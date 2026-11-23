param([string]$PhpPath='C:\xampp\php\php-win.exe',[switch]$Unattended)
$ErrorActionPreference='Stop'
$taskRoot=Split-Path -Parent $PSScriptRoot
$runner=Join-Path $PSScriptRoot 'backup-runner.php'
if (!(Test-Path -LiteralPath $PhpPath) -or !(Test-Path -LiteralPath $runner)) { throw 'PHP or backup runner missing.' }
$taskAction=New-ScheduledTaskAction -Execute $PhpPath -Argument ('"'+$runner+'"') -WorkingDirectory $taskRoot
$taskTrigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5)
$taskSettings=New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 1) -MultipleInstances IgnoreNew
$taskName='PikolCastle-Backup-'+(Split-Path -Leaf $taskRoot)
$existingTask=Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if($existingTask -and ($existingTask.Actions.Arguments -notlike ('*'+$runner+'*'))){throw 'An unrelated task already uses this task name.'}
$logonType=if($Unattended){'S4U'}else{'Interactive'}
$taskPrincipal=New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType $logonType -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $taskAction -Trigger $taskTrigger -Settings $taskSettings -Principal $taskPrincipal -Force -Description 'Runs protected Pikol Castle backups every five minutes.' -ErrorAction Stop | Out-Null
if(!$Unattended){Write-Output 'This task runs while its Windows user is signed in. Use -Unattended from an administrator shell for signed-out execution.'}
Write-Output "Registered $taskName. Check Task Scheduler permissions and unattended execution; the app reports operational status only after a recent runner heartbeat."
