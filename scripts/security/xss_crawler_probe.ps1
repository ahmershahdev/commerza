param(
    [string]$BaseUrl = "http://localhost/commerza",
    [int]$MaxPages = 80,
    [int]$TimeoutSec = 25
)

$ErrorActionPreference = 'Stop'

$BaseUrl = $BaseUrl.TrimEnd('/')
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$script:SupportsSkipHttpErrorCheck = (Get-Command Invoke-WebRequest).Parameters.ContainsKey('SkipHttpErrorCheck')
$script:SupportsUseBasicParsing = (Get-Command Invoke-WebRequest).Parameters.ContainsKey('UseBasicParsing')

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

function Invoke-CrawlRequest {
    param(
        [string]$Url
    )

    $requestParams = @{
        Uri = $Url
        Method = 'GET'
        WebSession = $session
        TimeoutSec = $TimeoutSec
    }

    if ($script:SupportsUseBasicParsing) {
        $requestParams.UseBasicParsing = $true
    }

    $statusCode = 0
    $body = ''
    $headers = @{}
    $finalUrl = ''

    if ($script:SupportsSkipHttpErrorCheck) {
        $response = Invoke-WebRequest @requestParams -SkipHttpErrorCheck
        $statusCode = [int]$response.StatusCode
        $body = [string]$response.Content
        $finalUrl = [string]$response.BaseResponse.ResponseUri.AbsoluteUri
        if ($response.Headers) {
            foreach ($key in $response.Headers.Keys) {
                $headers[$key] = [string]$response.Headers[$key]
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
                    $headers[$key] = [string]$response.Headers[$key]
                }
            }
        } catch {
            $errorResponse = Get-HttpErrorResponse -ErrorRecord $_
            $statusCode = [int]$errorResponse.StatusCode
            $body = [string]$errorResponse.Body
            $headers = @{}
            $finalUrl = ''
        }
    }

    return [pscustomobject]@{
        StatusCode = $statusCode
        Body = $body
        Headers = $headers
        FinalUrl = $finalUrl
    }
}

function Get-ContentType {
    param([hashtable]$Headers)

    if (-not $Headers) {
        return ''
    }

    foreach ($key in @('Content-Type', 'content-type')) {
        if ($Headers.ContainsKey($key)) {
            return ([string]$Headers[$key]).ToLowerInvariant()
        }
    }

    return ''
}

function Is-HtmlResponse {
    param([hashtable]$Headers)

    $contentType = Get-ContentType -Headers $Headers
    if ($contentType -eq '') {
        return $true
    }

    return $contentType.Contains('text/html')
}

function Normalize-SameOriginUrl {
    param(
        [string]$Candidate,
        [Uri]$CurrentUri,
        [Uri]$RootUri
    )

    if ([string]::IsNullOrWhiteSpace($Candidate)) {
        return ''
    }

    $raw = $Candidate.Trim()
    if ($raw -match '^(javascript:|mailto:|tel:|#)') {
        return ''
    }

    try {
        $resolved = [Uri]::new($CurrentUri, $raw)
    } catch {
        return ''
    }

    if ($resolved.Host -ne $RootUri.Host) {
        return ''
    }

    $rootPath = $RootUri.AbsolutePath.TrimEnd('/')
    $resolvedPath = $resolved.AbsolutePath
    if ($rootPath -ne '' -and $rootPath -ne '/' -and -not $resolvedPath.StartsWith($rootPath, [System.StringComparison]::OrdinalIgnoreCase)) {
        return ''
    }

    $cleanUriBuilder = [UriBuilder]::new($resolved)
    $cleanUriBuilder.Fragment = ''

    return $cleanUriBuilder.Uri.AbsoluteUri
}

function Get-LinksFromHtml {
    param(
        [string]$Html,
        [Uri]$CurrentUri,
        [Uri]$RootUri
    )

    $links = New-Object System.Collections.Generic.List[string]
    if ([string]::IsNullOrWhiteSpace($Html)) {
        return $links
    }

    $matches = [regex]::Matches($Html, 'href\s*=\s*["'']([^"'']+)["'']', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    foreach ($match in $matches) {
        if (-not $match.Success) {
            continue
        }

        $candidate = $match.Groups[1].Value
        $normalized = Normalize-SameOriginUrl -Candidate $candidate -CurrentUri $CurrentUri -RootUri $RootUri
        if ($normalized -ne '') {
            $links.Add($normalized)
        }
    }

    return $links
}

$rootUri = [Uri]$BaseUrl
$visited = New-Object System.Collections.Generic.HashSet[string]([System.StringComparer]::OrdinalIgnoreCase)
$queue = New-Object System.Collections.Generic.Queue[string]
$htmlPages = New-Object System.Collections.Generic.List[string]

$seedUrls = @(
    "$BaseUrl/index.php",
    "$BaseUrl/products.php",
    "$BaseUrl/login.php",
    "$BaseUrl/signup.php",
    "$BaseUrl/contact.php",
    "$BaseUrl/cart.php",
    "$BaseUrl/account.php",
    "$BaseUrl/admin/frontend/admin-login.php"
)

foreach ($seed in $seedUrls) {
    if (-not [string]::IsNullOrWhiteSpace($seed)) {
        $queue.Enqueue($seed)
    }
}

while ($queue.Count -gt 0 -and $visited.Count -lt $MaxPages) {
    $current = $queue.Dequeue()
    if ($visited.Contains($current)) {
        continue
    }

    $visited.Add($current) | Out-Null

    $response = Invoke-CrawlRequest -Url $current
    if ($response.StatusCode -lt 200 -or $response.StatusCode -ge 400) {
        continue
    }

    if (-not (Is-HtmlResponse -Headers $response.Headers)) {
        continue
    }

    $htmlPages.Add($current)

    $currentUri = [Uri]$current
    $links = Get-LinksFromHtml -Html $response.Body -CurrentUri $currentUri -RootUri $rootUri
    foreach ($link in $links) {
        if (-not $visited.Contains($link)) {
            $queue.Enqueue($link)
        }
    }
}

$payload = '"><svg/onload=alert(''cxss'')>'
$probeParams = @('xss_probe', 'q', 'query', 'search', 's', 'keyword')
$findings = @()

foreach ($pageUrl in $htmlPages) {
    foreach ($param in $probeParams) {
        $separator = if ($pageUrl.Contains('?')) { '&' } else { '?' }
        $probeUrl = "$pageUrl$separator$param=$([Uri]::EscapeDataString($payload))"
        $probeResponse = Invoke-CrawlRequest -Url $probeUrl

        if ($probeResponse.StatusCode -lt 200 -or $probeResponse.StatusCode -ge 400) {
            continue
        }

        if (-not (Is-HtmlResponse -Headers $probeResponse.Headers)) {
            continue
        }

        if ($probeResponse.Body -like "*$payload*") {
            $findings += [pscustomobject]@{
                Url = $pageUrl
                Parameter = $param
                ProbeUrl = $probeUrl
                Severity = 'HIGH'
                Detail = 'Raw payload reflected in HTML response body.'
            }
            break
        }
    }
}

Write-Host "Crawled pages: $($htmlPages.Count)"

if ($findings.Count -gt 0) {
    $findings | Format-Table -AutoSize
    Write-Host "`nSummary: $($findings.Count) potential reflected XSS findings detected."
    exit 1
}

Write-Host "No raw reflected XSS payloads detected by crawler probe."
exit 0
