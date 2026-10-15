[CmdletBinding()]
param([ValidateRange(5,1440)][int]$IntervaloMinutos = 15)
$ErrorActionPreference = 'Stop'
$taskName = 'TechStore - Alertas de inventario'
$projectPath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$runnerPath = Join-Path $PSScriptRoot 'revision_automatica.ps1'
if (-not (Test-Path -LiteralPath 'C:\xampp\php\php.exe')) { throw 'No se encontró PHP de XAMPP.' }
if (-not (Test-Path -LiteralPath $runnerPath)) { throw 'No se encontró el programa de revisión.' }
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing -and $existing.Description -notlike 'TechStore: revisión local*') { throw 'Ya existe una tarea con este nombre y otro propósito. No se reemplazó.' }
$accountSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
$action = New-ScheduledTaskAction -Execute "$env:WINDIR\System32\WindowsPowerShell\v1.0\powershell.exe" -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -File "' + $runnerPath + '"') -WorkingDirectory $projectPath
$repeating = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes $IntervaloMinutos)
$atLogon = New-ScheduledTaskTrigger -AtLogOn -User $accountSid
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 3) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$principal = New-ScheduledTaskPrincipal -UserId $accountSid -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger @($repeating,$atLogon) -Settings $settings -Principal $principal -Description 'TechStore: revisión local de inventario, análisis y envío de novedades a Telegram. Requiere sesión iniciada y MySQL disponible.' -Force | Out-Null
$directory = Join-Path $projectPath 'storage\multiagentes'
if (-not (Test-Path -LiteralPath $directory)) { New-Item -ItemType Directory -Path $directory -Force | Out-Null }
$metadata = @{ nombre=$taskName; intervalo_minutos=$IntervaloMinutos; instalada_utc=[DateTime]::UtcNow.ToString('o'); requiere_sesion=$true }
[IO.File]::WriteAllText((Join-Path $directory 'automatizacion.json'), ($metadata | ConvertTo-Json), (New-Object Text.UTF8Encoding($false)))
Write-Output "Tarea instalada: $taskName. Intervalo: $IntervaloMinutos minutos."
