param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$DbHost = '',
    [string]$DbUser = '',
    [string]$DbPassword = '',
    [string]$DbName = '',
    [string]$MySqlBinPath = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-ConfigValue {
    param(
        [string]$Primary,
        [string]$Secondary,
        [string]$Fallback
    )

    if ($Primary -ne '') {
        return $Primary
    }

    if ($Secondary -ne '') {
        return $Secondary
    }

    return $Fallback
}

function Find-MySqlBinaries {
    param([string]$PreferredPath)

    $candidates = @()
    if ($PreferredPath -ne '') {
        $candidates += $PreferredPath
    }

    if ($env:COMMERZA_MYSQL_BIN) {
        $candidates += $env:COMMERZA_MYSQL_BIN
    }

    $candidates += @(
        'C:\xampp\mysql\bin',
        'C:\Program Files\MySQL\MySQL Server 8.0\bin',
        'C:\Program Files\MariaDB 10.11\bin'
    )

    foreach ($candidate in $candidates) {
        if (-not $candidate) {
            continue
        }

        $resolved = $candidate
        if (-not (Test-Path -LiteralPath $resolved)) {
            continue
        }

        $mysqldump = Join-Path $resolved 'mysqldump.exe'
        $mysql = Join-Path $resolved 'mysql.exe'

        if ((Test-Path -LiteralPath $mysqldump) -and (Test-Path -LiteralPath $mysql)) {
            return @{
                mysql = $mysql
                mysqldump = $mysqldump
            }
        }
    }

    throw 'Unable to locate mysql.exe and mysqldump.exe. Set -MySqlBinPath or COMMERZA_MYSQL_BIN.'
}

function Get-MySqlArgs {
    param(
        [string]$DbHostName,
        [string]$DbUserName,
        [string]$DbPasswordValue
    )

    $args = @("--host=$DbHostName", "--user=$DbUserName")
    if ($DbPasswordValue -ne '') {
        $args += "--password=$DbPasswordValue"
    }

    return $args
}

function Get-TableCount {
    param(
        [string]$MySqlExe,
        [string[]]$ConnArgs,
        [string]$SchemaName
    )

    $query = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$SchemaName';"
    $result = & $MySqlExe @ConnArgs --batch --skip-column-names --execute=$query
    if ($LASTEXITCODE -ne 0) {
        throw "Failed table count query for schema '$SchemaName'."
    }

    return [int]($result | Select-Object -First 1)
}

function Test-TableExists {
    param(
        [string]$MySqlExe,
        [string[]]$ConnArgs,
        [string]$SchemaName,
        [string]$TableName
    )

    $query = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$SchemaName' AND table_name = '$TableName';"
    $result = & $MySqlExe @ConnArgs --batch --skip-column-names --execute=$query
    if ($LASTEXITCODE -ne 0) {
        throw "Failed table-exists query for '$SchemaName.$TableName'."
    }

    return ([int]($result | Select-Object -First 1)) -gt 0
}

function Get-RowCount {
    param(
        [string]$MySqlExe,
        [string[]]$ConnArgs,
        [string]$SchemaName,
        [string]$TableName
    )

    $query = "SELECT COUNT(*) FROM $SchemaName.$TableName;"
    $result = & $MySqlExe @ConnArgs --batch --skip-column-names --execute=$query
    if ($LASTEXITCODE -ne 0) {
        throw "Failed row count query for '$SchemaName.$TableName'."
    }

    return [int]($result | Select-Object -First 1)
}

function Assert {
    param(
        [bool]$Condition,
        [string]$Message
    )

    if (-not $Condition) {
        throw $Message
    }
}

$DbHost = Resolve-ConfigValue -Primary $DbHost -Secondary $env:COMMERZA_DB_HOST -Fallback 'localhost'
$DbUser = Resolve-ConfigValue -Primary $DbUser -Secondary $env:COMMERZA_DB_USER -Fallback 'root'
$DbName = Resolve-ConfigValue -Primary $DbName -Secondary $env:COMMERZA_DB_NAME -Fallback 'commerza'

if ($DbPassword -eq '') {
    if ($env:COMMERZA_DB_PASS) {
        $DbPassword = $env:COMMERZA_DB_PASS
    } elseif ($env:DB_PASS) {
        $DbPassword = $env:DB_PASS
    }
}

$tools = Find-MySqlBinaries -PreferredPath $MySqlBinPath
$mysqlExe = $tools.mysql
$mysqldumpExe = $tools.mysqldump
$connArgs = Get-MySqlArgs -DbHostName $DbHost -DbUserName $DbUser -DbPasswordValue $DbPassword

$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$workDir = Join-Path $ProjectRoot (Join-Path 'tmp' "backup-restore-test-$timestamp")
New-Item -ItemType Directory -Path $workDir -Force | Out-Null

$dumpFile = Join-Path $workDir "$DbName-backup.sql"
$dumpErrFile = Join-Path $workDir 'mysqldump.err.log'

$restoreDb = ("${DbName}_restore_test_$timestamp" -replace '[^A-Za-z0-9_]', '_').ToLowerInvariant()

$mediaTargets = @(
    'frontend/assets/images',
    'frontend/assets/videos',
    'admin/frontend/assets/images'
)

$backupManifest = @()

Write-Output "[1/6] Creating SQL backup: $dumpFile"
$dumpArgs = $connArgs + @('--single-transaction', '--routines', '--events', '--triggers', '--default-character-set=utf8mb4', $DbName)
& $mysqldumpExe @dumpArgs 2> $dumpErrFile | Out-File -FilePath $dumpFile -Encoding utf8
if ($LASTEXITCODE -ne 0) {
    $dumpErr = ''
    if (Test-Path -LiteralPath $dumpErrFile) {
        $dumpErr = Get-Content -LiteralPath $dumpErrFile -Raw
    }
    throw "mysqldump failed. $dumpErr"
}

Assert -Condition (Test-Path -LiteralPath $dumpFile) -Message 'Backup SQL file was not created.'
Assert -Condition ((Get-Item -LiteralPath $dumpFile).Length -gt 1024) -Message 'Backup SQL file is unexpectedly small.'

Write-Output '[2/6] Backing up media directories'
foreach ($relativePath in $mediaTargets) {
    $sourcePath = Join-Path $ProjectRoot $relativePath
    if (-not (Test-Path -LiteralPath $sourcePath)) {
        continue
    }

    $zipName = ($relativePath -replace '[\\/]', '_') + '.zip'
    $zipPath = Join-Path $workDir $zipName

    Compress-Archive -Path $sourcePath -DestinationPath $zipPath -CompressionLevel Optimal -Force

    $sourceCount = (Get-ChildItem -LiteralPath $sourcePath -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count

    $backupManifest += [PSCustomObject]@{
        RelativePath = $relativePath
        SourcePath = $sourcePath
        ZipPath = $zipPath
        SourceCount = [int]$sourceCount
    }
}

Write-Output "[3/6] Restoring SQL backup into test schema: $restoreDb"
$createSql = "DROP DATABASE IF EXISTS $restoreDb; CREATE DATABASE $restoreDb CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
& $mysqlExe @connArgs --execute=$createSql
if ($LASTEXITCODE -ne 0) {
    throw 'Failed to create restore test schema.'
}

Get-Content -LiteralPath $dumpFile -Raw | & $mysqlExe @connArgs $restoreDb
if ($LASTEXITCODE -ne 0) {
    throw 'Failed to import SQL backup into restore test schema.'
}

Write-Output '[4/6] Validating restored table and row counts'
$sourceTableCount = Get-TableCount -MySqlExe $mysqlExe -ConnArgs $connArgs -SchemaName $DbName
$restoreTableCount = Get-TableCount -MySqlExe $mysqlExe -ConnArgs $connArgs -SchemaName $restoreDb
Assert -Condition ($sourceTableCount -eq $restoreTableCount) -Message "Table count mismatch. Source: $sourceTableCount, Restored: $restoreTableCount"

$criticalTables = @('users', 'products', 'orders', 'order_items', 'coupons', 'coupon_redemptions', 'product_reviews')
foreach ($tableName in $criticalTables) {
    if (-not (Test-TableExists -MySqlExe $mysqlExe -ConnArgs $connArgs -SchemaName $DbName -TableName $tableName)) {
        continue
    }

    $sourceRows = Get-RowCount -MySqlExe $mysqlExe -ConnArgs $connArgs -SchemaName $DbName -TableName $tableName
    $restoreRows = Get-RowCount -MySqlExe $mysqlExe -ConnArgs $connArgs -SchemaName $restoreDb -TableName $tableName
    Assert -Condition ($sourceRows -eq $restoreRows) -Message "Row count mismatch in table '$tableName'. Source: $sourceRows, Restored: $restoreRows"
}

Write-Output '[5/6] Restoring media backups and validating file counts'
$mediaRestoreRoot = Join-Path $workDir 'media-restore-check'
New-Item -ItemType Directory -Path $mediaRestoreRoot -Force | Out-Null

foreach ($entry in $backupManifest) {
    $extractPath = Join-Path $mediaRestoreRoot ($entry.RelativePath -replace '[\\/]', '_')
    New-Item -ItemType Directory -Path $extractPath -Force | Out-Null

    Expand-Archive -Path $entry.ZipPath -DestinationPath $extractPath -Force

    $restoredCount = (Get-ChildItem -LiteralPath $extractPath -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
    Assert -Condition ([int]$entry.SourceCount -eq [int]$restoredCount) -Message "Media file count mismatch for '$($entry.RelativePath)'. Source: $($entry.SourceCount), Restored: $restoredCount"
}

Write-Output '[6/6] Cleaning up restore test schema'
& $mysqlExe @connArgs --execute="DROP DATABASE IF EXISTS $restoreDb;"
if ($LASTEXITCODE -ne 0) {
    throw "Failed to drop restore test schema '$restoreDb'."
}

Write-Output ''
Write-Output 'Backup and restore test completed successfully.'
Write-Output "Artifacts saved in: $workDir"
