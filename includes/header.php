<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Calcular total de items en el carrito para la insignia
$total_carrito_items = isset($_SESSION['carrito']) ? array_sum(array_column($_SESSION['carrito'], 'cantidad')) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'TechStore - Tienda Virtual'; ?></title>
    <link rel="stylesheet" href="css/tienda.css">
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body>

    <!-- HEADER PÚBLICO -->
    <header class="store-header">
        <a href="tienda.php" class="store-logo">TechStore 🚀</a>
        <nav class="store-nav">
            <a href="tienda.php">Catálogo</a>
            <a href="carrito.php">🛒 Carrito <span class="cart-badge"><?php echo $total_carrito_items; ?></span></a>
            <a href="index.php">Admin Panel</a>
        </nav>
    </header>