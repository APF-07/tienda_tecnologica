<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';
require_once '../../includes/multiagentes.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
exigir_post();session_write_close();
set_time_limit(45);
$bloqueado=false;
try{
    preparar_registro_agentes($conexion);
    if($conexion->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$bloqueado=(int)$conexion->query("SELECT GET_LOCK('techstore_agentes_auto',0)")->fetchColumn()===1;if(!$bloqueado){echo json_encode(['ok'=>true,'estado'=>'Ya hay una ejecución en curso']);exit;}}
    $estado=leer_estado_agentes($conexion,'ultimo_intento_auto');
    if($estado&&time()-(int)$estado['timestamp']<60){echo json_encode(['ok'=>true,'estado'=>'Revisión reciente']);}
    else{escribir_estado_agentes($conexion,'ultimo_intento_auto',['timestamp'=>time()]);$r=ejecutar_multiagentes($conexion,true);echo json_encode(['ok'=>true,'estado'=>$r['telegram']['estado']??'Completado']);}
}catch(Throwable $e){error_log('Agentes automáticos: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'estado'=>'No se completó la revisión automática.']);}
finally{if($bloqueado)$conexion->query("SELECT RELEASE_LOCK('techstore_agentes_auto')");}
