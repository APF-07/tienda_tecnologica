<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';
require_once '../../includes/pedidos.php';
exigir_post();
$id = intval($_POST['id_pedido'] ?? 0);
try {
    cambiar_estado_pedido($conexion, $id, trim($_POST['estado'] ?? ''));
    header('Location: detalle.php?id=' . $id . '&msg=' . rawurlencode('Estado actualizado correctamente.'));
} catch (Throwable $e) {
    error_log($e->getMessage());
    $mensaje = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'No se pudo actualizar el pedido.';
    header('Location: detalle.php?id=' . $id . '&error=' . rawurlencode($mensaje));
}
exit;
