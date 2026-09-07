<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);

    if (empty($nombre)) {
        $error = 'El nombre de la marca es obligatorio.';
    } else {
        try {
            $stmt = $conexion->prepare("INSERT INTO marcas (nombre, descripcion) VALUES (:nombre, :descripcion)");
            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion
            ]);
            header('Location: listar.php?msg=Marca registrada correctamente.');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Ya existe una marca con ese nombre.';
            } else {
                $error = 'Error al registrar la marca.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Marca - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .form-container { background: #fff; padding: 30px; border-radius: 8px; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #333; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; height: 100px; }
        .btn-submit { background-color: #27ae60; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background-color: #219653; }
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
            <li><a href="../categorias/listar.php">🗂️ Categorías</a></li>
            <li><a href="listar.php" class="active">🏷️ Marcas</a></li>
            <li><a href="../pedidos/listar.php">🛒 Pedidos</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Registrar Nueva Marca</h1>
        </header>

        <main class="admin-content">
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="agregar.php" method="POST">
                    <div class="form-group">
                        <label for="nombre">Nombre de la Marca *</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Ej. ASUS, Logitech...">
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" placeholder="Breve descripción de la marca..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Guardar Marca</button>
                    <a href="listar.php" class="btn-back">Cancelar</a>
                </form>
            </div>
        </main>
    </div>

</body>
</html>