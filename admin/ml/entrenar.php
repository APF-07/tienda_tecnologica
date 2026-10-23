<?php
require_once '../../includes/verificar_admin.php';
exigir_post();
header('Content-Type: application/json; charset=UTF-8');
set_time_limit(600);session_write_close();
try {
    require_once '../../includes/control_ml.php';
    $r=entrenar_control_ml($conexion);
    require_once '../../includes/centro_operaciones.php';
    evaluar_pronosticos_guardados($conexion);sincronizar_acciones_dia($conexion);
    echo json_encode(['ok'=>true,'mensaje'=>$r['mensaje']]);
} catch(Throwable $e) {
    error_log($e->getMessage());http_response_code(str_contains($e->getMessage(),'en curso')?409:500);
    echo json_encode(['ok'=>false,'mensaje'=>'No se completó el entrenamiento. Comprueba el estado y registro en Control ML.']);
}