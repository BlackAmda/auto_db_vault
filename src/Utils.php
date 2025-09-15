<?php
namespace AutoDBVault;

final class Utils
{
    public static function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0775, true) && !is_dir($path)) {
                throw new \RuntimeException("Failed to create directory: {$path}");
            }
        }
    }

    public static function now(string $tz): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($tz));
    }

    public static function formatHourlyFilename(string $db, \DateTimeImmutable $dt): string
    {
        return sprintf('%s_backup_%s.sql.gz', $db, $dt->format('Ymd_His')); 
        // sample file name: erp_backup_20250826_160001.sql.gz
    }

    // to-do: rename to formatDailyFilename
    public static function formatDailyFilename(string $db, \DateTimeImmutable $dt): string
    {
        return sprintf('%s_backup_%s.sql.gz', $db, $dt->format('Ymd'));
    }

    // to-do: parseTimestampFromFilename
    public static function parseTimestampFromFilename(string $filename): ?\DateTimeImmutable
    {
        if (preg_match('/_(\d{8})_(\d{6})\.sql\.gz$/', $filename, $m)) {
            return \DateTimeImmutable::createFromFormat('Ymd_His', $m[1] . '_' . $m[2], new \DateTimeZone('UTC')) ?: null;
        }
        if (preg_match('/_(\d{8})\.sql\.gz$/', $filename, $m)) {
            return \DateTimeImmutable::createFromFormat('Ymd H:i:s', $m[1] . ' 00:00:00', new \DateTimeZone('UTC')) ?: null;
        }
        return null;
    }
}