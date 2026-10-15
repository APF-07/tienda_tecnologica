<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/conexion.php';
$columnas=array_column($conexion->query('SHOW COLUMNS FROM pedidos')->fetchAll(),'Field');
foreach(['entrega_lat'=>'DECIMAL(10,7) NULL','entrega_lng'=>'DECIMAL(10,7) NULL','entrega_place_id'=>'VARCHAR(255) NULL'] as $nombre=>$tipo){
    if(!in_array($nombre,$columnas,true)){$conexion->exec("ALTER TABLE pedidos ADD COLUMN $nombre $tipo");echo "Añadida $nombre\n";}
}
echo "Destino actualizado; pedidos anteriores conservados.\n";
