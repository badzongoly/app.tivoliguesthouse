param([string]$BaseUrl = 'http://localhost/app.tivoliguesthouse')
$ErrorActionPreference = 'Stop'
$uri = [Uri]$BaseUrl
if (-not $uri.IsLoopback -or $uri.Scheme -ne 'http') { throw 'Only local HTTP smoke checks are permitted' }
Add-Type -AssemblyName System.Net.Http
$handler = [Net.Http.HttpClientHandler]::new()
$handler.AllowAutoRedirect = $false
$client = [Net.Http.HttpClient]::new($handler)
$cases = @{
  '/' = 302
  '/auth/login.php' = 200
  '/dashboard.php' = 302
  '/finance.php' = 302
  '/finance-evidence.php' = 302
  '/storage/private/finance-evidence/private.enc' = 403
  '/assets/css/finance.css' = 200
  '/assets/js/finance.js' = 200
  '/staff.php' = 302
  '/hr-settings.php' = 302
  '/hr-document.php' = 302
  '/config/hr.local.php' = 403
  '/storage/hr-documents/private.enc' = 403
  '/assets/css/hr.css' = 200
  '/assets/js/hr.js' = 200
  '/assets/images/Tivoli.png' = 200
  '/img/Tivoli.png' = 200
  '/config/database.local.php' = 403
  '/bootstrap/app.php' = 403
  '/src/Security/require_login.php' = 403
  '/templates/partials/sidebar.php' = 403
  '/.git/config' = 403
  '/pssvkote_tivoli.sql' = 403
  '/.local-setup/tivoli-before-import.sql' = 403
}
try {
  foreach ($entry in $cases.GetEnumerator()) {
    $response = $client.GetAsync($BaseUrl.TrimEnd('/') + $entry.Key).GetAwaiter().GetResult()
    $body = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if ([int]$response.StatusCode -ne $entry.Value) { throw "Unexpected status for $($entry.Key): $([int]$response.StatusCode)" }
    if ($entry.Value -eq 302 -and [string]$response.Headers.Location -ne 'auth/login.php') { throw 'Unexpected login redirect' }
    if ($body -match 'Fatal error|Parse error|DB connection failed|Warning:') { throw "PHP error at $($entry.Key)" }
    if ($entry.Key -eq '/auth/login.php' -and $body -notmatch '<form') { throw 'Login form missing' }
    $response.Dispose()
    Write-Output "PASS $($entry.Key)"
  }
} finally { $client.Dispose(); $handler.Dispose() }