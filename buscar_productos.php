<?php
require_once 'config/conexion.php';

$pagina = max(1, min(100000, intval($_GET['pagina'] ?? 1)));
$por_pagina = 24;
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat_filtro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$marca_filtro = isset($_GET['marca']) ? intval($_GET['marca']) : 0;
$ordenes=['recientes'=>'p.id_producto DESC','precio_asc'=>'p.precio,p.id_producto','precio_desc'=>'p.precio DESC,p.id_producto','nombre'=>'p.nombre,p.id_producto'];
$orden=is_string($_GET['orden']??null)?$_GET['orden']:'recientes';
$disponibles=($_GET['disponibles']??'')==='1';

try {
    $sql = "SELECT p.*, c.nombre AS categoria, m.nombre AS marca 
            FROM productos p 
            INNER JOIN categorias c ON p.id_categoria = c.id_categoria 
            INNER JOIN marcas m ON p.id_marca = m.id_marca 
            WHERE p.estado = 1 AND c.estado = 1 AND m.estado = 1";
    
    $params = [];
    if($disponibles)$sql.=' AND p.stock>0';

    if (!empty($busqueda)) {
        $sql .= " AND (p.nombre LIKE :busqueda OR p.descripcion LIKE :descripcion OR p.codigo LIKE :codigo)";
        $params['busqueda'] = "%$busqueda%";
        $params['descripcion'] = "%$busqueda%";
        $params['codigo'] = "%$busqueda%";
    }

    if ($cat_filtro > 0) {
        $sql .= " AND p.id_categoria = :cat";
        $params['cat'] = $cat_filtro;
    }

    if ($marca_filtro > 0) {
        $sql .= " AND p.id_marca = :marca";
        $params['marca'] = $marca_filtro;
    }

    $conteo = $conexion->prepare('SELECT COUNT(*) FROM (' . $sql . ') resultados');
    $conteo->execute($params);
    $total_resultados = (int)$conteo->fetchColumn();
    $paginas = max(1, (int)ceil($total_resultados / $por_pagina));
    $pagina = min($pagina, $paginas);
    $offset = ($pagina - 1) * $por_pagina;
    $sql .= ' ORDER BY '.($ordenes[$orden]??$ordenes['recientes'])." LIMIT $por_pagina OFFSET $offset";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('No se pudo cargar el catálogo.');
}

header('X-Catalog-Total: '.$total_resultados);
header('X-Catalog-Page: '.$pagina);
if (count($productos) > 0) {
    foreach ($productos as $prod) {
        $imagen = imagen_producto($prod);
        echo '<div class="product-card">';
        echo '<div class="product-visual"><span class="catalog-stock-badge '.($prod['stock']<1?'is-empty':($prod['stock']<=5?'is-low':'is-available')).'">'.($prod['stock']<1?'Agotado':($prod['stock']<=5?'Últimas unidades':'Disponible')).'</span>';
        echo '<img loading="lazy" src="' . htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($prod['nombre'], ENT_QUOTES, 'UTF-8') . '">';
        echo '</div>';
        echo '<div class="product-info">';
        echo '<div class="product-classification"><span class="product-brand">' . htmlspecialchars($prod['marca']) . '</span><span class="product-category">'.e($prod['categoria']).'</span></div>';
        echo '<h3 class="product-name">' . htmlspecialchars($prod['nombre']) . '</h3>';
        echo '<div class="product-price">S/ ' . number_format($prod['precio'], 2) . '</div>';
        echo '<p class="product-stock">' . ($prod['stock'] > 0 ? 'Disponible · ' . (int)$prod['stock'] . ' unidades' : 'Agotado') . '</p>';
        echo '<div class="product-actions">';
        echo '<a href="producto.php?id=' . $prod['id_producto'] . '" class="btn-detail">Ver Detalle</a>';
        echo '<form action="carrito.php" method="POST" style="flex: 1; display: flex;">' . csrf_campo();
        echo '<input type="hidden" name="id_producto" value="' . $prod['id_producto'] . '">';
        echo '<input type="hidden" name="accion" value="agregar">';
        echo '<button type="submit" class="btn-cart"' . ($prod['stock'] < 1 ? ' disabled' : '') . '>Agregar</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '<nav class="catalog-pagination" aria-label="Páginas del catálogo">';
    echo '<span>' . $total_resultados . ' productos · Página ' . $pagina . ' de ' . $paginas . '</span>';
    if ($pagina > 1) echo '<button class="btn-detail" type="button" data-pagina="' . ($pagina-1) . '">Anterior</button>';
    if ($pagina < $paginas) echo '<button class="btn-detail" type="button" data-pagina="' . ($pagina+1) . '">Siguiente</button>';
    echo '</nav>';
} else {
    echo '<div class="no-products" style="grid-column: 1 / -1; text-align: center; padding: 40px;">';
    echo '<h3>No se encontraron productos disponibles.</h3>';
    echo '<p style="margin-top: 8px; font-size: 13px; color: #94A3B8;">Intenta cambiar los filtros de búsqueda o categoría.</p>';
    echo '<button type="button" class="btn-detail" data-restablecer>Restablecer filtros</button>';
    echo '</div>';
}
?>
