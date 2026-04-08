# Backup and Restore Test Runbook

## Purpose

This runbook verifies that database and media backups can be restored correctly in the local XAMPP environment.

## Prerequisites

- XAMPP MySQL is running
- `C:\xampp\mysql\bin` is available
- PHP CLI available at `C:\xampp\php\php.exe`
- Sufficient disk space for backup artifacts

## Script

- Primary script: `scripts/maintenance/backup_restore_test.ps1`

## Example Command

`powershell -ExecutionPolicy Bypass -File scripts/maintenance/backup_restore_test.ps1 -MySqlBinPath "C:\xampp\mysql\bin"`

## What the Test Should Validate

1. Database dump can be generated successfully.
2. Fresh test schema can be restored from dump.
3. Table count and key data checks match expected baseline.
4. Media directory backup and restore paths are valid.

## Success Criteria

- Script exits with success code.
- No table import failures in output.
- Restored schema contains expected core tables.
- Media restore path validation passes.

## Failure Triage

- MySQL authentication failure:
  - verify credentials and MySQL service state
- Dump/restore path errors:
  - verify path permissions and available storage
- SQL import failures:
  - inspect failing statement and schema compatibility

## Recommended Frequency

- Before production release
- After schema migrations
- After backup strategy changes
