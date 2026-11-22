[CmdletBinding(SupportsShouldProcess=$true)]
param([switch]$Install)
$ErrorActionPreference = 'Stop'
$appRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..')).TrimEnd('\')
if ($appRoot -ine 'C:\xampp\htdocs\app.tivoliguesthouse') { throw 'Unexpected local application root.' }
$phpPath = 'C:\xampp\php\php.exe'
$workerPath = Join-Path $appRoot 'scripts\reminders-worker.php'
if (!(Test-Path -LiteralPath $phpPath -PathType Leaf) -or !(Test-Path -LiteralPath $workerPath -PathType Leaf)) { throw 'The reviewed local worker is missing.' }
$taskName = 'Tivoli-Local-Reminders-5min'
$taskArgs = '"' + $workerPath + '" --local'
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing) {
    if ($existing.Actions.Count -ne 1 -or $existing.Actions[0].Execute -ine $phpPath -or $existing.Actions[0].Arguments -cne $taskArgs) { throw 'A different task already uses this name; it was not changed.' }
    Write-Output ('Existing local reminder task: ' + $existing.State)
    return
}
if (!$Install) { Write-Output 'Local reminder task is not installed. Use -Install after reviewing this local-only definition.'; return }
if ($PSCmdlet.ShouldProcess($taskName, 'Register reviewed local-only reminder worker every five minutes')) {
    $identity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
    $action = New-ScheduledTaskAction -Execute $phpPath -Argument $taskArgs -WorkingDirectory $appRoot
    $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5)
    $principal = New-ScheduledTaskPrincipal -UserId $identity -LogonType Interactive -RunLevel Limited
    $settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 4) -StartWhenAvailable -Hidden -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Local Tivoli reminders only. Uses --local exact-target guard; no financial source command or real external delivery.' | Out-Null
    Write-Output 'Registered local reminder task every five minutes while this Windows user is signed in.'
}
