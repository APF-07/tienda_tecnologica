<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/conexion.php';
require __DIR__.'/../includes/registro_agentes.php';
require __DIR__.'/../includes/evaluacion_prospectiva.php';
preparar_registro_agentes($conexion);preparar_evaluacion_prospectiva($conexion);
echo "Registro de agentes, transiciones y pronósticos preparado. No se enviaron mensajes ni se modificó stock.\n";
