<?php
session_start();
require_once 'config/conexion.php';

// Capturar filtros de búsqueda o categoría/marca si se envían por GET
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat_filtro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$marca_filtro = isset($_GET['marca']) ? intval($_GET['marca']) : 0;

try {
    // Obtener categorías y marcas para los filtros laterales
    $categorias = $conexion->query("SELECT * FROM categorias WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
    $marcas = $conexion->query("SELECT * FROM marcas WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();

    // Construir consulta dinámica basada en los filtros activos
    $sql = "SELECT p.*, c.nombre AS categoria, m.nombre AS marca 
            FROM productos p 
            INNER JOIN categorias c ON p.id_categoria = c.id_categoria 
            INNER JOIN marcas m ON p.id_marca = m.id_marca 
            WHERE p.estado = 1";
    
    $params = [];

    if (!empty($busqueda)) {
        $sql .= " AND (p.nombre LIKE :busqueda OR p.descripcion LIKE :busqueda OR p.codigo LIKE :busqueda)";
        $params['busqueda'] = "%$busqueda%";
    }

    if ($cat_filtro > 0) {
        $sql .= " AND p.id_categoria = :cat";
        $params['cat'] = $cat_filtro;
    }

    if ($marca_filtro > 0) {
        $sql .= " AND p.id_marca = :marca";
        $params['marca'] = $marca_filtro;
    }

    $sql .= " ORDER BY p.id_producto DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();

} catch (Exception $e) {
    $productos = [];
    $categorias = [];
    $marcas = [];
}

// Contar productos en el carrito para la insignia del header
$total_carrito = isset($_SESSION['carrito']) ? array_sum(array_column($_SESSION['carrito'], 'cantidad')) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda Virtual - TechStore</title>
    <link rel="stylesheet" href="css/tienda.css">
</head>
<body>

    <!-- HEADER PÚBLICO -->
    <header class="store-header">
        <a href="tienda.php" class="store-logo">TechStore 🚀</a>
        <nav class="store-nav">
            <a href="tienda.php" class="active">Catálogo</a>
            <a href="rastrear.php">Rastrear Pedido</a>
            <a href="carrito.php">🛒 Carrito <span class="cart-badge"><?php echo $total_carrito; ?></span></a>
            <a href="index.php">Admin Panel</a>
        </nav>
    </header>

    <!-- CONTENEDOR GENERAL -->
    <div class="store-container">
        
        <!-- SIDEBAR DE FILTROS -->
        <aside class="store-sidebar">
            <div class="filter-group">
                <h3>Categorías</h3>
                <ul class="filter-list">
                    <li><a href="tienda.php" class="<?php echo ($cat_filtro == 0) ? 'active' : ''; ?>">Todas las categorías</a></li>
                    <?php foreach ($categorias as $cat): ?>
                        <li>
                            <a href="tienda.php?categoria=<?php echo $cat['id_categoria']; ?>" class="<?php echo ($cat_filtro == $cat['id_categoria']) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="filter-group">
                <h3>Marcas</h3>
                <ul class="filter-list">
                    <li><a href="tienda.php" class="<?php echo ($marca_filtro == 0) ? 'active' : ''; ?>">Todas las marcas</a></li>
                    <?php foreach ($marcas as $marca): ?>
                        <li>
                            <a href="tienda.php?marca=<?php echo $marca['id_marca']; ?>" class="<?php echo ($marca_filtro == $marca['id_marca']) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($marca['nombre']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>

        <!-- CATÁLOGO PRINCIPAL -->
        <main class="store-catalog">
            <div class="catalog-header">
                <h2>Catálogo de Productos Tecnológicos</h2>
                <form action="tienda.php" method="GET" class="search-box">
                    <input type="text" name="q" placeholder="Buscar productos..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    <?php if ($cat_filtro > 0): ?><input type="hidden" name="categoria" value="<?php echo $cat_filtro; ?>"><?php endif; ?>
                    <?php if ($marca_filtro > 0): ?><input type="hidden" name="marca" value="<?php echo $marca_filtro; ?>"><?php endif; ?>
                    <button type="submit">Buscar</button>
                </form>
            </div>

            <div class="products-grid">
                <?php if (count($productos) > 0): ?>
                    <?php foreach ($productos as $prod): ?>
                        <div class="product-card">
                            <?php if (!empty($prod['imagen']) && file_exists('uploads/productos/' . $prod['imagen'])): ?>
                                <img src="uploads/productos/<?php echo $prod['imagen']; ?>" alt="Producto">
                            <?php else: ?>
                                <img src="uploads/productos/default.png" alt="Sin Imagen">
                            <?php endif; ?>

                            <div class="product-info">
                                <span class="product-brand"><?php echo htmlspecialchars($prod['marca']); ?></span>
                                <h3 class="product-name"><?php echo htmlspecialchars($prod['nombre']); ?></h3>
                                <div class="product-price">$ <?php echo number_format($prod['precio'], 2); ?></div>
                                
                                <div class="product-actions">
                                    <a href="producto.php?id=<?php echo $prod['id_producto']; ?>" class="btn-detail">Ver Detalle</a>
                                    <form action="carrito.php" method="POST" style="flex: 1; display: flex;">
                                        <input type="hidden" name="id_producto" value="<?php echo $prod['id_producto']; ?>">
                                        <input type="hidden" name="accion" value="agregar">
                                        <button type="submit" class="btn-cart">Agregar</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products">
                        <h3>No se encontraron productos disponibles.</h3>
                        <p style="margin-top: 8px; font-size: 13px;">Intenta cambiar los filtros de búsqueda o categoría.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>

    </div>

</body>
</html>