<?php
require_once '../../includes/verificar_admin.php';require_once '../../config/conexion.php';require_once '../../includes/listados.php';
$lista=listar_admin($conexion,'clientes',$_GET,true);
header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename=clientes.csv');
$salida=fopen('php://output','w');fprintf($salida,"\xEF\xBB\xBF");
fputcsv($salida,['ID','Nombre','Apellido','Correo','Teléfono','Registro']);
foreach($lista['filas'] as $c) fputcsv($salida,array_map('csv_seguro',[$c['id_cliente'],$c['nombre'],$c['apellido'],$c['correo'],$c['telefono'],$c['fecha_registro']]));
fclose($salida);
