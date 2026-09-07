<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    try {
        // Intentar eliminar la categoría
        $stmt = $conexion->prepare("DELETE FROM categorias WHERE id_categoria = :id");
        $stmt->execute(['id' => $id]);
        
        header('Location: listar.php?msg=Categoría eliminada correctamente.');
        exit();
    } catch (PDOException $e) {
        // Capturar error por restricción de clave foránea (ON DELETE RESTRICT) si tiene productos asociados
        if ($e->getCode() == 23000) {
            header('Location: listar.php?error=No se puede eliminar la categoría porque tiene productos asociados.');
            exit();
        } else {
            header('Location: listar.php?error=Ocurrió un error al intentar eliminar la categoría.');
            exit();
        }
    }
} else {
    header('Location: listar.php');
    exit();
}
?>