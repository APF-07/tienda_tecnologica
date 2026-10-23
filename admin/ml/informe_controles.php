<?php
require_once '../../includes/verificar_admin.php';
header('Cache-Control: no-store');
$metodo=(string)file_get_contents(__DIR__.'/../../docs/CONTROLES_ML_NEGOCIO.md');
$evidencia=is_file(__DIR__.'/../../docs/evidencias/controles-negocio.md')?(string)file_get_contents(__DIR__.'/../../docs/evidencias/controles-negocio.md'):'Sin evidencia exportada. Ejecuta scripts/evidencia_controles_negocio.php.';
if(isset($_GET['descargar'])){
    header('Content-Type: text/markdown; charset=UTF-8');header('Content-Disposition: attachment; filename="Informe-cinco-controles-ML.md"');echo $metodo."\n\n".$evidencia;exit;
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Informe de controles ML</title><style>body{background:#0c1828;color:#dceafb;font:15px/1.7 system-ui;max-width:1100px;margin:auto;padding:30px}pre{white-space:pre-wrap;overflow-wrap:anywhere}a{color:#7de3eb;display:inline-block;margin:0 20px 25px 0}</style></head><body><h1>Informe y evidencia de cinco controles ML</h1><a href="controles.php">← Volver a controles</a><a href="?descargar=1">Descargar informe Markdown</a><h2>Resultados medidos</h2><pre><?= e($evidencia) ?></pre><h2>Metodología y comparación</h2><pre><?= e($metodo) ?></pre></body></html>
