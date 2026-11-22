param([switch]$Stop)
$ErrorActionPreference='Stop'
$rootPath=[IO.Path]::GetFullPath((Split-Path $PSScriptRoot))
$dataPath=Join-Path $rootPath '.local-setup/auth-test-mysql'
$binPath='C:\xampp\mysql\bin'
$phpPath='C:\xampp\php\php.exe'
New-Item -ItemType Directory -Path (Join-Path $rootPath '.local-setup') -Force | Out-Null
if($Stop){
    $pidPath=Join-Path $rootPath '.local-setup/access-http.pid'
    if(Test-Path -LiteralPath $pidPath){
        $testPid=[int][IO.File]::ReadAllText($pidPath)
        $process=Get-CimInstance Win32_Process -Filter "ProcessId=$testPid" -ErrorAction SilentlyContinue
        if($process -and $process.Name -eq 'php.exe' -and $process.CommandLine.Contains('127.0.0.1:8099') -and $process.CommandLine.Contains($rootPath.Replace('\','/')+'/public')){Stop-Process -Id $testPid -ErrorAction Stop}
    }
    & $phpPath (Join-Path $rootPath 'scripts/provision-access-test.php') --confirmed-test-instance
    if($LASTEXITCODE -ne 0){throw 'Refusing shutdown of an unverified database instance'}
    & (Join-Path $binPath 'mysqladmin.exe') --no-defaults --connect-timeout=3 --protocol=TCP --host=127.0.0.1 --port=3307 --user=root --skip-password shutdown
    if($LASTEXITCODE -ne 0){throw 'Test database shutdown failed'}
    Write-Output 'Dedicated test services stopped.';exit
}
if(-not (Test-Path -LiteralPath $dataPath)){
    & (Join-Path $binPath 'mysql_install_db.exe') "--datadir=$dataPath" --port=3307
    if($LASTEXITCODE -ne 0){throw 'Test instance initialization failed'}
}
$probe=& (Join-Path $binPath 'mysqladmin.exe') --no-defaults --connect-timeout=3 --protocol=TCP --host=127.0.0.1 --port=3307 --user=root --skip-password ping 2>$null
if($LASTEXITCODE -ne 0){
    Start-Process -FilePath (Join-Path $binPath 'mysqld.exe') -ArgumentList @('--no-defaults','--basedir=C:/xampp/mysql',('--datadir='+$dataPath.Replace('\','/')),'--port=3307','--bind-address=127.0.0.1','--skip-name-resolve','--max-connections=15','--innodb-buffer-pool-size=32M') -WindowStyle Hidden | Out-Null
    $ready=$false
    for($i=0;$i -lt 50;$i++){Start-Sleep -Milliseconds 200;$probe=& (Join-Path $binPath 'mysqladmin.exe') --no-defaults --connect-timeout=3 --protocol=TCP --host=127.0.0.1 --port=3307 --user=root --skip-password ping 2>$null;if($LASTEXITCODE -eq 0){$ready=$true;break}}
    if(-not $ready){throw 'Test database did not become ready'}
}
& $phpPath (Join-Path $rootPath 'scripts/provision-access-test.php') --confirmed-test-instance
if($LASTEXITCODE -ne 0){throw 'Synthetic database verification/provisioning failed'}
$env:TIVOLI_TEST_MODE='1'
try{
    & $phpPath (Join-Path $rootPath 'scripts/setup-access.php') --confirmed-local
    if($LASTEXITCODE -ne 0){throw 'Test schema installation failed'}
    if(Test-Path -LiteralPath (Join-Path $rootPath 'scripts/setup-expenses.php')){
        & $phpPath (Join-Path $rootPath 'scripts/setup-expenses.php') --confirmed-local
        if($LASTEXITCODE -ne 0){throw 'Synthetic expense schema setup failed'}
    }
    $httpReady=$false
    try{$response=Invoke-WebRequest -Uri http://127.0.0.1:8099/auth/login.php -MaximumRedirection 0;$httpReady=$response.Headers['X-Tivoli-Test-Mode'] -eq 'synthetic'}catch{}
    if(-not $httpReady){
        $server=Start-Process -FilePath $phpPath -ArgumentList @('-S','127.0.0.1:8099','-t',($rootPath.Replace('\','/')+'/public')) -WindowStyle Hidden -RedirectStandardError (Join-Path $rootPath '.local-setup/auth-http-test.log') -PassThru
        [IO.File]::WriteAllText((Join-Path $rootPath '.local-setup/access-http.pid'),[string]$server.Id)
        Start-Sleep -Milliseconds 500
        $response=Invoke-WebRequest -Uri http://127.0.0.1:8099/auth/login.php -MaximumRedirection 0
        if($response.Headers['X-Tivoli-Test-Mode'] -ne 'synthetic'){throw 'Refusing unverified HTTP test service'}
    }
}finally{Remove-Item Env:TIVOLI_TEST_MODE -ErrorAction SilentlyContinue}
Write-Output 'Synthetic services verified: database 127.0.0.1:3307; HTTP 127.0.0.1:8099.'