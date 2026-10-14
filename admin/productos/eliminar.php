<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

exigir_post();
$id = intval($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        // Verificar si el producto aparece en algún pedido (restricción ON DELETE RESTRICT)
        $stmtCheck = $conexion->prepare("SELECT COUNT(*) FROM detalle_pedidos WHERE id_producto = :id");
        $stmtCheck->execute(['id' => $id]);
        $enPedidos = $stmtCheck->fetchColumn();

        if ($enPedidos > 0) {
            header('Location: listar.php?error=No se puede eliminar el producto porque ya forma parte de pedidos registrados. Te sugerimos cambiar su estado a Inactivo.');
            exit();
        }

        // Obtener la imagen para borrarla del servidor si existe
        $stmtImg = $conexion->prepare("SELECT imagen FROM productos WHERE id_producto = :id LIMIT 1");
        $stmtImg->execute(['id' => $id]);
        $prod = $stmtImg->fetch();

        if ($prod && !empty($prod['imagen'])) {
            $imgPath = '../../uploads/productos/' . basename($prod['imagen']);
            if (file_exists($imgPath)) {
                unlink($imgPath);
            }
        }

        // Eliminar producto
        $stmt = $conexion->prepare("DELETE FROM productos WHERE id_producto = :id");
        $stmt->execute(['id' => $id]);

        header('Location: listar.php?msg=Producto eliminado correctamente.');
        exit();

    } catch (PDOException $e) {
        header('Location: listar.php?error=Ocurrió un error al intentar eliminar el producto.');
        exit();
    }
} else {
    header('Location: listar.php');
    exit();
}
?>