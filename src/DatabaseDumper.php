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
        $host = escapeshellarg($this->mysql['host'] ?? '127.0.0.1');
        $port = (int) ($this->mysql['port'] ?? 3306);
        $user = escapeshellarg($this->mysql['username']);
        $pass = escapeshellarg($this->mysql['password']);
        $db = escapeshellarg($database);

        // build dump command
        $cmd = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%d ' .
            '--single-transaction --quick --routines --triggers --events --set-gtid-purged=OFF %s | gzip -c > %s',
            $user,
            $pass,
            $host,
            $port,
            $db,
            escapeshellarg($targetFile)
        );

        $exitCode = 0;
        system($cmd, $exitCode);
        if ($exitCode !== 0) {
            @unlink($targetFile);
            throw new \RuntimeException("mysqldump failed for DB '{$database}' (exit {$exitCode})");
        }
    }
}