<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

$error = '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: listar.php');
    exit();
}

// Obtener categorías y marcas activas
try {
    $categorias = $conexion->query("SELECT * FROM categorias WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
    $marcas = $conexion->query("SELECT * FROM marcas WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {
    $categorias = [];
    $marcas = [];
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_categoria = intval($_POST['id_categoria']);
    $id_marca = intval($_POST['id_marca']);
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $estado = intval($_POST['estado']);
    $imagen_nombre = $_POST['imagen_actual'];

    if (empty($codigo) || empty($nombre) || $id_categoria <= 0 || $id_marca <= 0 || $precio <= 0) {
        $error = 'Por favor, completa todos los campos obligatorios correctamente.';
    } else {
        // Verificar si se subió una nueva imagen
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['imagen']['tmp_name'];
            $fileName = $_FILES['imagen']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadFileDir = '../../uploads/productos/';
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $imagen_nombre = $newFileName;
                }
            }
        }

        try {
            $stmt = $conexion->prepare("UPDATE productos SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion, id_categoria = :id_categoria, id_marca = :id_marca, precio = :precio, stock = :stock, imagen = :imagen, estado = :estado WHERE id_producto = :id");
            $stmt->execute([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'id_categoria' => $id_categoria,
                'id_marca' => $id_marca,
                'precio' => $precio,
                'stock' => $stock,
                'imagen' => $imagen_nombre,
                'estado' => $estado,
                'id' => $id
            ]);
            header('Location: listar.php?msg=Producto actualizado correctamente.');
            exit();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'El código del producto ya existe en otro registro.';
            } else {
                $error = 'Error al actualizar el producto.';
            }
        }
    }
}

// Obtener datos actuales del producto
try {
    $stmt = $conexion->prepare("SELECT * FROM productos WHERE id_producto = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $producto = $stmt->fetch();

    if (!$producto) {
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
    <title>Editar Producto - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .form-container { background: #fff; padding: 30px; border-radius: 8px; max-width: 700px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .form-row { display: flex; gap: 20px; }
        .form-group { margin-bottom: 20px; flex: 1; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; height: 90px; }
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
            <h1>Editar Producto</h1>
        </header>

        <main class="admin-content">
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="editar.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($producto['imagen']); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo">Código / SKU *</label>
                            <input type="text" id="codigo" name="codigo" value="<?php echo htmlspecialchars($producto['codigo']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="nombre">Nombre del Producto *</label>
                            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_categoria">Categoría *</label>
                            <select id="id_categoria" name="id_categoria" required>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>" <?php echo ($producto['id_categoria'] == $cat['id_categoria']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="id_marca">Marca *</label>
                            <select id="id_marca" name="id_marca" required>
                                <?php foreach ($marcas as $marca): ?>
                                    <option value="<?php echo $marca['id_marca']; ?>" <?php echo ($producto['id_marca'] == $marca['id_marca']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($marca['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio">Precio ($) *</label>
                            <input type="number" step="0.01" id="precio" name="precio" value="<?php echo $producto['precio']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock *</label>
                            <input type="number" id="stock" name="stock" value="<?php echo $producto['stock']; ?>" required min="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="estado">Estado *</label>
                            <select id="estado" name="estado">
                                <option value="1" <?php echo ($producto['estado'] == 1) ? 'selected' : ''; ?>>Activo</option>
                                <option value="0" <?php echo ($producto['estado'] == 0) ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="imagen">Cambiar Imagen</label>
                            <input type="file" id="imagen" name="imagen" accept="image/*">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Actualizar Producto</button>
                    <a href="listar.php" class="btn-back">Cancelar</a>
                </form>
            </div>
        </main>
    </div>

</body>
</html>