<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pedido = isset($_POST['id_pedido']) ? intval($_POST['id_pedido']) : 0;
    $nuevo_estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';

    $estados_validos = ['Pendiente', 'Confirmado', 'En preparación', 'Enviado', 'Entregado', 'Cancelado'];

    if ($id_pedido > 0 && in_array($nuevo_estado, $estados_validos)) {
        try {
            // Iniciar transacción para asegurar consistencia si se cancela y devuelve stock
            $conexion->beginTransaction();

            // 1. Obtener estado actual del pedido
            $stmtAnt = $conexion->prepare("SELECT estado FROM pedidos WHERE id_pedido = :id LIMIT 1");
            $stmtAnt->execute(['id' => $id_pedido]);
            $pedidoActual = $stmtAnt->fetch();

            if (!$pedidoActual) {
                throw new Exception("Pedido no encontrado.");
            }

            $estadoAnterior = $pedidoActual['estado'];

            // 2. Si el pedido pasa a ser "Cancelado" y antes NO estaba cancelado, devolver stock
            if ($nuevo_estado === 'Cancelado' && $estadoAnterior !== 'Cancelado') {
                $stmtDetalles = $conexion->prepare("SELECT id_producto, cantidad FROM detalle_pedidos WHERE id_pedido = :id");
                $stmtDetalles->execute(['id' => $id_pedido]);
                $detalles = $stmtDetalles->fetchAll();

                $stmtDevolverStock = $conexion->prepare("UPDATE productos SET stock = stock + :cantidad WHERE id_producto = :id_producto");

                foreach ($detalles as $item) {
                    $stmtDevolverStock->execute([
                        'cantidad' => $item['cantidad'],
                        'id_producto' => $item['id_producto']
                    ]);
                }
            }

            // 3. Si el pedido estaba "Cancelado" y ahora se reactiva a otro estado, descontar stock nuevamente
            if ($estadoAnterior === 'Cancelado' && $nuevo_estado !== 'Cancelado') {
                $stmtDetalles = $conexion->prepare("SELECT id_producto, cantidad FROM detalle_pedidos WHERE id_pedido = :id");
                $stmtDetalles->execute(['id' => $id_pedido]);
                $detalles = $stmtDetalles->fetchAll();

                $stmtQuitarStock = $conexion->prepare("UPDATE productos SET stock = stock - :cantidad WHERE id_producto = :id_producto");

                foreach ($detalles as $item) {
                    $stmtQuitarStock->execute([
                        'cantidad' => $item['cantidad'],
                        'id_producto' => $item['id_producto']
                    ]);
                }
            }

            // 4. Actualizar el estado en la tabla pedidos
            $stmtUpdate = $conexion->prepare("UPDATE pedidos SET estado = :estado WHERE id_pedido = :id");
            $stmtUpdate->execute([
                'estado' => $nuevo_estado,
                'id' => $id_pedido
            ]);

            $conexion->commit();
            header('Location: detalle.php?id=' . $id_pedido . '&msg=Estado del pedido actualizado correctamente.');
            exit();

        } catch (Exception $e) {
            $conexion->rollBack();
            header('Location: detalle.php?id=' . $id_pedido . '&error=Error al intentar actualizar el estado.');
            exit();
        }
    }
}

header('Location: listar.php');
exit();
?>