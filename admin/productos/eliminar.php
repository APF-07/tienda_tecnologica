<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';
require_once '../../includes/registro_agentes.php';

exigir_post();
preparar_registro_agentes($conexion);
$id = intval($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $conexion->beginTransaction();$bloquear=$conexion->prepare('SELECT id_producto FROM productos WHERE id_producto=? FOR UPDATE');$bloquear->execute([$id]);
        // Verificar si el producto aparece en algún pedido (restricción ON DELETE RESTRICT)
        $stmtCheck = $conexion->prepare("SELECT COUNT(*) FROM detalle_pedidos WHERE id_producto = :id");
        $stmtCheck->execute(['id' => $id]);
        $enPedidos = $stmtCheck->fetchColumn();
        foreach(['compras_reposicion','devoluciones','movimientos_stock'] as $tabla){$q=$conexion->prepare('SELECT COUNT(*) FROM '.$tabla.' WHERE id_producto=?');$q->execute([$id]);$enPedidos+=(int)$q->fetchColumn();}

        if ($enPedidos > 0) {
            $conexion->rollBack();header('Location: listar.php?error='.urlencode('El producto tiene historial de pedidos, reposición o inventario. Cambia su estado a Inactivo para conservarlo.'));
            exit();
        }

        // Obtener la imagen para borrarla del servidor si existe
        $stmtImg = $conexion->prepare("SELECT imagen FROM productos WHERE id_producto = :id LIMIT 1");
        $stmtImg->execute(['id' => $id]);
        $prod = $stmtImg->fetch();

        if ($prod && !empty($prod['imagen'])) {
            $imgPath = '../../uploads/productos/' . basename($prod['imagen']);
        }

        // Eliminar producto
        $stmt = $conexion->prepare("DELETE FROM productos WHERE id_producto = :id");
        $stmt->execute(['id' => $id]);
        $conexion->commit();if(isset($imgPath)&&is_file($imgPath))@unlink($imgPath);

        header('Location: listar.php?msg=Producto eliminado correctamente.');
        exit();

    } catch (PDOException $e) {
        if($conexion->inTransaction())$conexion->rollBack();
        header('Location: listar.php?error=Ocurrió un error al intentar eliminar el producto.');
        exit();
    }
} else {
    header('Location: listar.php');
    exit();
}
?>
