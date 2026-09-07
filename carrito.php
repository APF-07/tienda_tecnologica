<?php
session_start();
require_once 'config/conexion.php';

// Inicializar el carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Capturar acciones enviadas por POST o GET
$accion = isset($_POST['accion']) ? $_POST['accion'] : (isset($_GET['accion']) ? $_GET['accion'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($accion)) {

    // 1. ACCIÓN: AGREGAR AL CARRITO
    if ($accion === 'agregar') {
        $id_producto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
        $cantidad_solicitada = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 1;

        if ($id_producto > 0 && $cantidad_solicitada > 0) {
            try {
                // Verificar datos y stock real en la base de datos
                $stmt = $conexion->prepare("SELECT * FROM productos WHERE id_producto = :id AND estado = 1 LIMIT 1");
                $stmt->execute(['id' => $id_producto]);
                $producto = $stmt->fetch();

                if ($producto) {
                    $stock_disponible = $producto['stock'];

                    // Calcular cantidad actual en el carrito para este producto
                    $cantidad_en_carrito = isset($_SESSION['carrito'][$id_producto]) ? $_SESSION['carrito'][$id_producto]['cantidad'] : 0;
                    $nueva_cantidad = $cantidad_en_carrito + $cantidad_solicitada;

                    // Validar que no supere el stock disponible
                    if ($nueva_cantidad <= $stock_disponible) {
                        if (isset($_SESSION['carrito'][$id_producto])) {
                            $_SESSION['carrito'][$id_producto]['cantidad'] = $nueva_cantidad;
                        } else {
                            $_SESSION['carrito'][$id_producto] = [
                                'id_producto' => $producto['id_producto'],
                                'nombre' => $producto['nombre'],
                                'precio' => $producto['precio'],
                                'imagen' => $producto['imagen'],
                                'cantidad' => $cantidad_solicitada,
                                'stock' => $stock_disponible
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                // Error silencioso de base de datos en carrito
            }
        }
        header('Location: carrito.php');
        exit();
    }

    // 2. ACCIÓN: ACTUALIZAR CANTIDADES
    if ($accion === 'actualizar') {
        if (isset($_POST['cantidades']) && is_array($_POST['cantidades'])) {
            foreach ($_POST['cantidades'] as $id_producto => $cantidad) {
                $id_producto = intval($id_producto);
                $cantidad = intval($cantidad);

                if (isset($_SESSION['carrito'][$id_producto])) {
                    if ($cantidad <= 0) {
                        unset($_SESSION['carrito'][$id_producto]);
                    } else {
                        // Validar contra stock máximo almacenado
                        $stock_max = $_SESSION['carrito'][$id_producto]['stock'];
                        if ($cantidad <= $stock_max) {
                            $_SESSION['carrito'][$id_producto]['cantidad'] = $cantidad;
                        } else {
                            $_SESSION['carrito'][$id_producto]['cantidad'] = $stock_max; // Limitar al stock máximo
                        }
                    }
                }
            }
        }
        header('Location: carrito.php');
        exit();
    }

    // 3. ACCIÓN: ELIMINAR UN PRODUCTO
    if ($accion === 'eliminar') {
        $id_producto = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id_producto > 0 && isset($_SESSION['carrito'][$id_producto])) {
            unset($_SESSION['carrito'][$id_producto]);
        }
        header('Location: carrito.php');
        exit();
    }

    // 4. ACCIÓN: VACIAR CARRITO
    if ($accion === 'vaciar') {
        unset($_SESSION['carrito']);
        header('Location: carrito.php');
        exit();
    }
}

// Calcular total general de productos en el carrito
$total_carrito_items = isset($_SESSION['carrito']) ? array_sum(array_column($_SESSION['carrito'], 'cantidad')) : 0;
$total_precio = 0;
if (isset($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $item) {
        $total_precio += ($item['precio'] * $item['cantidad']);
    }
}
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
</head>
<body>

    <!-- HEADER PÚBLICO -->
    <header class="store-header">
        <a href="tienda.php" class="store-logo">TechStore 🚀</a>
        <nav class="store-nav">
            <a href="tienda.php">Catálogo</a>
            <a href="carrito.php" class="active">🛒 Carrito <span class="cart-badge"><?php echo $total_carrito_items; ?></span></a>
            <a href="index.php">Admin Panel</a>
        </nav>
    </header>

    <div class="cart-container">
        <h1 class="cart-title">Tu Carrito de Compras</h1>

        <?php if (!empty($_SESSION['carrito'])): ?>
            <form action="carrito.php" method="POST">
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
                                    <?php if (!empty($item['imagen']) && file_exists('uploads/productos/' . $item['imagen'])): ?>
                                        <img src="uploads/productos/<?php echo $item['imagen']; ?>" class="cart-img" alt="Prod">
                                    <?php else: ?>
                                        <img src="uploads/productos/default.png" class="cart-img" alt="S/I">
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($item['nombre']); ?></strong></td>
                                <td>$ <?php echo number_format($item['precio'], 2); ?></td>
                                <td>
                                    <input type="number" name="cantidades[<?php echo $item['id_producto']; ?>]" value="<?php echo $item['cantidad']; ?>" min="1" max="<?php echo $item['stock']; ?>" class="cart-input">
                                </td>
                                <td><strong>$ <?php echo number_format($subtotal, 2); ?></strong></td>
                                <td>
                                    <a href="carrito.php?accion=eliminar&id=<?php echo $item['id_producto']; ?>" class="btn-delete-item">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-summary">
                    <div class="cart-actions">
                        <button type="submit" class="btn-update">Actualizar Carrito</button>
                        <a href="carrito.php?accion=vaciar" class="btn-empty" onclick="return confirm('¿Vaciar todo el carrito?');">Vaciar Carrito</a>
                    </div>
                    <div class="cart-total-text">
                        Total: $ <?php echo number_format($total_precio, 2); ?>
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