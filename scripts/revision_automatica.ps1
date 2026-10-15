$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = New-Object Text.UTF8Encoding($false)
$projectPath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$phpPath = 'C:\xampp\php\php.exe'
$entryPath = Join-Path $projectPath 'scripts\ejecutar_agentes.php'
$stateDirectory = Join-Path $projectPath 'storage\multiagentes'
$exitStatus = 1
try {
    if (-not (Test-Path -LiteralPath $stateDirectory)) { New-Item -ItemType Directory -Path $stateDirectory -Force | Out-Null }
    $executionOutput = (& $phpPath $entryPath '--enviar-telegram' 2>&1 | Out-String).Trim()
    $exitStatus = $LASTEXITCODE
    $entry = [ordered]@{ fecha_utc = [DateTime]::UtcNow.ToString('o'); codigo = $exitStatus; resultado = $executionOutput }
    [IO.File]::WriteAllText((Join-Path $stateDirectory 'ultima_revision_automatica.json'), ($entry | ConvertTo-Json), (New-Object Text.UTF8Encoding($false)))
} catch {
    if (Test-Path -LiteralPath $stateDirectory) {
        $entry = @{ fecha_utc = [DateTime]::UtcNow.ToString('o'); codigo = 1; resultado = 'La revisión automática no se completó. Comprueba MySQL y la configuración local.' }
        [IO.File]::WriteAllText((Join-Path $stateDirectory 'ultima_revision_automatica.json'), ($entry | ConvertTo-Json), (New-Object Text.UTF8Encoding($false)))
    }
}
exit $exitStatus
