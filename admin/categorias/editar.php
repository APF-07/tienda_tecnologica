<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

$error = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: listar.php');
    exit();
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $estado = isset($_POST['estado']) ? intval($_POST['estado']) : 1;

    if (empty($nombre)) {
        $error = 'El nombre de la categoría es obligatorio.';
    } else {
        try {
            $stmt = $conexion->prepare("UPDATE categorias SET nombre = :nombre, descripcion = :descripcion, estado = :estado WHERE id_categoria = :id");
            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'estado' => $estado,
                'id' => $id
            ]);
            header('Location: listar.php?msg=Categoría actualizada correctamente.');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Ya existe otra categoría con ese nombre.';
            } else {
                $error = 'Error al actualizar la categoría.';
            }
        }
    }
}

// Obtener datos actuales
try {
    $stmt = $conexion->prepare("SELECT * FROM categorias WHERE id_categoria = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $categoria = $stmt->fetch();

    if (!$categoria) {
        header('Location: listar.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: listar.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoría - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .form-container { background: #fff; padding: 30px; border-radius: 8px; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #333; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; height: 100px; }
        .btn-submit { background-color: #f39c12; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background-color: #d68910; }
        .btn-back { background-color: #95a5a6; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-left: 10px; }
        .alert-error { background: #fde8e8; color: #c0392b; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <div class="sidebar-brand">TechStore Admin</div>
        <ul class="sidebar-menu">
            <li><a href="../dashboard.php">📊 Dashboard</a></li>
            <li><a href="../productos/listar.php">📦 Productos</a></li>
            <li><a href="listar.php" class="active">🗂️ Categorías</a></li>
            <li><a href="../marcas/listar.php">🏷️ Marcas</a></li>
            <li><a href="../pedidos/listar.php">🛒 Pedidos</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Editar Categoría</h1>
        </header>

        <main class="admin-content">
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="editar.php?id=<?php echo $id; ?>" method="POST">
                    <div class="form-group">
                        <label for="nombre">Nombre de la Categoría *</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($categoria['nombre']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion"><?php echo htmlspecialchars($categoria['descripcion']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="1" <?php echo ($categoria['estado'] == 1) ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo ($categoria['estado'] == 0) ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Actualizar Categoría</button>
                    <a href="listar.php" class="btn-back">Cancelar</a>
                </form>
            </div>
        </main>
    </div>

</body>
</html>