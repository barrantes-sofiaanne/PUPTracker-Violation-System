param(
    [Parameter(Mandatory = $true)]
    [string]$Url
)

$ErrorActionPreference = "Stop"

function Get-HeaderValue {
    param(
        [object]$Headers,
        [string]$Name
    )

    foreach ($key in $Headers.Keys) {
        if ($key.ToString().ToLowerInvariant() -eq $Name.ToLowerInvariant()) {
            return [string]$Headers[$key]
        }
    }

    return ""
}

try {
    $response = Invoke-WebRequest -Uri $Url -Method GET -MaximumRedirection 5 -UseBasicParsing
} catch {
    Write-Host "FAIL: Unable to reach URL: $Url" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 2
}

$headers = $response.Headers

$xFrame = Get-HeaderValue -Headers $headers -Name "X-Frame-Options"
$xContentType = Get-HeaderValue -Headers $headers -Name "X-Content-Type-Options"
$csp = Get-HeaderValue -Headers $headers -Name "Content-Security-Policy"
$hsts = Get-HeaderValue -Headers $headers -Name "Strict-Transport-Security"

$checks = @()

$checks += [pscustomobject]@{
    Name = "X-Frame-Options"
    Expected = "SAMEORIGIN"
    Actual = $xFrame
    Passed = ($xFrame -eq "SAMEORIGIN")
}

$checks += [pscustomobject]@{
    Name = "X-Content-Type-Options"
    Expected = "nosniff"
    Actual = $xContentType
    Passed = ($xContentType.ToLowerInvariant() -eq "nosniff")
}

$checks += [pscustomobject]@{
    Name = "Content-Security-Policy"
    Expected = "Present and non-empty"
    Actual = $csp
    Passed = (-not [string]::IsNullOrWhiteSpace($csp))
}

$uri = [System.Uri]$Url
if ($uri.Scheme -eq "https") {
    $checks += [pscustomobject]@{
        Name = "Strict-Transport-Security"
        Expected = "Present on HTTPS"
        Actual = $hsts
        Passed = (-not [string]::IsNullOrWhiteSpace($hsts))
    }
} else {
    $checks += [pscustomobject]@{
        Name = "Strict-Transport-Security"
        Expected = "Not required on HTTP"
        Actual = $hsts
        Passed = $true
    }
}

Write-Host "Security Header Check for $Url"
Write-Host ""

$allPassed = $true
foreach ($check in $checks) {
    if ($check.Passed) {
        Write-Host ("PASS: " + $check.Name) -ForegroundColor Green
    } else {
        Write-Host ("FAIL: " + $check.Name) -ForegroundColor Red
        $allPassed = $false
    }

    Write-Host ("  Expected: " + $check.Expected)
    Write-Host ("  Actual:   " + ($(if ([string]::IsNullOrWhiteSpace($check.Actual)) { "<missing>" } else { $check.Actual })))
}

Write-Host ""
if ($allPassed) {
    Write-Host "OVERALL: PASS" -ForegroundColor Green
    exit 0
}

Write-Host "OVERALL: FAIL" -ForegroundColor Red
exit 1
