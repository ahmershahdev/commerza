param(
    [string]$BaseUrl = "http://localhost/commerza",
    [string]$AdminEmail = $env:COMMERZA_ADMIN_TEST_EMAIL,
    [string]$AdminPassword = $env:COMMERZA_ADMIN_TEST_PASSWORD,
    [string]$AdminTwoFactorCode = $env:COMMERZA_ADMIN_TEST_2FA_CODE,
    [string]$AdminSessionId = $env:COMMERZA_ADMIN_TEST_SESSION_ID,
    [int]$TimeoutSec = 30,
    [switch]$RequireAuthenticated
)

$ErrorActionPreference = 'Stop'

$BaseUrl = $BaseUrl.TrimEnd('/')
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$results = @()
$script:SupportsSkipHttpErrorCheck = (Get-Command Invoke-WebRequest).Parameters.ContainsKey('SkipHttpErrorCheck')
$script:SupportsUseBasicParsing = (Get-Command Invoke-WebRequest).Parameters.ContainsKey('UseBasicParsing')

function Add-TestResult {
    param(
        [string]$Name,
        [string]$Status,
        [string]$Details
    )

    $script:results += [pscustomobject]@{
        Name = $Name
        Status = $Status
        Details = $Details
    }
}

function Get-HttpErrorResponse {
    param(
        [Parameter(Mandatory = $true)]
        [System.Management.Automation.ErrorRecord]$ErrorRecord
    )

    $exception = $ErrorRecord.Exception
    $response = $exception.Response
    if (-not $response) {
        throw $exception
    }

    $statusCode = 0
    try {
        $statusCode = [int]$response.StatusCode
    } catch {
        $statusCode = 0
    }

    $body = ''
    try {
        $stream = $response.GetResponseStream()
        if ($stream) {
            $reader = New-Object System.IO.StreamReader($stream)
            try {
                $body = $reader.ReadToEnd()
            } finally {
                $reader.Dispose()
                $stream.Dispose()
            }
        }
    } catch {
        $body = ''
    }

    if ([string]::IsNullOrWhiteSpace($body) -and $ErrorRecord.ErrorDetails -and $ErrorRecord.ErrorDetails.Message) {
        $body = [string]$ErrorRecord.ErrorDetails.Message
    }

    return [pscustomobject]@{
        StatusCode = $statusCode
        Body = $body
        Headers = @{}
        FinalUrl = ''
    }
}

function Invoke-CommerzaRequest {
    param(
        [ValidateSet('GET', 'POST')]
        [string]$Method,
        [string]$Url,
        [hashtable]$Form,
        [hashtable]$Headers
    )

    $requestParams = @{
        Uri = $Url
        Method = $Method
        WebSession = $session
        TimeoutSec = $TimeoutSec
    }

    if ($script:SupportsUseBasicParsing) {
        $requestParams.UseBasicParsing = $true
    }

    if ($Headers -and $Headers.Count -gt 0) {
        $requestParams.Headers = $Headers
    }

    if ($Method -eq 'POST') {
        $requestParams.Body = $Form
        $requestParams.ContentType = 'application/x-www-form-urlencoded'
    }

    $statusCode = 0
    $body = ''
    $headerMap = @{}
    $finalUrl = ''

    if ($script:SupportsSkipHttpErrorCheck) {
        $response = Invoke-WebRequest @requestParams -SkipHttpErrorCheck
        $statusCode = [int]$response.StatusCode
        $body = [string]$response.Content
        $finalUrl = [string]$response.BaseResponse.ResponseUri.AbsoluteUri
        if ($response.Headers) {
            foreach ($key in $response.Headers.Keys) {
                $headerMap[$key] = [string]$response.Headers[$key]
            }
        }
    } else {
        try {
            $response = Invoke-WebRequest @requestParams
            $statusCode = [int]$response.StatusCode
            $body = [string]$response.Content
            $finalUrl = [string]$response.BaseResponse.ResponseUri.AbsoluteUri
            if ($response.Headers) {
                foreach ($key in $response.Headers.Keys) {
                    $headerMap[$key] = [string]$response.Headers[$key]
                }
            }
        } catch {
            $errorResponse = Get-HttpErrorResponse -ErrorRecord $_
            $statusCode = [int]$errorResponse.StatusCode
            $body = [string]$errorResponse.Body
            $headerMap = @{}
            $finalUrl = ''
        }
    }

    $json = $null
    try {
        $json = $body | ConvertFrom-Json -ErrorAction Stop
    } catch {
        $json = $null
    }

    return [pscustomobject]@{
        StatusCode = $statusCode
        Body = $body
        Json = $json
        Headers = $headerMap
        FinalUrl = $finalUrl
    }
}

function Get-HiddenInputValue {
    param(
        [string]$Html,
        [string]$Name
    )

    if ([string]::IsNullOrWhiteSpace($Html) -or [string]::IsNullOrWhiteSpace($Name)) {
        return ''
    }

    $pattern = 'name=["'']' + [regex]::Escape($Name) + '["''][^>]*value=["'']([^"'']*)'
    $match = [regex]::Match($Html, $pattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if (-not $match.Success) {
        return ''
    }

    return [System.Net.WebUtility]::HtmlDecode(($match.Groups[1].Value -as [string]))
}

function Get-MetaContent {
    param(
        [string]$Html,
        [string]$MetaName
    )

    if ([string]::IsNullOrWhiteSpace($Html) -or [string]::IsNullOrWhiteSpace($MetaName)) {
        return ''
    }

    $pattern = '<meta[^>]*name=["'']' + [regex]::Escape($MetaName) + '["''][^>]*content=["'']([^"'']*)'
    $match = [regex]::Match($Html, $pattern, [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if (-not $match.Success) {
        return ''
    }

    return [System.Net.WebUtility]::HtmlDecode(($match.Groups[1].Value -as [string]))
}

function Get-CaptchaQuestion {
    param([string]$Html)

    if ([string]::IsNullOrWhiteSpace($Html)) {
        return ''
    }

    $options = [System.Text.RegularExpressions.RegexOptions]::IgnoreCase -bor [System.Text.RegularExpressions.RegexOptions]::Singleline
    $primary = [regex]::Match($Html, 'Security\s*(?:check|question)\s*:\s*([^<\r\n]+)', $options)
    if ($primary.Success) {
        $question = [System.Net.WebUtility]::HtmlDecode(($primary.Groups[1].Value -as [string])).Trim()
        if ($question -ne '') {
            return $question.TrimEnd('?', '=')
        }
    }

    $mathFallback = [regex]::Match($Html, 'Solve:\s*-?\d+\s*[+\-x*/]\s*-?\d+', $options)
    if ($mathFallback.Success) {
        return [System.Net.WebUtility]::HtmlDecode(($mathFallback.Value -as [string])).Trim()
    }

    return ''
}

function Resolve-CaptchaAnswer {
    param([string]$Question)

    $q = ($Question ?? '').ToString().Trim().ToLowerInvariant()
    if ($q -eq '') {
        return ''
    }

    $knowledge = @{
        'what is the capital of france' = 'paris'
        'what is the capital of pakistan' = 'islamabad'
        'what is the capital of japan' = 'tokyo'
        'how many days are in one week' = '7'
        'which month comes after june' = 'july'
        'what is the first letter of the english alphabet' = 'a'
    }

    foreach ($key in $knowledge.Keys) {
        if ($q -like "*$key*") {
            return $knowledge[$key]
        }
    }

    $add = [regex]::Match($q, 'solve:\s*(-?\d+)\s*\+\s*(-?\d+)')
    if ($add.Success) {
        return ([int]$add.Groups[1].Value + [int]$add.Groups[2].Value).ToString()
    }

    $sub = [regex]::Match($q, 'solve:\s*(-?\d+)\s*-\s*(-?\d+)')
    if ($sub.Success) {
        return ([int]$sub.Groups[1].Value - [int]$sub.Groups[2].Value).ToString()
    }

    $mul = [regex]::Match($q, 'solve:\s*(-?\d+)\s*[x*]\s*(-?\d+)')
    if ($mul.Success) {
        return ([int]$mul.Groups[1].Value * [int]$mul.Groups[2].Value).ToString()
    }

    $div = [regex]::Match($q, 'solve:\s*(-?\d+)\s*/\s*(-?\d+)')
    if ($div.Success) {
        $left = [int]$div.Groups[1].Value
        $right = [int]$div.Groups[2].Value
        if ($right -ne 0 -and ($left % $right) -eq 0) {
            return ($left / $right).ToString()
        }
    }

    return ''
}

function Wait-MinSeconds {
    param([double]$Seconds = 4.2)

    $target = [DateTime]::UtcNow.AddSeconds([Math]::Max(0.0, $Seconds))
    while ([DateTime]::UtcNow -lt $target) {
        [void][Math]::Sqrt(144)
    }
}

$authenticated = $false

if (-not [string]::IsNullOrWhiteSpace($AdminSessionId)) {
    try {
        $baseUri = [Uri]$BaseUrl
        $cookie = New-Object System.Net.Cookie('PHPSESSID', $AdminSessionId.Trim(), '/', $baseUri.Host)
        $session.Cookies.Add($cookie)
        $authenticated = $true
        Add-TestResult -Name 'admin_session_cookie_supplied' -Status 'PASS' -Details 'Using provided admin session cookie for authenticated abuse checks.'
    } catch {
        Add-TestResult -Name 'admin_session_cookie_supplied' -Status 'FAIL' -Details 'Unable to initialize provided admin session cookie.'
    }
} elseif (-not [string]::IsNullOrWhiteSpace($AdminEmail) -and -not [string]::IsNullOrWhiteSpace($AdminPassword)) {
    $loginUrl = "$BaseUrl/admin/frontend/admin-login.php"
    $loginPage = Invoke-CommerzaRequest -Method GET -Url $loginUrl -Form $null -Headers @{}

    if ($loginPage.StatusCode -ne 200) {
        Add-TestResult -Name 'admin_login_page_load' -Status 'FAIL' -Details "Login page unavailable. HTTP $($loginPage.StatusCode)."
    } else {
        Add-TestResult -Name 'admin_login_page_load' -Status 'PASS' -Details 'Login page loaded.'

        $csrfToken = Get-HiddenInputValue -Html $loginPage.Body -Name 'csrf_token'
        $nextTarget = Get-HiddenInputValue -Html $loginPage.Body -Name 'next'
        $captchaStartedAt = Get-HiddenInputValue -Html $loginPage.Body -Name 'commerza_captcha_started_at'
        $captchaContext = Get-HiddenInputValue -Html $loginPage.Body -Name 'commerza_captcha_context'
        $captchaToken = Get-HiddenInputValue -Html $loginPage.Body -Name 'commerza_captcha_token'
        $captchaQuestion = Get-CaptchaQuestion -Html $loginPage.Body
        $captchaAnswer = Resolve-CaptchaAnswer -Question $captchaQuestion

        if ([string]::IsNullOrWhiteSpace($csrfToken) -or [string]::IsNullOrWhiteSpace($captchaStartedAt) -or [string]::IsNullOrWhiteSpace($captchaToken) -or [string]::IsNullOrWhiteSpace($captchaAnswer)) {
            Add-TestResult -Name 'admin_login_with_fallback_captcha' -Status 'FAIL' -Details 'Could not parse required login or fallback CAPTCHA fields.'
        } else {
            Wait-MinSeconds -Seconds 4.2

            $loginForm = @{
                csrf_token = $csrfToken
                next = $nextTarget
                admin_email = $AdminEmail
                admin_password = $AdminPassword
                commerza_captcha_started_at = $captchaStartedAt
                commerza_captcha_context = $captchaContext
                commerza_captcha_answer = $captchaAnswer
                commerza_captcha_token = $captchaToken
                commerza_contact_website = ''
                'g-recaptcha-v3-response' = ''
            }

            $loginSubmit = Invoke-CommerzaRequest -Method POST -Url $loginUrl -Form $loginForm -Headers @{}
            $twoFaPromptDetected = ($loginSubmit.Body -match 'verification_code|Verify\s*&\s*Login|admin-verify-2fa')
            $panelDetected = ($loginSubmit.Body -match 'admin-csrf-token|Coupon Campaign Studio|dashboardSection') -or ($loginSubmit.FinalUrl -match 'admin-panel')

            if ($panelDetected) {
                $authenticated = $true
                Add-TestResult -Name 'admin_login_with_fallback_captcha' -Status 'PASS' -Details 'Authenticated without 2FA step.'
            } elseif ($twoFaPromptDetected) {
                if ([string]::IsNullOrWhiteSpace($AdminTwoFactorCode)) {
                    Add-TestResult -Name 'admin_login_with_fallback_captcha' -Status 'PASS' -Details 'Password + CAPTCHA accepted, pending 2FA.'
                    Add-TestResult -Name 'admin_2fa_verify_step' -Status 'SKIP' -Details 'Set COMMERZA_ADMIN_TEST_2FA_CODE to run authenticated abuse checks.'
                } else {
                    $verifyUrl = "$BaseUrl/admin/frontend/admin-verify-2fa.php"
                    $verifyPage = $loginSubmit
                    if ($verifyPage.StatusCode -ne 200 -or -not ($verifyPage.Body -match 'verification_code')) {
                        $verifyPage = Invoke-CommerzaRequest -Method GET -Url $verifyUrl -Form $null -Headers @{}
                    }

                    $verifyCsrf = Get-HiddenInputValue -Html $verifyPage.Body -Name 'csrf_token'
                    if ([string]::IsNullOrWhiteSpace($verifyCsrf)) {
                        Add-TestResult -Name 'admin_2fa_verify_step' -Status 'FAIL' -Details 'Unable to parse CSRF token from 2FA form.'
                    } else {
                        $verifyForm = @{
                            csrf_token = $verifyCsrf
                            verification_code = ($AdminTwoFactorCode.Trim())
                            action = 'verify'
                        }
                        $verifySubmit = Invoke-CommerzaRequest -Method POST -Url $verifyUrl -Form $verifyForm -Headers @{}
                        $authenticated = ($verifySubmit.Body -match 'admin-csrf-token|Coupon Campaign Studio|dashboardSection') -or ($verifySubmit.FinalUrl -match 'admin-panel')

                        if ($authenticated) {
                            Add-TestResult -Name 'admin_2fa_verify_step' -Status 'PASS' -Details '2FA verification succeeded; authenticated session established.'
                        } else {
                            Add-TestResult -Name 'admin_2fa_verify_step' -Status 'FAIL' -Details '2FA verification did not reach an authenticated admin session.'
                        }
                    }
                }
            } else {
                Add-TestResult -Name 'admin_login_with_fallback_captcha' -Status 'FAIL' -Details "Login did not reach 2FA or admin panel. HTTP $($loginSubmit.StatusCode)."
            }
        }
    }
} else {
    Add-TestResult -Name 'admin_authentication_setup' -Status 'SKIP' -Details 'Set COMMERZA_ADMIN_TEST_SESSION_ID or COMMERZA_ADMIN_TEST_EMAIL/COMMERZA_ADMIN_TEST_PASSWORD to run authenticated abuse checks.'
}

if ($authenticated) {
    $panelUrl = "$BaseUrl/admin/frontend/admin-panel.php"
    $panelPage = Invoke-CommerzaRequest -Method GET -Url $panelUrl -Form $null -Headers @{}

    if ($panelPage.StatusCode -ne 200) {
        Add-TestResult -Name 'admin_panel_access' -Status 'FAIL' -Details "Unable to access admin panel with authenticated session. HTTP $($panelPage.StatusCode)."
    } else {
        Add-TestResult -Name 'admin_panel_access' -Status 'PASS' -Details 'Authenticated admin panel access confirmed.'

        $adminCsrf = Get-MetaContent -Html $panelPage.Body -MetaName 'admin-csrf-token'
        if ([string]::IsNullOrWhiteSpace($adminCsrf)) {
            Add-TestResult -Name 'admin_panel_csrf_meta_present' -Status 'FAIL' -Details 'Admin CSRF token meta tag is missing.'
        } else {
            Add-TestResult -Name 'admin_panel_csrf_meta_present' -Status 'PASS' -Details 'Admin CSRF token meta tag detected.'

            $couponsList = Invoke-CommerzaRequest -Method GET -Url "$BaseUrl/admin/backend/coupons_api.php?action=list" -Form $null -Headers @{}
            if ($couponsList.StatusCode -eq 200 -and $couponsList.Json -and $couponsList.Json.ok) {
                Add-TestResult -Name 'admin_coupons_list_authenticated' -Status 'PASS' -Details 'Authenticated GET list succeeded.'
            } else {
                Add-TestResult -Name 'admin_coupons_list_authenticated' -Status 'FAIL' -Details "Expected HTTP 200 and ok=true, got HTTP $($couponsList.StatusCode)."
            }

            $noCsrfPayload = @{
                action = 'save-coupon'
                code = 'AB'
                discount_type = 'fixed'
                discount_value = '0'
            }

            $missingCsrf = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/admin/backend/coupons_api.php?action=save-coupon" -Form $noCsrfPayload -Headers @{}
            if ($missingCsrf.StatusCode -eq 403) {
                Add-TestResult -Name 'admin_coupons_post_missing_csrf_rejected' -Status 'PASS' -Details 'Missing CSRF rejected with HTTP 403.'
            } else {
                Add-TestResult -Name 'admin_coupons_post_missing_csrf_rejected' -Status 'FAIL' -Details "Expected 403, got HTTP $($missingCsrf.StatusCode)."
            }

            $badCsrfPayload = @{
                action = 'save-coupon'
                csrf_token = 'invalid_csrf_token_for_test'
                code = 'AB'
                discount_type = 'fixed'
                discount_value = '0'
            }

            $invalidCsrf = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/admin/backend/coupons_api.php?action=save-coupon" -Form $badCsrfPayload -Headers @{}
            if ($invalidCsrf.StatusCode -eq 403) {
                Add-TestResult -Name 'admin_coupons_post_invalid_csrf_rejected' -Status 'PASS' -Details 'Invalid CSRF rejected with HTTP 403.'
            } else {
                Add-TestResult -Name 'admin_coupons_post_invalid_csrf_rejected' -Status 'FAIL' -Details "Expected 403, got HTTP $($invalidCsrf.StatusCode)."
            }

            $validCsrfInvalidPayload = @{
                action = 'save-coupon'
                csrf_token = $adminCsrf
                code = 'AB'
                discount_type = 'fixed'
                discount_value = '0'
            }

            $invalidPayloadResponse = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/admin/backend/coupons_api.php?action=save-coupon" -Form $validCsrfInvalidPayload -Headers @{}
            if (($invalidPayloadResponse.StatusCode -eq 422 -or $invalidPayloadResponse.StatusCode -eq 400) -and $invalidPayloadResponse.Json -and (-not $invalidPayloadResponse.Json.ok)) {
                Add-TestResult -Name 'admin_coupons_invalid_payload_rejected' -Status 'PASS' -Details 'Invalid payload blocked after CSRF validation.'
            } else {
                Add-TestResult -Name 'admin_coupons_invalid_payload_rejected' -Status 'FAIL' -Details "Expected 400/422 with ok=false, got HTTP $($invalidPayloadResponse.StatusCode)."
            }

            $securityNoCsrf = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/admin/backend/security_api.php?action=update-reset-key" -Form @{
                action = 'update-reset-key'
                resetKey = '12345678'
                confirmResetKey = '12345678'
            } -Headers @{}

            if ($securityNoCsrf.StatusCode -eq 403) {
                Add-TestResult -Name 'admin_security_api_missing_csrf_rejected' -Status 'PASS' -Details 'Security API rejected missing CSRF token.'
            } else {
                Add-TestResult -Name 'admin_security_api_missing_csrf_rejected' -Status 'FAIL' -Details "Expected 403, got HTTP $($securityNoCsrf.StatusCode)."
            }
        }
    }
}

if (-not $authenticated -and $RequireAuthenticated.IsPresent) {
    Add-TestResult -Name 'admin_authenticated_session_required' -Status 'FAIL' -Details 'Authenticated admin session was not established. Provide COMMERZA_ADMIN_TEST_SESSION_ID or COMMERZA_ADMIN_TEST_2FA_CODE for full abuse coverage.'
}

$results | Format-Table -AutoSize

$failedCount = ($results | Where-Object { $_.Status -eq 'FAIL' }).Count
$skippedCount = ($results | Where-Object { $_.Status -eq 'SKIP' }).Count

Write-Host "`nSummary: $($results.Count) tests, $failedCount failed, $skippedCount skipped."

if ($failedCount -gt 0) {
    exit 1
}

exit 0
