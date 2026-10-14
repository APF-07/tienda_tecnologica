<?php
// Migración aditiva: no elimina tablas ni pedidos. Ejecutar únicamente desde consola.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/conexion.php';
$columnas = array_column($conexion->query('SHOW COLUMNS FROM pedidos')->fetchAll(), 'Field');
$nuevas = [
    'metodo_pago' => "VARCHAR(50) NOT NULL DEFAULT 'Contraentrega'",
    'codigo_operacion' => 'VARCHAR(100) NULL',
    'cliente_nombre' => 'VARCHAR(100) NULL',
    'cliente_apellido' => 'VARCHAR(100) NULL',
    'cliente_correo' => 'VARCHAR(100) NULL',
    'cliente_telefono' => 'VARCHAR(20) NULL',
    'cliente_direccion' => 'TEXT NULL'
];
foreach ($nuevas as $nombre => $definicion) {
    if (!in_array($nombre, $columnas, true)) {
        $conexion->exec("ALTER TABLE pedidos ADD COLUMN $nombre $definicion");
        echo "Columna añadida: $nombre\n";
    }
}
$conexion->exec('UPDATE pedidos p JOIN clientes c ON c.id_cliente = p.id_cliente SET p.cliente_nombre = c.nombre, p.cliente_apellido = c.apellido, p.cliente_correo = c.correo, p.cliente_telefono = c.telefono, p.cliente_direccion = c.direccion WHERE p.cliente_correo IS NULL');
echo "Esquema actualizado; historial de entrega conservado.\n";
