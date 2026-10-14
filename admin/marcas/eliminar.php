<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

exigir_post();
$id = intval($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $conexion->prepare("DELETE FROM marcas WHERE id_marca = :id");
        $stmt->execute(['id' => $id]);
        
        header('Location: listar.php?msg=Marca eliminada correctamente.');
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header('Location: listar.php?error=No se puede eliminar la marca porque tiene productos asociados.');
            exit();
        } else {
            header('Location: listar.php?error=Ocurrió un error al intentar eliminar la marca.');
            exit();
        }
    }
} else {
    header('Location: listar.php');
    exit();
}
?>