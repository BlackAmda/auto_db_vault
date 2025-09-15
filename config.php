<?php
return [
    'mysql' => [
        'host' => $_ENV['MYSQL_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['MYSQL_PORT'] ?? 3306),
        'username' => $_ENV['MYSQL_USER'] ?? '',
        'password' => $_ENV['MYSQL_PASSWORD'] ?? '',
    ],

    'databases' => array_map('trim', explode(',', $_ENV['BACKUP_DATABASES'] ?? '')),

    'backup_root' => $_ENV['BACKUP_ROOT'] ?? __DIR__ . '/backups',
    'drive_folder_name' => $_ENV['DRIVE_FOLDER_NAME'] ?? 'DB_BACKUPS',

    'local_keep' => filter_var($_ENV['LOCAL_KEEP'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'timezone' => $_ENV['TIMEZONE'] ?? 'UTC',

    'retention' => [
        'daily_days' => (int) ($_ENV['RETENTION_DAILY_DAYS'] ?? 3),
    ],
];
