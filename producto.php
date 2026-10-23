<?php
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
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
                                WHERE p.id_producto = :id AND p.estado = 1 AND c.estado=1 AND m.estado=1 LIMIT 1");
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
<link rel="stylesheet" href="css/mejoras.css">
<link rel="stylesheet" href="css/experiencia.css?v=20261020"><script src="js/experiencia.js?v=20261014" defer></script>
</head>
<body>

    <!-- HEADER PÚBLICO -->
    <?php render_store_header(''); ?>

    <div style="max-width: 1000px; margin: 20px auto 0 auto; padding: 0 20px;">
        <a href="tienda.php" class="btn-back-catalog">&larr; Volver al Catálogo</a>
    </div>

    <!-- CONTENEDOR DE DETALLE -->
    <div class="product-detail-container">
        
        <!-- IMAGEN -->
        <div class="product-detail-img">
            <img src="<?php echo e(imagen_producto($producto)); ?>" alt="<?php echo e($producto['nombre']); ?>">
        </div>

        <!-- INFORMACIÓN -->
        <div class="product-detail-info">
            <div class="detail-brand"><?php echo htmlspecialchars($producto['marca']); ?></div>
            <h1 class="detail-title"><?php echo htmlspecialchars($producto['nombre']); ?></h1>
            
            <div class="detail-meta">
                <span>Categoría: <?php echo htmlspecialchars($producto['categoria']); ?></span>
                <span>Código: <?php echo htmlspecialchars($producto['codigo']); ?></span>
            </div>

            <div class="detail-price">S/ <?php echo number_format($producto['precio'], 2); ?></div>

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
                <?php echo csrf_campo(); ?>
                    <input type="hidden" name="id_producto" value="<?php echo $producto['id_producto']; ?>">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="number" name="cantidad" value="1" min="1" max="<?php echo $producto['stock']; ?>">
                    <button type="submit" class="btn-add-cart">Agregar al carrito</button>
                </form>
            <?php else: ?>
                <p style="color: #e74c3c; font-weight: 600;">Este producto no se encuentra disponible para la compra en este momento.</p>
            <?php endif; ?>
        </div>

    </div>

<?php require __DIR__.'/includes/recomendaciones_producto.php'; ?>
<style>.related-ml{max-width:1200px;margin:35px auto;padding:24px}.related-ml-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}.related-ml-grid a{display:grid;gap:16px;padding:22px;border:1px solid #607b9240;border-radius:18px;color:inherit;text-decoration:none;background:#66899d0c}.related-ml-grid span{color:#168ca7;font-weight:700}.related-ml-grid small{font-size:12px}</style>
</body>
</html>
