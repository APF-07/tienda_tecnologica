<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/conexion.php';require __DIR__.'/../includes/registro_agentes.php';require __DIR__.'/../includes/permisos.php';
preparar_registro_agentes($conexion);preparar_roles($conexion);
echo "Tablas de gestión y roles preparadas. Sin pagos, mensajes, cambios de stock ni creación de usuarios.\n";
