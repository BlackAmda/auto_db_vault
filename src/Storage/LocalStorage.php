<?php
namespace AutoDBVault\Storage;

use AutoDBVault\Utils;

final class LocalStorage
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
        Utils::ensureDir($this->root);
    }

    public function dbDir(string $db): string
    {
        $dir = $this->root . '/' . $db;
        Utils::ensureDir($dir);
        return $dir;
    }

    public function put(string $db, string $localTempFile, string $finalName): string
    {
        $dir = $this->dbDir($db);
        $target = $dir . '/' . $finalName;
        if (!rename($localTempFile, $target)) {
            // if rename across partitions fails, fall back to copy
            if (!copy($localTempFile, $target)) {
                throw new \RuntimeException("Failed to move backup to {$target}");
            }
            @unlink($localTempFile);
        }
        return $target;
    }

    public function listFiles(string $db): array
    {
        $dir = $this->dbDir($db);
        $files = array_values(array_filter(scandir($dir), function ($f) use ($dir) {
            return $f !== '.' && $f !== '..' && is_file($dir . '/' . $f);
        }));
        sort($files);
        return $files;
    }

    public function delete(string $db, string $filename): void
    {
        $path = $this->dbDir($db) . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}