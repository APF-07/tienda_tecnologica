<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

$error = '';

// Obtener categorías y marcas activas para los selectores del formulario
try {
    $categorias = $conexion->query("SELECT * FROM categorias WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
    $marcas = $conexion->query("SELECT * FROM marcas WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {
    $categorias = [];
    $marcas = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_categoria = intval($_POST['id_categoria']);
    $id_marca = intval($_POST['id_marca']);
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $imagen_nombre = null;

    if (empty($codigo) || empty($nombre) || $id_categoria <= 0 || $id_marca <= 0 || $precio <= 0) {
        $error = 'Por favor, completa todos los campos obligatorios correctamente.';
    } else {
        // Procesar subida de imagen si se adjuntó una
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['imagen']['tmp_name'];
            $fileName = $_FILES['imagen']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadFileDir = '../../uploads/productos/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                
                // Generar un nombre único para evitar colisiones
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $imagen_nombre = $newFileName;
                }
            }
        }

        try {
            $stmt = $conexion->prepare("INSERT INTO productos (codigo, nombre, descripcion, id_categoria, id_marca, precio, stock, imagen) VALUES (:codigo, :nombre, :descripcion, :id_categoria, :id_marca, :precio, :stock, :imagen)");
            $stmt->execute([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'id_categoria' => $id_categoria,
                'id_marca' => $id_marca,
                'precio' => $precio,
                'stock' => $stock,
                'imagen' => $imagen_nombre
            ]);
            header('Location: listar.php?msg=Producto registrado correctamente.');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'El código del producto ya existe.';
            } else {
                $error = 'Error al registrar el producto en la base de datos.';
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
    <title>Nuevo Producto - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .form-container { background: #fff; padding: 30px; border-radius: 8px; max-width: 700px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .form-row { display: flex; gap: 20px; }
        .form-group { margin-bottom: 20px; flex: 1; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; height: 90px; }
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
            <li><a href="listar.php" class="active">📦 Productos</a></li>
            <li><a href="../categorias/listar.php">🗂️ Categorías</a></li>
            <li><a href="../marcas/listar.php">🏷️ Marcas</a></li>
            <li><a href="../pedidos/listar.php">🛒 Pedidos</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Registrar Nuevo Producto</h1>
        </header>

        <main class="admin-content">
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="agregar.php" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo">Código / SKU *</label>
                            <input type="text" id="codigo" name="codigo" required placeholder="Ej. LAP-ASUS-01">
                        </div>
                        <div class="form-group">
                            <label for="nombre">Nombre del Producto *</label>
                            <input type="text" id="nombre" name="nombre" required placeholder="Ej. Laptop ASUS ROG Strix">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_categoria">Categoría *</label>
                            <select id="id_categoria" name="id_categoria" required>
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="id_marca">Marca *</label>
                            <select id="id_marca" name="id_marca" required>
                                <option value="">Seleccione una marca</option>
                                <?php foreach ($marcas as $marca): ?>
                                    <option value="<?php echo $marca['id_marca']; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio">Precio ($) *</label>
                            <input type="number" step="0.01" id="precio" name="precio" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock Inicial *</label>
                            <input type="number" id="stock" name="stock" required value="10" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" placeholder="Especificaciones técnicas completas..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="imagen">Imagen del Producto (JPG, PNG, WEBP)</label>
                        <input type="file" id="imagen" name="imagen" accept="image/*">
                    </div>

                    <button type="submit" class="btn-submit">Guardar Producto</button>
                    <a href="listar.php" class="btn-back">Cancelar</a>
                </form>
            </div>
        </main>
    </div>

</body>
</html>