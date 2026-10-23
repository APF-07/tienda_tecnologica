<?php
require_once '../../includes/verificar_admin.php';
session_write_close();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$lock = fopen(__DIR__.'/../../ml/entrenamiento.lock', 'c');
$ocupado = $lock && !flock($lock, LOCK_EX | LOCK_NB);
if ($lock) { if (!$ocupado) flock($lock, LOCK_UN); fclose($lock); }
$etapa = 'Esperando inicio del proceso';
if ($ocupado) {
    $log = @file_get_contents(__DIR__.'/../../ml/entrenamiento.log');
    foreach (['productos'=>'Analizando inventario', 'clientes'=>'Analizando clientes', 'ventas'=>'Comparando modelos de ventas', 'pedidos'=>'Comparando modelos de pedidos'] as $clave=>$texto) {
        if (str_contains($log ?: '', $clave.':')) $etapa = $texto;
    }
    foreach (['ventas','inventario','anomalias','recomendaciones','clasificacion'] as $clave) {
        if(str_contains($log ?: '', 'Control de negocio '.$clave.':'))$etapa='Evaluando control de negocio: '.$clave;
    }
}
echo json_encode(['ocupado'=>(bool)$ocupado, 'etapa'=>$ocupado?$etapa:'Sin entrenamiento activo'], JSON_UNESCAPED_UNICODE);
