<?php
require_once __DIR__.'/validacion.php';
require_once __DIR__.'/alertas_operativas.php';
function registrar_compra(PDO $conexion, array &$carrito, array $datos): int {
    $datos = datos_compra($datos);
    validar_compra($datos);
    foreach (['nombre','apellido','correo','telefono','dni','direccion','tipo_envio','metodo_pago'] as $campo) $$campo = $datos[$campo];
    if (!$carrito) throw new RuntimeException('El carrito está vacío.');
    preparar_alertas_operativas($conexion);
    $stockResultante=[];
    try {
            $conexion->beginTransaction();
            $total_centimos = 0;
            ksort($carrito);
            foreach ($carrito as &$item) {
                $stmt = $conexion->prepare('SELECT p.codigo,p.nombre, p.precio, p.stock, p.estado FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca WHERE p.id_producto = :id AND c.estado=1 AND m.estado=1 FOR UPDATE');
                $stmt->execute(['id' => $item['id_producto']]);
                $producto = $stmt->fetch();
                if (!is_int($item['cantidad']) || $item['cantidad'] < 1 || $item['cantidad'] > 2147483647) throw new RuntimeException('La cantidad del carrito no es válida.');
                if (!$producto || !$producto['estado'] || $item['cantidad'] < 1 || $producto['stock'] < $item['cantidad']) {
                    throw new RuntimeException('Un producto ya no está disponible en la cantidad solicitada. Revisa tu carrito.');
                }
                if (precio_centimos($item['precio']) !== precio_centimos($producto['precio'])) {
                    $item['precio'] = $producto['precio'];
                    throw new RuntimeException('El precio de un producto cambió. Revisa el total actualizado y confirma nuevamente.');
                }
                $item['nombre'] = $producto['nombre'];
                $stockResultante[]=['id_producto'=>$item['id_producto'],'codigo'=>$producto['codigo'],'nombre'=>$producto['nombre'],'stock'=>(int)$producto['stock']-$item['cantidad']];
                $total_centimos += precio_centimos($producto['precio']) * $item['cantidad'];
            }
            unset($item);
            $total_centimos += $tipo_envio === 'fuera' ? 1500 : 0;
            if ($total_centimos > 9999999999) throw new RuntimeException('El importe excede el límite admitido por pedido.');
            $total_general = decimal_centimos($total_centimos);
            $direccion_completa = "DNI: $dni | Envío: " . ($tipo_envio === 'fuera' ? 'Fuera de la ciudad (+S/ 15)' : 'Dentro de la ciudad (Gratis)') . " | Dir: $direccion";
            // Reutilizar el cliente sin sobrescribir los datos de sus compras anteriores.
            $stmt = $conexion->prepare('INSERT INTO clientes (nombre,apellido,correo,telefono,direccion) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE id_cliente = LAST_INSERT_ID(id_cliente)');
            $stmt->execute([$nombre,$apellido,$correo,$telefono,$direccion_completa]);
            $id_cliente = $conexion->lastInsertId();
            $stmt = $conexion->prepare("INSERT INTO pedidos (id_cliente,total,estado,metodo_pago,cliente_nombre,cliente_apellido,cliente_correo,cliente_telefono,cliente_direccion) VALUES (?,?,'Pendiente',?,?,?,?,?,?)");
            $stmt->execute([$id_cliente,$total_general,$metodo_pago,$nombre,$apellido,$correo,$telefono,$direccion_completa]);
            $id_pedido = $conexion->lastInsertId();
            if($datos['entrega_lat']!==''&&$datos['entrega_lng']!==''){
                $ubicacion=$conexion->prepare('UPDATE pedidos SET entrega_lat=?,entrega_lng=?,entrega_place_id=? WHERE id_pedido=?');
                $ubicacion->execute([$datos['entrega_lat'],$datos['entrega_lng'],$datos['entrega_place_id']?:null,$id_pedido]);
            }
            $detalle = $conexion->prepare('INSERT INTO detalle_pedidos (id_pedido,id_producto,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)');
            $stock = $conexion->prepare('UPDATE productos SET stock = stock - ? WHERE id_producto = ?');
            foreach ($carrito as $item) {
                $detalle->execute([$id_pedido,$item['id_producto'],$item['cantidad'],$item['precio'],decimal_centimos(precio_centimos($item['precio'])*$item['cantidad'])]);
                $stock->execute([$item['cantidad'],$item['id_producto']]);
            }
            registrar_stock_venta($conexion,(int)$id_pedido,$stockResultante);
            $conexion->commit();
            // La alerta ya está en MySQL; el proveedor externo no puede deshacer la compra.
            try{enviar_alertas_operativas($conexion,null,null,1);}catch(Throwable $e){error_log('Compra guardada; envío operativo pendiente de revisión.');}
            return (int)$id_pedido;
    } catch (Throwable $e) {
        unset($item);
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $e;
    }
}
