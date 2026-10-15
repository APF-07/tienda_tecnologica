<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

require_once '../../includes/listados.php';
$lista = listar_admin($conexion,'productos',$_GET);
$productos = $lista['filas'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .btn-action { padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; margin-right: 3px; display: inline-block; }
        .btn-add { background-color: #27ae60; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none; display: inline-block; margin-bottom: 20px; font-weight: 600; }
        .btn-add:hover { background-color: #219653; }
        .btn-edit { background-color: #f39c12; color: #fff; }
        .btn-delete { background-color: #e74c3c; color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; vertical-align: middle; }
        th { background-color: #f8f9fa; color: #1e3c72; font-weight: 600; }
        .product-img { width: 45px; height: 45px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .alert-success { background: #e8f8f5; color: #27ae60; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fde8e8; color: #c0392b; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
<link rel="stylesheet" href="../../css/admin-mejoras.css?v=20261013-menu">
<link rel="stylesheet" href="../../css/experiencia.css?v=20261014"><script src="../../js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="../../css/admin-experiencia.css?v=1"><script src="../../js/admin-experiencia.js?v=1" defer></script>
</head>
<body>

    <?php render_admin_sidebar('productos'); ?>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Gestión de Productos</h1>
            <div class="admin-user-info">Admin: <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></div>
        </header>

        <main class="admin-content">
            <?php if (!empty($mensaje)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="admin-toolbar"><a href="agregar.php" class="btn-add">+ Nuevo producto</a><a class="btn-action" href="exportar.php?<?php echo e(http_build_query(array_intersect_key($_GET,array_flip(['q','estado','orden','stock','marca','categoria'])))); ?>">Descargar CSV</a></div><?php render_filtros_admin('productos',$lista); ?>

            <table>
                <thead>
                    <tr>
                        <th>Img</th>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Marca</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($productos) > 0): ?>
                        <?php foreach ($productos as $prod): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo e(imagen_producto($prod)); ?>" class="product-img" alt="<?php echo e($prod['nombre']); ?>" loading="lazy">
                                </td>
                                <td><code><?php echo htmlspecialchars($prod['codigo']); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($prod['categoria']); ?></td>
                                <td><?php echo htmlspecialchars($prod['marca']); ?></td>
                                <td>S/ <?php echo number_format($prod['precio'], 2); ?></td>
                                <td>
                                    <?php if ($prod['stock'] > 5): ?>
                                        <span style="color: #27ae60; font-weight: 600;"><?php echo $prod['stock']; ?></span>
                                    <?php elseif ($prod['stock'] > 0): ?>
                                        <span style="color: #f39c12; font-weight: 600;"><?php echo $prod['stock']; ?> (Bajo)</span>
                                    <?php else: ?>
                                        <span style="color: #e74c3c; font-weight: 600;">Agotado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($prod['estado'] == 1): ?>
                                        <span style="color: #27ae60;">Activo</span>
                                    <?php else: ?>
                                        <span style="color: #e74c3c;">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="editar.php?id=<?php echo $prod['id_producto']; ?>" class="btn-action btn-edit">Editar</a>
                                    <form action="eliminar.php" method="POST" class="inline-action" onsubmit="return confirm('¿Eliminar este registro?');"><?php echo csrf_campo(); ?><input type="hidden" name="id" value="<?php echo $prod['id_producto']; ?>"><button type="submit" class="btn-action btn-delete">Eliminar</button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #777;">No hay productos que coincidan con estos filtros.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table><?php render_paginacion($lista['pagina'],$lista['paginas'],$lista['total']); ?>
        </main>
    </div>

</body>
</html>