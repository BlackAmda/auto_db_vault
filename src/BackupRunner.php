<?php
namespace AutoDBVault;

use AutoDBVault\Storage\LocalStorage;
use AutoDBVault\Storage\DriveStorage;

final class BackupRunner
{
    private DatabaseDumper $dumper;
    private LocalStorage $local;
    private ?DriveStorage $drive;
    private RetentionPolicy $policy;
    private string $tz;
    private bool $localKeep;

    public function __construct(
        DatabaseDumper $dumper,
        LocalStorage $local,
        RetentionPolicy $policy,
        string $timezone,
        ?DriveStorage $drive = null,
        bool $localKeep = false
    ) {
        $this->dumper = $dumper;
        $this->local = $local;
        $this->policy = $policy;
        $this->drive = $drive;
        $this->tz = $timezone;
        $this->localKeep = $localKeep;
    }

    public function run(array $databases): void
    {
        $now = Utils::now($this->tz);
        foreach ($databases as $db) {
            try {
                $this->backupOne($db, $now);
                $this->enforceRetentionLocal($db, $now);
                $this->enforceRetentionDrive($db, $now);
                fwrite(STDOUT, "[OK] {$db}\n");
            } catch (\Throwable $e) {
                // continue with next DB
                fwrite(STDERR, "[FAIL] {$db}: " . $e->getMessage() . "\n");
            }
        }
    }

    private function backupOne(string $db, \DateTimeImmutable $now): void
    {
        // create hourly file in a temp path
        $hourlyName = Utils::formatHourlyFilename($db, $now);
        $tempPath = sys_get_temp_dir() . '/' . uniqid('dump_', true) . '.sql.gz';

        $this->dumper->dump($db, $tempPath);

        // move into local storage
        $finalLocal = $this->local->put($db, $tempPath, $hourlyName);

        // upload to google drive
        if ($this->drive) {
            $fileId = $this->drive->upload($db, $finalLocal, basename($finalLocal));
            if (!$this->localKeep && $fileId && file_exists($finalLocal)) {
                unlink($finalLocal); // only delete if confirmed
            } else {
                error_log("Skipped deleting local backup for {$db}, upload not confirmed.");
            }
        }
    }

    private function enforceRetentionLocal(string $db, \DateTimeImmutable $now): void
    {
        $files = $this->local->listFiles($db); // sorted asc by name
        $decision = $this->policy->evaluate($files, $now);

        // delete flagged files from local storage
        foreach ($decision['delete'] as $f) {
            $this->local->delete($db, $f);
        }
    }

    private function enforceRetentionDrive(string $db, \DateTimeImmutable $now): void
    {
        if (!$this->drive)
            return;
        $files = $this->drive->listFiles($db);
        $names = [];
        foreach ($files as $f) {
            $names[] = $f->getName();
        }
        sort($names);

        $decision = $this->policy->evaluate($names, $now);

        // delete flagged files from google drive
        foreach ($decision['delete'] as $name) {
            foreach ($files as $f) {
                if ($f->getName() === $name) {
                    $this->drive->deleteById($f->getId());
                }
            }
        }
    }
}