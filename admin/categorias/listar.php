<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

// Obtener mensajes de éxito o error si existen
$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

try {
    $stmt = $conexion->query("SELECT * FROM categorias ORDER BY id_categoria DESC");
    $categorias = $stmt->fetchAll();
} catch (Exception $e) {
    $categorias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .btn-action { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; margin-right: 5px; }
        .btn-add { background-color: #27ae60; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none; display: inline-block; margin-bottom: 20px; font-weight: 600; }
        .btn-add:hover { background-color: #219653; }
        .btn-edit { background-color: #f39c12; color: #fff; }
        .btn-delete { background-color: #e74c3c; color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background-color: #f8f9fa; color: #1e3c72; font-weight: 600; }
        .alert-success { background: #e8f8f5; color: #27ae60; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fde8e8; color: #c0392b; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
<link rel="stylesheet" href="../../css/admin-mejoras.css?v=20261013-menu">
<link rel="stylesheet" href="../../css/experiencia.css?v=20261020"><script src="../../js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="../../css/admin-experiencia.css?v=20261020"><script src="../../js/admin-experiencia.js?v=20261020" defer></script>
</head>
<body>

    <?php render_admin_sidebar('categorias'); ?>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Gestión de Categorías</h1>
            <div class="admin-user-info">Admin: <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></div>
        </header>

        <main class="admin-content">
            <?php if (!empty($mensaje)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <a href="agregar.php" class="btn-add">+ Nueva Categoría</a>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($categorias) > 0): ?>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td><?php echo $cat['id_categoria']; ?></td>
                                <td><strong><?php echo htmlspecialchars($cat['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cat['descripcion']); ?></td>
                                <td>
                                    <?php if ($cat['estado'] == 1): ?>
                                        <span style="color: #27ae60; font-weight: 600;">Activo</span>
                                    <?php else: ?>
                                        <span style="color: #e74c3c; font-weight: 600;">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $cat['fecha_registro']; ?></td>
                                <td>
                                    <a href="editar.php?id=<?php echo $cat['id_categoria']; ?>" class="btn-action btn-edit" aria-label="Editar categoría <?= e($cat['nombre']) ?>">Editar categoría</a>
                                    <form action="eliminar.php" method="POST" class="inline-action" onsubmit="return confirm('¿Eliminar esta categoría? Esta acción puede rechazarse si tiene registros relacionados.');"><?php echo csrf_campo(); ?><input type="hidden" name="id" value="<?php echo $cat['id_categoria']; ?>"><button type="submit" class="btn-action btn-delete" aria-label="Eliminar categoría <?= e($cat['nombre']) ?>">Eliminar</button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #777;">No hay categorías registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>

</body>
</html>