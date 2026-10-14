<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

// Forzar la descarga del archivo como Excel
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_pedidos.csv');

$output = fopen('php://output', 'w');

// Añadir BOM de UTF-8 para las tildes
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeceras de las columnas para los pedidos
fputcsv($output, ['ID Pedido', 'Cliente', 'Correo', 'Teléfono', 'Fecha', 'Total (S/)', 'Método de Pago', 'N° Operación', 'Estado']);

// Consultar los pedidos con la información de los clientes
require_once '../../includes/listados.php';
$lista = listar_admin($conexion,'pedidos',$_GET,true);
foreach ($lista['filas'] as $row) {
    fputcsv($output, array_map('csv_seguro', [
        $row['id_pedido'],
        $row['cliente_nombre'] . ' ' . $row['cliente_apellido'],
        $row['correo'],
        $row['telefono'],
        $row['fecha_pedido'],
        $row['total'],
        $row['metodo_pago'],
        $row['codigo_operacion'] ?? 'N/A',
        $row['estado']
    ]));
}

fclose($output);
exit();
?>