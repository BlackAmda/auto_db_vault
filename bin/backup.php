#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use AutoDBVault\DatabaseDumper;
use AutoDBVault\BackupRunner;
use AutoDBVault\RetentionPolicy;
use AutoDBVault\Storage\LocalStorage;
use AutoDBVault\Storage\DriveStorage;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$config = require __DIR__ . '/../config.php';

$mysql = $config['mysql'];
$databases = $config['databases'];
$backupRoot = $config['backup_root'];
$retCfg = $config['retention'];
$tz = $config['timezone'] ?? 'UTC';

$dumper = new DatabaseDumper($mysql);
$local = new LocalStorage($backupRoot);
$policy = new RetentionPolicy($retCfg['daily_days']);

$drive = null;
$driveFolder = $config['drive_folder_name'] ?? null;
$googleClientFile = __DIR__ . '/../google_client.php';
if ($driveFolder && file_exists($googleClientFile)) {
    /** @var Google_Client $client */
    $client = require $googleClientFile;
    $drive = new DriveStorage($client, $driveFolder);
}

$runner = new BackupRunner($dumper, $local, $policy, $tz, $drive, $config['local_keep'] ?? false);
$runner->run($databases);

fwrite(STDOUT, "Backup run completed\n");