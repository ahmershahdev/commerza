param(
    [string]$BaseUrl = "http://localhost/commerza",
    [int]$TimeoutSec = 30
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
    }
}

function Invoke-CommerzaRequest {
    param(
        [ValidateSet('GET', 'POST')]
        [string]$Method,
        [string]$Url,
        [hashtable]$Form
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

    if ($Method -eq 'POST') {
        $requestParams.Body = $Form
        $requestParams.ContentType = 'application/x-www-form-urlencoded'
    }

    $statusCode = 0
    $body = ''
    if ($script:SupportsSkipHttpErrorCheck) {
        $response = Invoke-WebRequest @requestParams -SkipHttpErrorCheck
        $statusCode = [int]$response.StatusCode
        $body = [string]$response.Content
    } else {
        try {
            $response = Invoke-WebRequest @requestParams
            $statusCode = [int]$response.StatusCode
            $body = [string]$response.Content
        } catch {
            $errorResponse = Get-HttpErrorResponse -ErrorRecord $_
            $statusCode = [int]$errorResponse.StatusCode
            $body = [string]$errorResponse.Body
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
    }
}

# Warm up session and load CSRF from cart status endpoint.
[void](Invoke-CommerzaRequest -Method GET -Url "$BaseUrl/index.php")
$cartStatus = Invoke-CommerzaRequest -Method GET -Url "$BaseUrl/backend/api/cart_api.php?action=status"
$csrfToken = ''
if ($cartStatus.Json -and $cartStatus.Json.csrf_token) {
    $csrfToken = [string]$cartStatus.Json.csrf_token
}

# Resolve one valid product id for reviews/viewers tests.
$firstProductId = 0
$productsPayload = Invoke-CommerzaRequest -Method GET -Url "$BaseUrl/backend/api/products_api.php?action=sections"
if ($productsPayload.Json -and $productsPayload.Json.sections) {
    foreach ($section in $productsPayload.Json.sections) {
        if ($section.products -and $section.products.Count -gt 0) {
            $firstProductId = [int]$section.products[0].id
            break
        }
    }
}
if ($firstProductId -le 0) {
    $firstProductId = 1
}

# 1) Checkout form POST without CSRF should be forbidden.
$checkoutNoCsrf = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/cart.php" -Form @{
    action = 'place_order'
}
if ($checkoutNoCsrf.StatusCode -eq 403) {
    Add-TestResult -Name 'checkout_missing_csrf_rejected' -Status 'PASS' -Details 'cart.php rejected missing CSRF with HTTP 403.'
} else {
    Add-TestResult -Name 'checkout_missing_csrf_rejected' -Status 'FAIL' -Details "Expected 403 Forbidden, got HTTP $($checkoutNoCsrf.StatusCode)."
}

# 2) Cart API add without CSRF should be forbidden.
$cartAddNoCsrf = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/backend/api/cart_api.php" -Form @{
    action = 'add'
    product_id = $firstProductId
    quantity = 1
}
if ($cartAddNoCsrf.StatusCode -eq 403) {
    Add-TestResult -Name 'cart_add_missing_csrf_rejected' -Status 'PASS' -Details 'cart_api.php rejected missing CSRF with HTTP 403.'
} else {
    Add-TestResult -Name 'cart_add_missing_csrf_rejected' -Status 'FAIL' -Details "Expected 403, got HTTP $($cartAddNoCsrf.StatusCode)."
}

# 3) Coupon endpoint should reject invalid code even with CSRF.
if ([string]::IsNullOrWhiteSpace($csrfToken)) {
    Add-TestResult -Name 'coupon_invalid_code_rejected' -Status 'SKIP' -Details 'No CSRF token returned by cart status endpoint.'
} else {
    $couponInvalid = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/backend/api/cart_api.php" -Form @{
        action = 'apply_coupon'
        csrf_token = $csrfToken
        code = 'INVALID_COUPON_FOR_SMOKE_TEST'
    }

    if ($couponInvalid.StatusCode -eq 422 -and $couponInvalid.Json -and -not $couponInvalid.Json.ok) {
        Add-TestResult -Name 'coupon_invalid_code_rejected' -Status 'PASS' -Details 'Invalid coupon was rejected with validation status.'
    } else {
        Add-TestResult -Name 'coupon_invalid_code_rejected' -Status 'FAIL' -Details "Expected 422 with ok=false, got HTTP $($couponInvalid.StatusCode)."
    }
}

# 4) Viewers count should reject invalid product id.
$viewersInvalidProduct = Invoke-CommerzaRequest -Method GET -Url "$BaseUrl/backend/api/viewers_api.php?action=count&product_id=0"
if ($viewersInvalidProduct.StatusCode -eq 422) {
    Add-TestResult -Name 'viewers_count_invalid_product_rejected' -Status 'PASS' -Details 'viewers_api count rejected invalid product id.'
} else {
    Add-TestResult -Name 'viewers_count_invalid_product_rejected' -Status 'FAIL' -Details "Expected 422, got HTTP $($viewersInvalidProduct.StatusCode)."
}

# 5) Viewers heartbeat without CSRF should be forbidden.
$viewersHeartbeatNoCsrf = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/backend/api/viewers_api.php" -Form @{
    action = 'heartbeat'
    product_id = $firstProductId
}
if ($viewersHeartbeatNoCsrf.StatusCode -eq 403) {
    Add-TestResult -Name 'viewers_heartbeat_missing_csrf_rejected' -Status 'PASS' -Details 'viewers_api heartbeat rejected missing CSRF.'
} else {
    Add-TestResult -Name 'viewers_heartbeat_missing_csrf_rejected' -Status 'FAIL' -Details "Expected 403, got HTTP $($viewersHeartbeatNoCsrf.StatusCode)."
}

# 6) Reviews submit without CSRF should be forbidden (valid product id required).
$reviewsNoCsrf = Invoke-CommerzaRequest -Method POST -Url "$BaseUrl/backend/api/reviews_api.php" -Form @{
    action = 'submit'
    product_id = $firstProductId
    rating = 5
    review_text = 'Security smoke test review body to validate CSRF rejection.'
}
if ($reviewsNoCsrf.StatusCode -eq 403) {
    Add-TestResult -Name 'reviews_submit_missing_csrf_rejected' -Status 'PASS' -Details 'reviews_api rejected missing CSRF on submit action.'
} else {
    Add-TestResult -Name 'reviews_submit_missing_csrf_rejected' -Status 'FAIL' -Details "Expected 403, got HTTP $($reviewsNoCsrf.StatusCode)."
}

# 7) Admin coupons endpoint must block unauthenticated access.
$adminCouponsUnauth = Invoke-CommerzaRequest -Method GET -Url "$BaseUrl/admin/backend/coupons_api.php?action=list"
if ($adminCouponsUnauth.StatusCode -ge 401) {
    Add-TestResult -Name 'admin_coupons_unauthenticated_blocked' -Status 'PASS' -Details 'Admin coupons API blocked unauthenticated request.'
} else {
    Add-TestResult -Name 'admin_coupons_unauthenticated_blocked' -Status 'FAIL' -Details "Expected 401+ status, got HTTP $($adminCouponsUnauth.StatusCode)."
}

# 8) Admin reviews endpoint must block unauthenticated access.
$adminReviewsUnauth = Invoke-CommerzaRequest -Method GET -Url "$BaseUrl/admin/backend/reviews_api.php?action=list"
if ($adminReviewsUnauth.StatusCode -ge 401) {
    Add-TestResult -Name 'admin_reviews_unauthenticated_blocked' -Status 'PASS' -Details 'Admin reviews API blocked unauthenticated request.'
} else {
    Add-TestResult -Name 'admin_reviews_unauthenticated_blocked' -Status 'FAIL' -Details "Expected 401+ status, got HTTP $($adminReviewsUnauth.StatusCode)."
}

$results | Format-Table -AutoSize

$failedCount = ($results | Where-Object { $_.Status -eq 'FAIL' }).Count
$skippedCount = ($results | Where-Object { $_.Status -eq 'SKIP' }).Count

Write-Host "`nSummary: $($results.Count) tests, $failedCount failed, $skippedCount skipped."

if ($failedCount -gt 0) {
    exit 1
}

exit 0
