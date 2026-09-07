<?php
session_start();
require_once 'config/conexion.php';

$id_producto = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_producto <= 0) {
    header('Location: tienda.php');
    exit();
}

try {
    // Obtener información detallada del producto incluyendo categoría y marca
    $stmt = $conexion->prepare("SELECT p.*, c.nombre AS categoria, m.nombre AS marca 
                                FROM productos p 
                                INNER JOIN categorias c ON p.id_categoria = c.id_categoria 
                                INNER JOIN marcas m ON p.id_marca = m.id_marca 
                                WHERE p.id_producto = :id AND p.estado = 1 LIMIT 1");
    $stmt->execute(['id' => $id_producto]);
    $producto = $stmt->fetch();

    if (!$producto) {
        header('Location: tienda.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: tienda.php');
    exit();
}

// Conteo de items en el carrito para el badge del header
$total_carrito = isset($_SESSION['carrito']) ? array_sum(array_column($_SESSION['carrito'], 'cantidad')) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($producto['nombre']); ?> - TechStore</title>
    <link rel="stylesheet" href="css/tienda.css">
    <style>
        .product-detail-container {
            max-width: 1000px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            overflow: hidden;
        }
        .product-detail-img {
            flex: 1;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            border-right: 1px solid #eee;
        }
        .product-detail-img img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 6px;
        }
        .product-detail-info {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .detail-brand {
            font-size: 13px;
            color: #888;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .detail-title {
            font-size: 26px;
            font-weight: 700;
            color: #1e3c72;
            margin-bottom: 12px;
        }
        .detail-meta {
            font-size: 14px;
            color: #555;
            margin-bottom: 15px;
        }
        .detail-meta span {
            background: #f1f3f5;
            padding: 4px 10px;
            border-radius: 4px;
            margin-right: 8px;
            font-weight: 600;
        }
        .detail-price {
            font-size: 24px;
            font-weight: 700;
            color: #27ae60;
            margin-bottom: 15px;
        }
        .detail-stock {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .detail-desc {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .cart-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .cart-form input[type="number"] {
            width: 70px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            text-align: center;
        }
        .btn-add-cart {
            background-color: #1e3c72;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-add-cart:hover {
            background-color: #162b50;
        }
        .btn-back-catalog {
            display: inline-block;
            margin: 20px auto 0 auto;
            color: #1e3c72;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-back-catalog:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .product-detail-container {
                flex-direction: column;
                margin: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- HEADER PÚBLICO -->
    <header class="store-header">
        <a href="tienda.php" class="store-logo">TechStore 🚀</a>
        <nav class="store-nav">
            <a href="tienda.php">Catálogo</a>
            <a href="carrito.php">🛒 Carrito <span class="cart-badge"><?php echo $total_carrito; ?></span></a>
            <a href="index.php">Admin Panel</a>
        </nav>
    </header>

    <div style="max-width: 1000px; margin: 20px auto 0 auto; padding: 0 20px;">
        <a href="tienda.php" class="btn-back-catalog">&larr; Volver al Catálogo</a>
    </div>

    <!-- CONTENEDOR DE DETALLE -->
    <div class="product-detail-container">
        
        <!-- IMAGEN -->
        <div class="product-detail-img">
            <?php if (!empty($producto['imagen']) && file_exists('uploads/productos/' . $producto['imagen'])): ?>
                <img src="uploads/productos/<?php echo $producto['imagen']; ?>" alt="Imagen del producto">
            <?php else: ?>
                <img src="uploads/productos/default.png" alt="Sin Imagen">
            <?php endif; ?>
        </div>

        <!-- INFORMACIÓN -->
        <div class="product-detail-info">
            <div class="detail-brand"><?php echo htmlspecialchars($producto['marca']); ?></div>
            <h1 class="detail-title"><?php echo htmlspecialchars($producto['nombre']); ?></h1>
            
            <div class="detail-meta">
                <span>Categoría: <?php echo htmlspecialchars($producto['categoria']); ?></span>
                <span>Código: <?php echo htmlspecialchars($producto['codigo']); ?></span>
            </div>

            <div class="detail-price">$ <?php echo number_format($producto['precio'], 2); ?></div>

            <div class="detail-stock">
                Disponibilidad: 
                <?php if ($producto['stock'] > 0): ?>
                    <span style="color: #27ae60;"><?php echo $producto['stock']; ?> unidades en stock</span>
                <?php else: ?>
                    <span style="color: #e74c3c;">Agotado temporalmente</span>
                <?php endif; ?>
            </div>

            <div class="detail-desc">
                <strong>Descripción:</strong><br>
                <?php echo nl2br(htmlspecialchars($producto['descripcion'] ? $producto['descripcion'] : 'Sin descripción detallada disponible para este producto.')); ?>
            </div>

            <?php if ($producto['stock'] > 0): ?>
                <form action="carrito.php" method="POST" class="cart-form">
                    <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="number" name="cantidad" value="1" min="1" max="<?php echo $producto['stock']; ?>">
                    <button type="submit" class="btn-add-cart">Agregar al Carrito</button>
                </form>
            <?php else: ?>
                <p style="color: #e74c3c; font-weight: 600;">Este producto no se encuentra disponible para la compra en este momento.</p>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>