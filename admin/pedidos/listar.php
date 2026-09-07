<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

try {
    // Consulta con JOIN para obtener los datos básicos del cliente y el pedido
    $sql = "SELECT p.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.correo 
            FROM pedidos p 
            INNER JOIN clientes c ON p.id_cliente = c.id_cliente 
            ORDER BY p.id_pedido DESC";
    $stmt = $conexion->query($sql);
    $pedidos = $stmt->fetchAll();
} catch (Exception $e) {
    $pedidos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .btn-action { padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; background-color: #1e3c72; color: #fff; }
        .btn-action:hover { background-color: #162b50; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; vertical-align: middle; }
        th { background-color: #f8f9fa; color: #1e3c72; font-weight: 600; }
        .badge-estado { padding: 5px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; display: inline-block; }
        .estado-Pendiente { background: #fff3cd; color: #856404; }
        .estado-Confirmado { background: #cce5ff; color: #004085; }
        .estado-Preparacion { background: #d1ecf1; color: #0c5460; }
        .estado-Enviado { background: #d4edda; color: #155724; }
        .estado-Entregado { background: #d4edda; color: #155724; }
        .estado-Cancelado { background: #f8d7da; color: #721c24; }
        .alert-success { background: #e8f8f5; color: #27ae60; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fde8e8; color: #c0392b; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <div class="sidebar-brand">TechStore Admin</div>
        <ul class="sidebar-menu">
            <li><a href="../dashboard.php">📊 Dashboard</a></li>
            <li><a href="../productos/listar.php">📦 Productos</a></li>
            <li><a href="../categorias/listar.php">🗂️ Categorías</a></li>
            <li><a href="../marcas/listar.php">🏷️ Marcas</a></li>
            <li><a href="listar.php" class="active">🛒 Pedidos</a></li>
        </ul>
        <div class="sidebar-footer">
            <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Gestión de Pedidos</h1>
            <div class="admin-user-info">Admin: <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></div>
        </header>

        <main class="admin-content">
            <?php if (!empty($mensaje)): ?>
                <div class="alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>ID Pedido</th>
                        <th>Cliente</th>
                        <th>Correo</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pedidos) > 0): ?>
                        <?php foreach ($pedidos as $ped): 
                            // Clase limpia para las insignias de estado
                            $estado_clase = str_replace(' ', '', $ped['estado']);
                        ?>
                            <tr>
                                <td><strong>#<?php echo $ped['id_pedido']; ?></strong></td>
                                <td><?php echo htmlspecialchars($ped['cliente_nombre'] . ' ' . $ped['cliente_apellido']); ?></td>
                                <td><?php echo htmlspecialchars($ped['correo']); ?></td>
                                <td><?php echo $ped['fecha_pedido']; ?></td>
                                <td><strong>$ <?php echo number_format($ped['total'], 2); ?></strong></td>
                                <td>
                                    <span class="badge-estado estado-<?php echo $estado_clase; ?>">
                                        <?php echo $ped['estado']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="detalle.php?id=<?php echo $ped['id_pedido']; ?>" class="btn-action">Ver Detalle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #777;">No hay pedidos registrados en la tienda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>

</body>
</html>