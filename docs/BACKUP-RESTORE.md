# Database backup and operator recovery

The Settings tabs reuse the shared admin login, PHP sessions and CSRF protection. Run `C:\xampp\php\php.exe tools/setup-database.php` for a fresh installation. On an existing installation, run both `tools/setup-settings.php` and `tools/setup-backups.php` with PHP to add the security/audit and backup tables. Migrations preserve existing rows. Restart PHP after updating code if opcode caching is enabled.

## Security policies

Defaults: minimum 12 characters, maximum 64, 30-minute inactivity timeout, 5 failed attempts and 15-minute lockout. Security settings save the maximum password length, expiry duration, inactivity timeout, failed-attempt limit and lockout duration in `system_settings`. Password expiry is initially disabled; enter a positive number of days to enable it, or 0 to disable it. Existing passwords are not rejected merely because the new creation limits change. Existing hashes remain readable; new passwords use a versioned SHA-384/base64 prehash with PHP's `password_hash` so passwords up to 256 characters are never silently truncated by bcrypt. Password recovery always invalidates old sessions; ordinary password changes invalidate them by default. `config/security.php` supplies defaults for values without a saved override, including minimum password length and session invalidation.

Lockout counters are scoped to a normalized account identity and request source, with the existing broader IP throttle retained. This reduces remote account lockout abuse. No forwarded IP headers are trusted. Successful authentication resets that counter; an elapsed lockout unlocks it on the next attempt. Expired passwords require the existing email OTP recovery flow. Enabling expiry or shortening sessions can sign out affected users.

Audit records are append-only through application endpoints. They include snapshot actor metadata, request IP and parsed device information; geographic location is not collected. Older pre-upgrade activity remains in the original activity table and is not presented as newly captured detailed audit data. Normal application actions use the shared audit service. Run `php tools/prune-audit.php` from a restricted server account to apply the configured retention (365 days initially). Schedule that CLI separately if desired. This is the sole supported retention deletion workflow. The private recovery journal remains outside the database and is not pruned by that command.

## Private storage

On this XAMPP installation, backups are stored under `C:\xampp\pikolcastle-private\<database-name>`, outside `htdocs`. The UI exposes a storage label only. A server operator may set `PIKOL_BACKUP_ROOT` to another protected directory outside the DocumentRoot; use the same environment for Apache and scheduled CLI jobs. For Linux deployments, explicitly set a private absolute path such as `/var/lib/pikolcastle/backups` and ensure the PHP service and scheduler user have access.

Keep the directory off shared drives. On Windows, the backup service restricts a new database storage directory to the current PHP identity, Administrators and SYSTEM using icacls. Configure the scheduler to use that same service identity, or explicitly grant its approved service account access through server ACL tools. PHP chmod alone does not enforce Windows ACLs. On Linux use a dedicated service account and 0700 directories/0600 files. Database credentials are placed only in short-lived protected option files, never process arguments. Do not expose backup directories through Apache aliases, static hosting, or file browsers.

XAMPP uses its bundled `mysql/bin/mysqldump.exe` and `mysql.exe`, resolved from the loaded PHP configuration for both Apache and CLI execution. Linux checks `/usr/bin` and `/usr/local/bin`. Operators with a different installation can set `PIKOL_MYSQL_BIN` to the directory containing these utilities. The database account needs dump permissions for structure, data, triggers, routines and events. Process execution must be enabled. The database should use transactional InnoDB tables; concurrent DDL/schema migrations must be paused during backups. Backups contain sensitive account hashes, personal information and payment records. Encrypt offline copies and restrict downloads.

## Manual backups

Choose Settings → Backup Data → **Download database backup**. The browser downloads an SQL file generated through PHP/PDO, without `mysqldump`, shell access, a configured backup path or a persistent server backup file. PHP may use temporary storage while preparing the response. The export includes base-table schemas, consistent InnoDB table data, views and accessible triggers. Stored routines/events and uploaded files are not included. Completion is checked before the download response starts. Exports are recorded in Audit Logs rather than Backup History. Keep the downloaded file securely on your own device.

Backup History still lists existing server-stored backups, whose download links verify file size and SHA-256. Scheduled server backups remain separate and require a supported server environment; a scheduled task cannot download a file to a closed browser. Server-generated backups may contain a Pending self-record because their history record is created before dumping; live history is authoritative.

An operator can also run:

```powershell
C:\xampp\php\php.exe tools/manual-backup.php
```

Failed operations remain in history with a safe error summary. Partial SQL artifacts are removed. Backup Data provides history filters, details and verified downloads; existing completed backups are preserved. Proof screenshots and avatars are filesystem assets and are **not included in SQL backups**; back up `storage/proofs` and `uploads` separately using the server's private filesystem backup procedure.

## Real scheduling

Saving a schedule does not install a scheduler. The UI reports enabled configuration separately from runner health. A runner heartbeat within the past 11 minutes is the operational signal; otherwise the UI warns that execution is not configured/recent. Automatic backups are disabled initially. The runner executes at most once for the most recent due occurrence and catches up a missed occurrence when the server restarts. Monthly dates past the end of a month use its last day. Failures do not mark the occurrence complete and are retried at the next runner invocation.

For Windows XAMPP, from the project directory:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools/install-backup-task.ps1
```

This installs a Task Scheduler task every five minutes. In Task Scheduler, configure the approved service account to **run whether the user is logged on or not**, with permission to run PHP, connect to MySQL and write private storage. Supply that account's credentials through Windows, not source files. Verify the task's Last Run Result and the app's heartbeat. The installer does not claim unattended operation until it has been configured and observed. Keep the machine and MySQL running. To remove it, delete the named PikolCastle-Backup task in Task Scheduler.

Linux cron example, using the deployment service account (replace paths):

```cron
*/5 * * * * cd /srv/pikolcastle && PIKOL_BACKUP_ROOT=/var/lib/pikolcastle/backups /usr/bin/php tools/backup-runner.php >> /var/log/pikolcastle-backup.log 2>&1
15 3 * * * cd /srv/pikolcastle && /usr/bin/php tools/prune-audit.php >> /var/log/pikolcastle-audit-retention.log 2>&1
```

Configure DB environment variables in a protected service configuration, not the cron command or a public file. Rotate runner logs. The runner uses Asia/Manila regardless of OS timezone and an advisory lock prevents overlapping schedule runs. Do not install this QA/demo schedule against a disposable test database.

## Verify a backup before recovery

Run `php tools/verify-backup.php <backup-id>` from a restricted server account. It checks the stored size and checksum, imports the SQL into a disposable database, checks the current application tables, and removes that disposable database. It records the result in the application audit log and private recovery journal. Live application data is unchanged. Backup Data does not expose browser restoration.

Automatic live restoration is deliberately unavailable on this shared local XAMPP deployment: PHP cannot safely coordinate database replacement, existing Apache workers, all sessions and external processes atomically. Use the controlled operator procedure below. The application never claims that a preview restored the database.

## Operator recovery procedure

1. Notify users and choose a maintenance window. Stop scheduled jobs. Create a **new verified recovery backup** of current data. Preserve the target backup and its metadata/checksum in private storage. Download/copy neither into `htdocs`.
2. Test the selected backup first, replacing `42` with its history ID:

   ```powershell
   C:\xampp\php\php.exe tools/verify-backup.php 42
   ```

   This verifies checksum, creates a random disposable database, imports using the fixed MySQL client, checks required tables, and drops only that generated test database. Live data is unchanged. The database account needs CREATE/DROP DATABASE privileges for this check. A failed validation blocks the procedure; do not proceed on an error.
3. Enable application maintenance: `php tools/maintenance.php on`. Stop Apache using XAMPP after maintenance is set, and stop the backup scheduled task. Keep MySQL running. This closes in-flight web workers and prevents writes during replacement. Maintenance flags live outside the database and survive recovery.
4. With a restricted database operator account, use MySQL's client and a protected `--defaults-extra-file` or password prompt to import the **verified private SQL file** into the exact intended database. Confirm database identity, backup reference and checksum independently. Do not put passwords on a command line. Example from an interactive MySQL client started with `mysql.exe -u <operator> -p <database>`:

   ```sql
   SOURCE C:/xampp/pikolcastle-private/<database>/backup-<verified-random-name>.sql;
   ```

   The SQL dump replaces tables, so this step is destructive. Do not use an uploaded or third-party SQL file. Use a staging server first for production recovery. Preserve the pre-restore recovery backup until post-recovery checks complete.
5. Run schema compatibility migrations if restoring an older deployment, then `php tools/check-install.php`. Reconcile the restored database with the matching separately backed-up proof/avatar filesystem. Run `php tools/record-restore.php BKP-<target-reference> success` only after a successful import. It verifies installation, invalidates sessions and logs completion in MySQL and the private recovery journal. A restored snapshot may not contain the target backup's later history row; the external recovery journal preserves its reference.
6. If import fails, **keep maintenance and Apache stopped**. Run `php tools/record-restore.php BKP-<target-reference> failure`; the private journal works even if database audit insertion fails. Recover using the pre-restore backup, repeat validation and installation checks, and investigate before reopening. Never assume a partially imported database is usable.
7. Only after verification, run `php tools/maintenance.php off`, start Apache, test admin/customer sign-in and bookings, and restart the scheduled task. Keep private recovery records and the recovery backup according to your operational retention policy.

No automated job in this implementation performs live database restoration. All restore import testing must use disposable databases first.

