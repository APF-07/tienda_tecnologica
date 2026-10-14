<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

// Forzar la descarga del archivo como Excel
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_productos.csv');

$output = fopen('php://output', 'w');

// Añadir BOM de UTF-8 para que Excel lea bien las tildes y eñes
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeceras de las columnas en el Excel
fputcsv($output, ['ID', 'Código', 'Nombre del Producto', 'Categoría', 'Marca', 'Precio (S/)', 'Stock', 'Estado']);

// Consultar los productos uniendo categorías y marcas
require_once '../../includes/listados.php';
$lista = listar_admin($conexion,'productos',$_GET,true);
foreach ($lista['filas'] as $row) {
    fputcsv($output, array_map('csv_seguro', [
        $row['id_producto'],
        $row['codigo'],
        $row['nombre'],
        $row['categoria'],
        $row['marca'],
        $row['precio'],
        $row['stock'],
        ($row['estado'] == 1 ? 'Activo' : 'Inactivo')
    ]));
}

fclose($output);
exit();
?>