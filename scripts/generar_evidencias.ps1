$ErrorActionPreference = 'Stop'
$projectEvidencePath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$phpEvidencePath = 'C:\xampp\php\php.exe'
$evidenceDirectory = Join-Path $projectEvidencePath 'docs\evidencias'
New-Item -ItemType Directory -Path $evidenceDirectory -Force | Out-Null
$suitesEvidence = @('pruebas_alerta_guardado.php','pruebas_telegram.php','pruebas_destino.php','pruebas_multiagentes.php','pruebas_gemini.php','pruebas_asistente_integral.php','pruebas_alertas_operativas.php','pruebas_cinco_agentes.php','pruebas_ranking_clientes.php','pruebas_predicciones_individuales.php','pruebas_dashboard_predicciones.php','pruebas_centro_operaciones.php','pruebas_gestion_avanzada.php','pruebas_control_ml.php','pruebas_proveedores_alertas.php')
$linesEvidence = [Collections.Generic.List[string]]::new()
$linesEvidence.Add('TechStore - evidencia automatizada local')
$linesEvidence.Add('Fecha UTC: ' + [DateTime]::UtcNow.ToString('o'))
$linesEvidence.Add('Sin API externa: proveedores simulados y bases SQLite temporales.')
$failedEvidence = $false
foreach ($suiteEvidence in $suitesEvidence) {
    $linesEvidence.Add('')
    $linesEvidence.Add('Suite: scripts/' + $suiteEvidence)
    $outputEvidence = & $phpEvidencePath (Join-Path $PSScriptRoot $suiteEvidence) 2>&1
    $codeEvidence = $LASTEXITCODE
    foreach ($lineEvidence in $outputEvidence) { $linesEvidence.Add([string]$lineEvidence) }
    $linesEvidence.Add('Codigo de salida: ' + $codeEvidence)
    if ($codeEvidence -ne 0) { $failedEvidence = $true }
}
$evidenceFile = Join-Path $evidenceDirectory 'pruebas-locales.txt'
[IO.File]::WriteAllLines($evidenceFile,$linesEvidence,(New-Object Text.UTF8Encoding($false)))
Write-Output ('Evidencia guardada en ' + $evidenceFile)
if ($failedEvidence) { exit 1 }
