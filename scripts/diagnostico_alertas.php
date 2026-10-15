<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../config/conexion.php';
require __DIR__.'/../includes/multiagentes.php';
$anterior=estado_multiagentes();
$actual=(new CoordinadorTienda())->ejecutar($conexion,$anterior['notificaciones']['activas']??[],null,$anterior['notificaciones']['recuperadas']??[]);
echo 'Última revisión guardada: '.($anterior['fecha']??'ninguna').PHP_EOL;
echo 'Cambios de stock bajo pendientes: '.$actual['notificaciones']['nuevas'].PHP_EOL;
echo 'Recuperaciones pendientes de detectar: '.$actual['notificaciones']['nuevas_recuperadas'].PHP_EOL;
foreach($actual['notificaciones']['activas'] as $id=>$a){
    $prev=$anterior['notificaciones']['activas'][$id]??null;
    if(!$prev||$prev['firma']!==$a['firma'])echo $a['producto']['codigo'].' · '.$a['producto']['nombre'].' · stock actual '.$a['producto']['stock'].PHP_EOL;
}
foreach($actual['notificaciones']['recuperadas'] as $id=>$a){
    if(!isset($anterior['notificaciones']['recuperadas'][$id]))echo $a['producto']['codigo'].' · recuperado de '.$a['stock_anterior'].' a '.$a['producto']['stock'].PHP_EOL;
}
echo 'Diagnóstico de solo lectura: no envía mensajes ni guarda revisiones.'.PHP_EOL;
