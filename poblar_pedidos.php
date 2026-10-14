<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Conexión a la base de datos
require_once 'config/conexion.php'; 

echo "<div style='font-family: Arial; padding: 20px;'>";
echo "<h2>Iniciando la generación de 500 pedidos aleatorios...</h2>";

try {
    // 1. Obtener todos los IDs de clientes existentes
    $stmtClientes = $conexion->query("SELECT id_cliente FROM clientes");
    $clientes = $stmtClientes->fetchAll(PDO::FETCH_COLUMN);

    // 2. Obtener todos los productos y sus precios
    $stmtProductos = $conexion->query("SELECT id_producto, precio FROM productos");
    $productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

    if (empty($clientes) || empty($productos)) {
        die("<p style='color: red;'>Error: Necesitas tener clientes y productos en la base de datos antes de crear pedidos.</p>");
    }

    $estados = ['Pendiente', 'Confirmado', 'En preparación', 'Enviado', 'Entregado', 'Cancelado'];
    $pedidos_insertados = 0;
    $detalles_insertados = 0;

    // 3. Bucle para crear 500 pedidos
    for ($i = 0; $i < 500; $i++) {
        // Seleccionar un cliente y un estado al azar
        $id_cliente = $clientes[array_rand($clientes)];
        $estado = $estados[array_rand($estados)];
        
        // Determinar cuántos productos distintos tendrá este pedido (entre 1 y 4)
        $num_items = rand(1, 4);
        
        $items_pedido = [];
        $total_pedido = 0;
        
        // Seleccionar los productos al azar para el carrito
        $claves_productos_random = (array) array_rand($productos, $num_items);
        
        foreach ($claves_productos_random as $index) {
            $producto = $productos[$index];
            $cantidad = rand(1, 3); // Lleva entre 1 y 3 unidades del mismo producto
            $precio_unitario = $producto['precio'];
            $subtotal = $cantidad * $precio_unitario;
            
            $total_pedido += $subtotal;
            
            // Guardamos el detalle temporalmente
            $items_pedido[] = [
                'id_producto' => $producto['id_producto'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio_unitario,
                'subtotal' => $subtotal
            ];
        }
        
        // 4. Insertar la cabecera del pedido
        $sql_pedido = "INSERT INTO pedidos (id_cliente, total, estado) VALUES (:id_cliente, :total, :estado)";
        $stmt = $conexion->prepare($sql_pedido);
        $stmt->execute([
            ':id_cliente' => $id_cliente,
            ':total' => $total_pedido,
            ':estado' => $estado
        ]);
        
        // Obtener el ID del pedido recién creado
        $id_pedido = $conexion->lastInsertId();
        $pedidos_insertados++;
        
        // 5. Insertar los productos correspondientes en detalle_pedidos
        $sql_detalle = "INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad, precio_unitario, subtotal) 
                        VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario, :subtotal)";
        $stmt_det = $conexion->prepare($sql_detalle);
        
        foreach ($items_pedido as $item) {
            $stmt_det->execute([
                ':id_pedido' => $id_pedido,
                ':id_producto' => $item['id_producto'],
                ':cantidad' => $item['cantidad'],
                ':precio_unitario' => $item['precio_unitario'],
                ':subtotal' => $item['subtotal']
            ]);
            $detalles_insertados++;
        }
    }
    
    echo "<p style='color: green;'>✔ $pedidos_insertados Pedidos generados con éxito.</p>";
    echo "<p style='color: green;'>✔ $detalles_insertados Productos agregados a los detalles de esos pedidos.</p>";
    echo "<br><a href='admin/dashboard.php' style='padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error en la base de datos: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>