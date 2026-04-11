$ErrorActionPreference = 'Stop'

$issues = @()

function Add-Issue {
    param([string]$Message)
    $script:issues += $Message
}

function Read-FileSafe {
    param([string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        Add-Issue "Missing required file: $Path"
        return ''
    }

    return Get-Content -LiteralPath $Path -Raw
}

function Assert-Contains {
    param(
        [string]$Content,
        [string]$Needle,
        [string]$Message
    )

    if (-not $Content.Contains($Needle)) {
        Add-Issue $Message
    }
}

function Assert-NotContains {
    param(
        [string]$Content,
        [string]$Needle,
        [string]$Message
    )

    if ($Content.Contains($Needle)) {
        Add-Issue $Message
    }
}

$requiredFiles = @(
    'scripts/security/security_smoke_tests.ps1',
    'scripts/security/SECURITY_SMOKE_TESTS.md',
    'scripts/security/admin_e2e_abuse_tests.ps1',
    'scripts/security/xss_crawler_probe.ps1',
    'scripts/maintenance/backup_restore_test.ps1',
    'scripts/maintenance/BACKUP_RESTORE_TESTS.md'
)

foreach ($path in $requiredFiles) {
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Issue "Required security/maintenance artifact missing: $path"
    }
}

$bootstrapHelpersPhp = Read-FileSafe -Path 'backend/core/bootstrap_helpers.php'
if ($bootstrapHelpersPhp -ne '') {
    Assert-Contains -Content $bootstrapHelpersPhp -Needle 'random_bytes(24)' -Message 'CSP nonce must use random_bytes(24) in backend/core/bootstrap_helpers.php.'
    Assert-NotContains -Content $bootstrapHelpersPhp -Needle "`$_SESSION['commerza_csp_nonce']" -Message 'CSP nonce must be per-request, not persisted in session.'
    Assert-Contains -Content $bootstrapHelpersPhp -Needle "style-src 'self' 'unsafe-inline'" -Message 'CSP style-src should allow inline styles used by current templates.'
    Assert-Contains -Content $bootstrapHelpersPhp -Needle "script-src 'self' 'unsafe-inline'" -Message 'CSP script-src should allow inline scripts/events used by current templates.'
}

$securityHelpers = Read-FileSafe -Path 'backend/security/security_helpers.php'
if ($securityHelpers -ne '') {
    Assert-Contains -Content $securityHelpers -Needle 'commerza_secure_nonce_hex(16)' -Message 'Captcha challenge nonce must be generated via secure helper.'
    Assert-Contains -Content $securityHelpers -Needle 'maxlength="64"' -Message 'Custom captcha input maxlength should be 64.'
    Assert-Contains -Content $securityHelpers -Needle 'data-commerza-captcha-answer="1"' -Message 'Captcha input hardening marker is missing.'
    Assert-Contains -Content $securityHelpers -Needle "['paste', 'copy', 'cut', 'drop', 'contextmenu']" -Message 'Captcha input hardening should block paste/copy/cut/drop/contextmenu events.'
    Assert-Contains -Content $securityHelpers -Needle 'user-select:none;-webkit-user-select:none;">Security question:' -Message 'Captcha prompt should disable text selection.'
}

$accountPhp = Read-FileSafe -Path 'account.php'
if ($accountPhp -ne '') {
    Assert-NotContains -Content $accountPhp -Needle '<img src="{$logoUrl}"' -Message 'Account reset email template should not include logo image.'
}

$forgotPasswordPhp = Read-FileSafe -Path 'forgot-password.php'
if ($forgotPasswordPhp -ne '') {
    Assert-NotContains -Content $forgotPasswordPhp -Needle '<img src="{$logoUrl}"' -Message 'Forgot-password email template should not include logo image.'
}

$expiryCleanupPhp = Read-FileSafe -Path 'backend/jobs/expiry_cleanup.php'
if ($expiryCleanupPhp -ne '') {
    Assert-NotContains -Content $expiryCleanupPhp -Needle '<img src="' -Message 'Expiry cleanup email template should not include inline logo image.'
}

$adminAuthPhp = Read-FileSafe -Path 'admin/backend/auth.php'
if ($adminAuthPhp -ne '') {
    Assert-NotContains -Content $adminAuthPhp -Needle '<img src="' -Message 'Admin security email layout should not include logo image.'
}

$notificationsPhp = Read-FileSafe -Path 'backend/helpers/notifications.php'
if ($notificationsPhp -ne '') {
    Assert-NotContains -Content $notificationsPhp -Needle '<img src="' -Message 'Notification email layout should not include logo image.'
}

if ($issues.Count -gt 0) {
    Write-Host 'Security gate failed with the following issues:'
    $issues | ForEach-Object { Write-Host "- $_" }
    exit 1
}

Write-Host 'Security gate passed. Required hardening artifacts and checks are in place.'
exit 0

