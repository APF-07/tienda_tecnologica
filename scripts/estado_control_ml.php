<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/conexion.php';
require __DIR__.'/../includes/control_ml.php';
$c=configuracion_control_ml($conexion);$u=leer_control_ml($conexion,'ultima',[]);
echo json_encode(['automatico'=>$c['automatico'],'seleccion_ventas'=>$c['ventas'],'seleccion_pedidos'=>$c['pedidos'],'ultima'=>$u,'requiere_entrenamiento'=>requiere_entrenamiento_ml($conexion)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
