<?php
require_once '../includes/verificar_admin.php';
require_once '../config/conexion.php';

// Consultas estadísticas reales existentes
try {
    $total_productos = $conexion->query("SELECT COUNT(*) FROM productos")->fetchColumn();
    $total_categorias = $conexion->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
    $total_marcas = $conexion->query("SELECT COUNT(*) FROM marcas")->fetchColumn();
    $total_pedidos = $conexion->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
} catch (Exception $e) {
    $total_productos = $total_categorias = $total_marcas = $total_pedidos = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TechStore Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>

    <aside class="admin-sidebar">
        <div class="sidebar-brand">TechStore</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 <span>Dashboard</span></a></li>
            <li><a href="productos/listar.php">📦 <span>Productos</span></a></li>
            <li><a href="categorias/listar.php">🗂️ <span>Categorías</span></a></li>
            <li><a href="marcas/listar.php">🏷️ <span>Marcas</span></a></li>
            <li><a href="pedidos/listar.php">🛒 <span>Pedidos</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Panel de Control</h1>
            <div class="admin-user-info">Gestor: <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></div>
        </header>

        <main class="admin-content">
            <div class="dashboard-cards">
                <div class="card-stat">
                    <h3>Total Productos</h3>
                    <div class="number"><?php echo $total_productos; ?></div>
                </div>
                <div class="card-stat">
                    <h3>Categorías</h3>
                    <div class="number"><?php echo $total_categorias; ?></div>
                </div>
                <div class="card-stat">
                    <h3>Marcas</h3>
                    <div class="number"><?php echo $total_marcas; ?></div>
                </div>
                <div class="card-stat">
                    <h3>Pedidos Totales</h3>
                    <div class="number"><?php echo $total_pedidos; ?></div>
                </div>
            </div>

            <div class="form-container">
                <h2 style="font-size: 18px; margin-bottom: 10px; color: #F8FAFC;">Bienvenido al Sistema TechStore PRO</h2>
                <p style="color: #94A3B8; font-size: 14px; line-height: 1.6;">Desde este panel central puedes administrar de manera eficiente todo el inventario, verificar el flujo de pedidos de los clientes y controlar las categorías y marcas activas en la tienda virtual.</p>
            </div>
        </main>
    </div>

</body>
</html>