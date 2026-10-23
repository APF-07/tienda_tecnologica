<?php
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
require_once 'config/conexion.php';

try {
    // Obtener categorías y marcas para los filtros laterales
    $categorias =$conexion->query("SELECT * FROM categorias WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
    $marcas =$conexion->query("SELECT * FROM marcas WHERE estado = 1 ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {
    $categorias = [];$marcas = [];
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
<link rel="stylesheet" href="css/mejoras.css">
<link rel="stylesheet" href="css/experiencia.css?v=20261020"><script src="js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="css/catalogo-nuevo.css?v=1"><script src="js/catalogo.js?v=1" defer></script>
</head>
<body class="catalog-page">

    <!-- HEADER PÚBLICO -->
    <?php render_store_header('tienda.php'); ?>

    <section class="catalog-intro">
        <div><span class="eyebrow">TU ESPACIO PARA EXPLORAR TECNOLOGÍA</span><h1>Buenas ideas.<br><span>Mejores equipos.</span></h1><p>Para estudiar, crear o jugar. Encuentra la tecnología que va contigo.</p><a class="catalog-hero-link" href="#catalogo">Explorar productos <span aria-hidden="true">↗</span></a></div>
        <img class="intro-illustration" src="uploads/ilustraciones/hero.svg" alt="Laptop y smartphone de TechStore">
    </section>
    <!-- CONTENEDOR GENERAL -->
    <div class="store-container">
        
        <!-- SIDEBAR DE FILTROS -->
        <aside class="store-sidebar">
            <div class="filters-heading"><h2>Encuentra lo tuyo</h2><span>Filtra tu búsqueda</span></div>
            <details class="filter-group filter-disclosure" open>
                <summary>Categorías</summary>
                <ul class="filter-list" id="lista-categorias">
                    <li><a href="#" data-cat="0" class="filtro-cat active">Todas las categorías</a></li>
                    <?php foreach ($categorias as$cat): ?>
                        <li>
                            <a href="#" data-cat="<?php echo $cat['id_categoria']; ?>" class="filtro-cat">
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>

            <details class="filter-group filter-disclosure" open>
                <summary>Marcas</summary>
                <ul class="filter-list" id="lista-marcas">
                    <li><a href="#" data-marca="0" class="filtro-marca active">Todas las marcas</a></li>
                    <?php foreach ($marcas as$marca): ?>
                        <li>
                            <a href="#" data-marca="<?php echo $marca['id_marca']; ?>" class="filtro-marca">
                                <?php echo htmlspecialchars($marca['nombre']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </aside>

        <!-- CATÁLOGO PRINCIPAL -->
        <main class="store-catalog" id="catalogo">
            <div class="catalog-header">
                <div><span class="catalog-kicker">ELIGE TU PRÓXIMO EQUIPO</span><h2>Explora el catálogo</h2></div>
                <div class="search-box">
                    <label class="sr-only" for="input-busqueda">Buscar productos</label><input type="search" id="input-busqueda" placeholder="Busca tu próximo equipo…">
                </div>
            </div>
            <div class="catalog-controls"><div class="catalog-order"><label for="orden-catalogo">Ordenar por</label><select id="orden-catalogo"><option value="recientes">Más recientes</option><option value="precio_asc">Menor precio</option><option value="precio_desc">Mayor precio</option><option value="nombre">Nombre A–Z</option></select></div><label class="catalog-availability"><input type="checkbox" id="solo-disponibles"> Solo disponibles</label><button type="button" id="limpiar-filtros">Restablecer filtros</button></div>
            <div class="catalog-selection" id="seleccion-catalogo" aria-live="polite"></div>
            <p class="catalog-result-count" id="resumen-catalogo" role="status">Preparando tu catálogo…</p>

            <!-- CONTENEDOR DE PRODUCTOS DINÁMICO -->
            <div class="products-grid" id="contenedor-productos" aria-live="polite" aria-busy="true">
                <!-- Se cargarán vía AJAX -->
            </div>
        </main>

    </div>

    <!-- SCRIPT DE FILTRADO EN TIEMPO REAL (AJAX) -->


<footer class="store-footer"><strong>TechStore</strong><span>Tecnología para estudiar, trabajar y jugar.</span><span>Pedidos sujetos a confirmación de pago.</span></footer>
</body>
</html>
