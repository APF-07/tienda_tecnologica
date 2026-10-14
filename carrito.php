<?php
require_once 'config/conexion.php';
require_once 'includes/carrito.php';
$_SESSION['carrito'] = $_SESSION['carrito'] ?? [];
$avisos = sincronizar_carrito($conexion,$_SESSION['carrito']);
$mensaje = $_SESSION['mensaje_carrito'] ?? '';
unset($_SESSION['mensaje_carrito']);
if ($avisos) $mensaje = implode(' ', $avisos);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $accion = $_POST['accion_boton'] ?? $_POST['accion'] ?? '';
    try {
        if (!is_string($accion)) throw new RuntimeException('Acción no válida.');
        if ($accion==='agregar') {
            agregar_carrito($conexion,$_SESSION['carrito'],intval($_POST['id_producto'] ?? 0),intval($_POST['cantidad'] ?? 1));
            $mensaje='Producto agregado al carrito.';
        } elseif ($accion==='actualizar') {
            actualizar_carrito($_SESSION['carrito'],is_array($_POST['cantidades'] ?? null)?$_POST['cantidades']:[]);
            $mensaje='Carrito actualizado.';
        } elseif (str_starts_with($accion,'eliminar:')) {
            unset($_SESSION['carrito'][intval(substr($accion,9))]);
            $mensaje='Producto retirado del carrito.';
        } elseif ($accion==='vaciar') {
            $_SESSION['carrito']=[]; $mensaje='Carrito vaciado.';
        } else throw new RuntimeException('Acción no válida.');
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensaje=$e instanceof PDOException?'No se pudo actualizar el carrito. Inténtalo nuevamente.':$e->getMessage();
    }
    $_SESSION['mensaje_carrito']=$mensaje;
    header('Location: carrito.php'); exit;
}
$total_carrito_items = array_sum(array_column($_SESSION['carrito'],'cantidad'));
$total_precio = array_sum(array_map(fn($item) => precio_centimos($item['precio'])*$item['cantidad'],$_SESSION['carrito']))/100;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - TechStore</title>
    <link rel="stylesheet" href="css/tienda.css">
    <style>
        .cart-container {
            max-width: 1000px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .cart-title {
            font-size: 24px;
            color: #1e3c72;
            margin-bottom: 25px;
            font-weight: 700;
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .cart-table th, .cart-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        .cart-table th {
            background-color: #f8f9fa;
            color: #1e3c72;
            font-weight: 600;
        }
        .cart-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .cart-input {
            width: 60px;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: center;
        }
        .cart-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .cart-total-text {
            font-size: 20px;
            font-weight: 700;
            color: #1e3c72;
        }
        .cart-actions {
            display: flex;
            gap: 15px;
        }
        .btn-update {
            background-color: #f39c12;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-update:hover { background-color: #d68910; }
        .btn-empty {
            background-color: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-empty:hover { background-color: #c0392b; }
        .btn-checkout {
            background-color: #27ae60;
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
        }
        .btn-checkout:hover { background-color: #219653; }
        .btn-delete-item {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        .btn-delete-item:hover { text-decoration: underline; }
        .empty-cart {
            text-align: center;
            padding: 50px 0;
            color: #777;
        }
        @media(max-width: 768px) {
            .cart-container { margin: 20px; padding: 15px; }
            .cart-summary { flex-direction: column; gap: 15px; text-align: center; }
        }
    </style>
<link rel="stylesheet" href="css/mejoras.css">
<link rel="stylesheet" href="css/experiencia.css?v=20261014"><script src="js/experiencia.js?v=20261014" defer></script>
</head>
<body>

    <!-- HEADER PÚBLICO -->
    <?php render_store_header('carrito.php'); ?>

    <div class="cart-container">
        <h1 class="cart-title">Tu carrito de compras</h1><?php if ($mensaje): ?><div class="cart-notice" role="status"><?php echo e($mensaje); ?></div><?php endif; ?>

        <?php if (!empty($_SESSION['carrito'])): ?>
            <form action="carrito.php" method="POST">
                <?php echo csrf_campo(); ?>
                <input type="hidden" name="accion" value="actualizar">
                
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Producto</th>
                            <th>Precio Unitario</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['carrito'] as $item): 
                            $subtotal = $item['precio'] * $item['cantidad'];
                        ?>
                            <tr>
                                <td>
                                    <img src="<?php echo e(imagen_producto($item)); ?>" class="cart-img" alt="<?php echo e($item['nombre']); ?>">
                                </td>
                                <td><strong><?php echo htmlspecialchars($item['nombre']); ?></strong></td>
                                <td>S/ <?php echo number_format($item['precio'], 2); ?></td>
                                <td>
                                    <input type="number" name="cantidades[<?php echo $item['id_producto']; ?>]" value="<?php echo $item['cantidad']; ?>" min="1" max="<?php echo $item['stock']; ?>" class="cart-input">
                                </td>
                                <td><strong>S/ <?php echo number_format($subtotal, 2); ?></strong></td>
                                <td>
                                    <button type="submit" name="accion_boton" value="eliminar:<?php echo $item['id_producto']; ?>" class="btn-remove" formnovalidate>Eliminar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-summary">
                    <div class="cart-actions">
                        <button type="submit" class="btn-update">Actualizar Carrito</button>
                        <button type="submit" name="accion_boton" value="vaciar" class="btn-empty" formnovalidate>Vaciar carrito</button>
                    </div>
                    <div class="cart-total-text">
                        Total: S/ <?php echo number_format($total_precio, 2); ?>
                    </div>
                </div>
            </form>

            <div style="text-align: right; margin-top: 20px;">
                <a href="checkout.php" class="btn-checkout">Proceder al Pago &rarr;</a>
            </div>

        <?php else: ?>
            <div class="empty-cart">
                <h3>Tu carrito de compras está vacío.</h3>
                <p style="margin: 15px 0; font-size: 14px;">Explora nuestro catálogo tecnológico y añade productos para comenzar tu compra.</p>
                <a href="tienda.php" class="btn-checkout" style="display: inline-block; margin-top: 10px;">Ir a la Tienda</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>