<?php
require_once '../../includes/verificar_admin.php';
require_once '../../includes/control_ml.php';
exigir_post();header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
try{guardar_control_ml($conexion,$_POST);echo json_encode(['ok'=>true]);}
catch(PDOException $e){error_log('Control ML: no se pudo guardar la configuración.');http_response_code(500);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo guardar la configuración en MySQL.']);}
catch(RuntimeException $e){http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>$e->getMessage()]);}
