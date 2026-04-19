<?php
namespace AutoDBVault;

final class DatabaseDumper
{
    private array $mysql;

    public function __construct(array $mysql)
    {
        $this->mysql = $mysql;
    }

    /**
     * Runs mysqldump and gzips output to targetFile.
     * Includes routines, triggers, events, and uses single-transaction for InnoDB consistency.
     */
    public function dump(string $database, string $targetFile): void
    {
        $host = $this->mysql['host'] ?? '127.0.0.1';
        $port = (int) ($this->mysql['port'] ?? 3306);
        $user = $this->mysql['username'];
        $pass = $this->mysql['password'];

        $cnfPath = tempnam(sys_get_temp_dir(), 'mysqldump_') . '.cnf';
        file_put_contents($cnfPath, "[client]\npassword=" . $pass . "\n", LOCK_EX);
        chmod($cnfPath, 0600);

        $cmd = sprintf(
            'mysqldump --defaults-extra-file=%s --user=%s --host=%s --port=%d ' .
            '--single-transaction --quick --routines --triggers --events --set-gtid-purged=OFF %s | gzip -c > %s',
            escapeshellarg($cnfPath),
            escapeshellarg($user),
            escapeshellarg($host),
            $port,
            escapeshellarg($database),
            escapeshellarg($targetFile)
        );

        $exitCode = 0;
        system($cmd, $exitCode);
        @unlink($cnfPath);
        if ($exitCode !== 0) {
            @unlink($targetFile);
            throw new \RuntimeException("mysqldump failed for DB '{$database}' (exit {$exitCode})");
        }
    }
}