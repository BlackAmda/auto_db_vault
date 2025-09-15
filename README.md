# 🚀 AUTO\_DB\_VAULT

**AUTO\_DB\_VAULT** is a lightweight, modular PHP tool that automates MySQL backups (`mysqldump`), compresses them (`.sql.gz`), stores them locally, optionally mirrors them to **Google Drive**, and maintain old backups using a simple retention policy.

> **Current retention model (exactly as implemented):**
> Keep **all of today’s hourly backups** and keep the **last *N* days** of backups (retention N days are configurable). Everything older is deleted.

---

## 🧰 Features

* ✅ Automated MySQL dumps using `mysqldump`, compressed to `.sql.gz`
* ✅ Upload to **Google Drive** and store locally (optionally).
* ✅ Simple retention: **today’s hourlies + last N daily backups**
* ✅ Multiple databases (comma-separated list)
* ✅ Drive folders per DB under a top-level folder you choose
* ✅ Cron-friendly CLI (no loops, no web triggers)
* ✅ Configuration via **`.env`**

---

## 📦 Requirements

* **PHP 7.4+**
* **mysqldump** available on the server PATH
* **Composer** (for dependencies)
* **Google Cloud** project with **Drive API** enabled
* `credentials.json` (OAuth2 client) and a generated `token.json` for the server (refer instructions below)

---

## 📁 Project layout (key files)

```
config.php                 # Reads env vars -> runtime config array
backup.php                 # CLI entry (invokes BackupRunner)
google_client.php          # Creates Google Client from credentials+token
get_token.php              # One-time OAuth: generates token.json

BackupRunner.php           # Orchestrates dump -> local/drive -> retention
DatabaseDumper.php         # Runs mysqldump -> gzip
RetentionPolicy.php        # Keeps today's hourlies + last N daily backups
LocalStorage.php           # Local filesystem ops
DriveStorage.php           # Google Drive ops (folders, upload, list, delete)
Utils.php                  # Time, dir ensure, filename timestamp parsing
```

---

## 🔧 Install

```bash
# 1. Clone the project
git clone https://github.com/BlackAmda/auto_db_vault.git
cd auto_db_vault

# 2. Install dependencies
composer install
```

---

## ⚙️ Configure

Rename **`.env.example`** to **`.env`** in the project root and adjust values:

```dotenv
# MySQL
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_USER=root
MYSQL_PASSWORD=your_password
BACKUP_DATABASES=erp,abc

# Paths & behavior
BACKUP_ROOT=/opt/auto_db_vault/backups
LOCAL_KEEP=false
TIMEZONE=Asia/Colombo

# Google Drive
DRIVE_FOLDER_NAME=DB_BACKUPS

# Retention
RETENTION_DAILY_DAYS=3
```

The app reads these via `config.php` into:

```php
return [
  'mysql' => ['host','port','username','password'],
  'databases' => [...],
  'backup_root' => '...',
  'drive_folder_name' => '...',
  'local_keep' => true|false,
  'timezone' => 'Asia/Colombo',
  'retention' => ['daily_days' => 3],
];
```

---

## 🔑 Google OAuth (one-time)

1. Enable **Google Drive API** in your GCP project
2. Create **OAuth 2.0 Client (Desktop App)** and download `credentials.json` to the project root
3. Generate `token.json`:

```bash
php get_token.php
# open the URL, authorize, paste code back; token.json is saved
```

> The app uses full `Google_Service_Drive::DRIVE` scope today.

---

## ▶️ Run a backup

The `backup.php` lives in `bin/`:

```bash
php bin/backup.php
```

What it does (per DB):

1. `mysqldump` → `*.sql.gz` in `BACKUP_ROOT/DB_NAME/`
2. Uploads the file to Google Drive under `DRIVE_FOLDER_NAME/DB_NAME/` (if `google_client.php` present)
3. Applies retention both locally and on Drive

---

## ⏱️ Cron

Run hourly (top of the hour), with a log:

```bash
0 * * * * /usr/bin/php /path/to/auto_db_vault/bin/backup.php >> /path/to/auto_db_vault/auto_db_vault.log 2>&1
```

---

## 🗄️ Google Drive structure

```
DRIVE_FOLDER_NAME/
└── <DB_NAME>/
    ├── <DB_NAME>_YYYYMMDD_HHMMSS.sql.gz   # hourly backups (today kept)
    └── <DB_NAME>_YYYYMMDD_230000.sql.gz   # daily snapshots (kept for N days)
```

> Filenames include timestamps that `RetentionPolicy` parses to decide keep/delete.

---

## 🔁 Retention (what’s actually implemented)

* **Keep:** all backups from **today** (hourly series)
* **Keep:** the **last N days** (one daily snapshot per day)
* **Delete:** anything older than N days (and not from today)

`N = RETENTION_DAILY_DAYS` in `.env` (default `3` if omitted).

---

## ♻️ Restore (quick guide)

Download from Drive or pick a local file, then:

```bash
# Local restore example
gunzip -c backups/erp_db/erp_db_20250914_021500.sql.gz | \
  mysql -h 127.0.0.1 -u root -p erp_db
```

> Ensure the MySQL user has the necessary privileges for restore (CREATE/ALTER/INSERT, etc.).

---

## 🔐 Security tips

* Use a dedicated MySQL user with least privileges needed for dump (`SELECT`, `SHOW VIEW`, `TRIGGER`, `EVENT`, maybe `PROCESS`)
* Keep `credentials.json` / `token.json` permissions tight

---

## 📄 License

MIT © 2025

---

## 🧭 Roadmap / TODO (nice-to-haves for future)

* Weekly / monthly “roll-ups” (grandfather-father-son retention) with configurable counts
* Switch Drive scope to `drive.file` (least privilege)
* Optional **service account** auth mode (headless)
* **Retries + exponential backoff** for Drive uploads; **resumable uploads** for large files
* **Checksums** (store and verify SHA-256; compare with Drive `md5Checksum`)
* **File locking** (`flock`) to prevent overlapping runs
* Structured **logging** (Monolog), JSON logs, clearer exit codes
* Optional **GPG encryption** (`.sql.gz.gpg`) before upload
* CLI polish (flags: `--db=`, `--dry-run`, `--no-drive`, `--verify`)
* Unit tests for `RetentionPolicy` + `Utils::parseTimestampFromFilename`
* Systemd unit + timer samples; GitHub Actions for lint/tests
* Support table/data exclusions (e.g., `EXCLUDE_TABLES`, `EXCLUDE_DATA_TABLES`)
* Detect and use `pigz` for faster compression