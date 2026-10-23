<?php
require_once __DIR__.'/registro_agentes.php';
function cambiar_estado_pedido(PDO $conexion, int $id, string $estado): void {
    $validos = ['Pendiente','Confirmado','En preparación','Enviado','Entregado','Cancelado'];
    if (!in_array($estado, $validos, true)) throw new RuntimeException('Estado no válido.');
    preparar_registro_agentes($conexion);
    try {
        $conexion->beginTransaction();
        $stmt = $conexion->prepare('SELECT estado FROM pedidos WHERE id_pedido = ? FOR UPDATE');
        $stmt->execute([$id]);
        $anterior = $stmt->fetchColumn();
        if ($anterior === false) throw new RuntimeException('Pedido no encontrado.');
        if($estado!=='Entregado'){$q=$conexion->prepare("SELECT COUNT(*) FROM devoluciones WHERE id_pedido=? AND estado<>'Rechazada'");$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('El pedido tiene devoluciones: gestiona esas operaciones antes de cambiar su estado.');}
        if (($anterior === 'Cancelado') !== ($estado === 'Cancelado')) {
            $stmt = $conexion->prepare('SELECT id_producto, cantidad FROM detalle_pedidos WHERE id_pedido = ? ORDER BY id_producto');
            $stmt->execute([$id]);
            $detalles = $stmt->fetchAll();
            $consultar = $conexion->prepare('SELECT id_producto,codigo,nombre,stock,estado FROM productos WHERE id_producto = ? FOR UPDATE');
            $actualizar = $conexion->prepare('UPDATE productos SET stock = stock + ? WHERE id_producto = ?');
            foreach ($detalles as $item) {
                $consultar->execute([$item['id_producto']]);
                $producto=$consultar->fetch(PDO::FETCH_ASSOC);$stock=$producto?(int)$producto['stock']:false;
                if ($stock === false || ($anterior === 'Cancelado' && $stock < $item['cantidad'])) {
                    throw new RuntimeException('No hay stock suficiente para reactivar el pedido.');
                }
                $cantidad = $estado === 'Cancelado' ? $item['cantidad'] : -$item['cantidad'];
                $actualizar->execute([$cantidad, $item['id_producto']]);
                $producto['stock']=$stock+$cantidad;registrar_transicion_stock($conexion,$producto,$stock,$estado==='Cancelado'?'cancelacion':'reactivacion',(string)$id,'Cambio de estado del pedido');
            }
        }
        $stmt = $conexion->prepare('UPDATE pedidos SET estado = ? WHERE id_pedido = ?');
        $stmt->execute([$estado,$id]);
        $conexion->commit();
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $e;
    }
}
