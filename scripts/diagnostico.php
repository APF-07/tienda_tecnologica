<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/conexion.php';
echo 'Motor: ' . $conexion->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;
foreach (['clientes', 'pedidos', 'productos'] as $tabla) {
    echo $tabla . ': ' . $conexion->query("SELECT COUNT(*) FROM $tabla")->fetchColumn() . " registros\n";
    echo implode(', ', array_column($conexion->query("SHOW COLUMNS FROM $tabla")->fetchAll(), 'Field')) . "\n";
}

echo json_encode($conexion->query("SELECT MIN(fecha_pedido) primera, MAX(fecha_pedido) ultima, COUNT(DISTINCT DATE(fecha_pedido)) dias FROM pedidos")->fetch(),JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo json_encode($conexion->query("SELECT estado,COUNT(*) cantidad FROM pedidos GROUP BY estado")->fetchAll(),JSON_UNESCAPED_UNICODE) . PHP_EOL;
