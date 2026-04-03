# Backup and Restore Test

This repository now includes an automated backup/restore verification script:

- Script: backend/backup_restore_test.ps1
- Purpose: Validate that SQL backups can be restored and that media archives can be extracted without data/file-count loss.

## What It Tests

1. Creates a SQL dump of the configured database.
2. Archives media folders used by storefront/admin.
3. Restores the SQL dump into a temporary test database.
4. Verifies table counts and critical row counts match source.
5. Extracts media archives and compares file counts.
6. Drops the temporary restore database.

## Default Connection Resolution

The script resolves DB settings in this order:

1. Explicit script parameters
2. COMMERZA_DB_HOST / COMMERZA_DB_USER / COMMERZA_DB_PASS / COMMERZA_DB_NAME
3. Fallback defaults: localhost, root, (empty password), commerza

## MySQL Binaries

It looks for mysql.exe and mysqldump.exe in this order:

1. -MySqlBinPath parameter
2. COMMERZA_MYSQL_BIN env var
3. Common Windows install paths (including C:\xampp\mysql\bin)

## Run Example

```powershell
powershell -ExecutionPolicy Bypass -File backend/backup_restore_test.ps1 -MySqlBinPath "C:\xampp\mysql\bin"
```

Optional explicit DB credentials:

```powershell
powershell -ExecutionPolicy Bypass -File backend/backup_restore_test.ps1 -DbHost localhost -DbUser root -DbPassword "" -DbName commerza -MySqlBinPath "C:\xampp\mysql\bin"
```

## Output

On success, the script prints a completion message and stores artifacts under:

- tmp/backup-restore-test-<timestamp>

If any validation fails, the script exits with an error and keeps artifacts for debugging.
