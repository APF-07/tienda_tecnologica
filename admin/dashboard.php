<?php
require_once '../includes/verificar_admin.php';
require_once '../config/conexion.php';
require_once '../includes/alertas_operativas.php';
try{revisar_alertas_operativas($conexion);}catch(Throwable $e){error_log('No se completó la revisión de alertas operativas.');}

// Consultas estadísticas reales existentes
try {
    $total_productos = $conexion->query("SELECT COUNT(*) FROM productos")->fetchColumn();
    $total_categorias = $conexion->query("SELECT COUNT(*) FROM categorias")->fetchColumn();
    $total_marcas = $conexion->query("SELECT COUNT(*) FROM marcas")->fetchColumn();
    $total_pedidos = $conexion->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();

    // Consulta para el Gráfico 1: Pedidos por Estado
    $stmtEstados = $conexion->query("SELECT estado, COUNT(*) as total FROM pedidos GROUP BY estado");
    $datos_estados = $stmtEstados->fetchAll(PDO::FETCH_ASSOC);

    $estados = [];
    foreach ($datos_estados as $row) {
        $estados[$row['estado']] = (int)$row['total'];
    }

    // Consulta para el Gráfico 2: Métodos de Pago
    $stmtPagos = $conexion->query("SELECT metodo_pago, COUNT(*) as total FROM pedidos GROUP BY metodo_pago");
    $datos_pagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

    $pagos = [];
    foreach ($datos_pagos as $row) {
        $nombrePago = trim($row['metodo_pago'] ?? '') ?: 'No especificado';
        if ($nombrePago === 'Efectivo/Contraentrega') $nombrePago = 'Contraentrega';
        if ($nombrePago === 'Tarjeta') $nombrePago = 'Tarjeta de crédito';
        $pagos[$nombrePago] = ($pagos[$nombrePago] ?? 0) + (int)$row['total'];
    }
    arsort($pagos);
    $stock_bajo = (int)$conexion->query("SELECT COUNT(*) FROM productos WHERE estado = 1 AND stock BETWEEN 0 AND 5")->fetchColumn();
    $ultimos_pedidos = $conexion->query("SELECT id_pedido, fecha_pedido, estado, total FROM pedidos ORDER BY fecha_pedido DESC, id_pedido DESC LIMIT 5")->fetchAll();

} catch (Exception $e) {
    $total_productos = $total_categorias = $total_marcas = $total_pedidos = 0;
    $estados = $pagos = $ultimos_pedidos = [];
    $stock_bajo = 0;
    $error_dashboard = true;
    error_log('Dashboard: '.$e->getMessage());
}
$colores_estados = ['Pendiente'=>'#fbbf24','Confirmado'=>'#38bdf8','En preparación'=>'#a78bfa','Enviado'=>'#2dd4bf','Entregado'=>'#34d399','Cancelado'=>'#fb7185'];
$colores_pago = ['#a78bfa','#38bdf8','#34d399','#fbbf24','#fb7185'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TechStore Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        /* Estilos adicionales para los accesos directos de IA */
        .ai-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .ai-card {
            background: #151B2E;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .ai-card:hover {
            transform: translateY(-4px);
            border-color: #38BDF8;
            box-shadow: 0 6px 20px rgba(56, 189, 248, 0.15);
        }
        .ai-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .ai-card h4 { color: #F8FAFC; margin: 0 0 4px 0; font-size: 15px; }
        .ai-card p { color: #94A3B8; margin: 0; font-size: 12px; }
    </style>
<link rel="stylesheet" href="../css/admin-mejoras.css?v=20261013-menu"><link rel="stylesheet" href="../css/dashboard.css?v=1">
<link rel="stylesheet" href="../css/experiencia.css?v=20261014"><script src="../js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="../css/admin-experiencia.css?v=1"><script src="../js/admin-experiencia.js?v=1" defer></script>
</head>
<body>

    <?php render_admin_sidebar('dashboard'); ?>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Panel de Control</h1>
            <div class="admin-user-info">Gestor: <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></div>
        </header>

        <main class="admin-content">
            <!-- TARJETAS DE ESTADÍSTICAS PRINCIPALES -->
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

            <!-- 🚀 ACCESOS DIRECTOS A LOS 4 MÓDULOS DE INTELIGENCIA ARTIFICIAL -->
            <div style="margin-bottom: 30px;">
                <h2 style="color: #F8FAFC; font-size: 18px; margin-bottom: 15px; font-weight: 700;">🚀 Machine Learning y analítica</h2>

                <div class="ai-grid">
                    <!-- 1. Inventario -->
                    <a href="ml/productos_ia.php" class="ai-card" onmouseover="this.style.borderColor='#38BDF8'">
                        <div class="ai-icon" style="background: rgba(56, 189, 248, 0.15); color: #38BDF8;">📦</div>
                        <div>
                            <h4>Inventario </h4>
                            <p>K-Means y reposición de inventario</p>
                        </div>
                    </a>

                    <!-- 2. Pedidos -->
                    <a href="ml/pedidos_ia.php" class="ai-card" onmouseover="this.style.borderColor='#C084FC'">
                        <div class="ai-icon" style="background: rgba(192, 132, 252, 0.15); color: #C084FC;">📊</div>
                        <div>
                            <h4>Pedidos & Envíos</h4>
                            <p>Pronóstico y riesgo de cancelación</p>
                        </div>
                    </a>

                    <!-- 3. Clientes -->
                    <a href="ml/clientes_ia.php" class="ai-card" onmouseover="this.style.borderColor='#EC4899'">
                        <div class="ai-icon" style="background: rgba(236, 72, 153, 0.15); color: #EC4899;">👥</div>
                        <div>
                            <h4>Clientes</h4>
                            <p>Segmentación RFM con K-Means</p>
                        </div>
                    </a>

                    <!-- 4. Ventas -->
                    <a href="ml/ventas_ia.php" class="ai-card" onmouseover="this.style.borderColor='#10B981'">
                        <div class="ai-icon" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">💰</div>
                        <div>
                            <h4>Ventas confirmadas</h4>
                            <p>Random Forest, Ridge y validación temporal</p>
                        </div>
                    </a>
                </div>
            </div>

            <?php if (!empty($error_dashboard)): ?><div class="alert-error" role="alert">No se pudo cargar el resumen. Intenta recargar la página.</div><?php endif; ?>
            <section class="dashboard-overview" aria-label="Resumen operativo">
                <article class="overview-card">
                    <div class="overview-heading"><div><h2>Estado de los pedidos</h2><p>Distribución de todo el historial</p></div><a href="pedidos/listar.php">Ver pedidos ↗</a></div>
                    <div class="status-summary"><strong><?= number_format((int)$total_pedidos,0,',','.') ?></strong><span>pedidos registrados</span></div>
                    <div class="status-track" aria-hidden="true"><?php foreach($estados as $estado=>$cantidad): ?><span style="width:<?= $total_pedidos ? 100*$cantidad/$total_pedidos : 0 ?>%;background:<?= $colores_estados[$estado] ?? '#94a3b8' ?>"></span><?php endforeach; ?></div>
                    <ul class="status-list"><?php foreach($estados as $estado=>$cantidad): ?><li><span class="overview-dot" style="background:<?= $colores_estados[$estado] ?? '#94a3b8' ?>"></span><span><?= e($estado) ?></span><strong><?= number_format($cantidad,0,',','.') ?></strong><small><?= $total_pedidos ? number_format(100*$cantidad/$total_pedidos,1,',','.') : '0' ?>%</small></li><?php endforeach; ?></ul>
                    <?php if(!$estados): ?><p class="overview-empty">Todavía no hay pedidos registrados.</p><?php endif; ?>
                </article>
                <article class="overview-card">
                    <div class="overview-heading"><div><h2>Métodos de pago</h2><p>Preferencias registradas en los pedidos</p></div></div>
                    <p class="overview-caption">Cantidad de pedidos por método · incluye todos los estados</p>
                    <ul class="payment-list"><?php $i=0; foreach($pagos as $metodo=>$cantidad): $porcentaje=$total_pedidos ? 100*$cantidad/$total_pedidos : 0; ?><li><div><span><?= e($metodo) ?></span><strong><?= number_format($cantidad,0,',','.') ?><small> · <?= $porcentaje > 0 && $porcentaje < 0.1 ? '&lt;0,1' : number_format($porcentaje,1,',','.') ?>%</small></strong></div><div class="payment-track"><span style="width:<?= $porcentaje ?>%;background:<?= $colores_pago[$i++ % count($colores_pago)] ?>"></span></div></li><?php endforeach; ?></ul>
                    <?php if(!$pagos): ?><p class="overview-empty">Los métodos aparecerán al registrar pedidos.</p><?php endif; ?>
                    <p class="overview-footnote">Este resumen muestra el método elegido, no acredita que el pago se haya recibido.</p>
                </article>
            </section>
            <section class="dashboard-overview dashboard-bottom" aria-label="Actividad y pendientes">
                <article class="overview-card recent-orders">
                    <div class="overview-heading"><div><h2>Últimos pedidos</h2><p>Los 5 pedidos más recientes</p></div><a href="pedidos/listar.php">Ver todos ↗</a></div>
                    <div class="recent-list"><?php foreach($ultimos_pedidos as $pedido): ?><a href="pedidos/detalle.php?id=<?= (int)$pedido['id_pedido'] ?>"><div><strong>Pedido #<?= (int)$pedido['id_pedido'] ?></strong><small><?= e(date('d/m/Y · H:i',strtotime($pedido['fecha_pedido']))) ?></small></div><div><strong><?= dinero($pedido['total']) ?></strong><small style="color:<?= $colores_estados[$pedido['estado']] ?? '#94a3b8' ?>"><?= e($pedido['estado']) ?></small></div></a><?php endforeach; ?></div>
                    <?php if(!$ultimos_pedidos): ?><p class="overview-empty">Aquí aparecerá la actividad de tu tienda.</p><?php endif; ?>
                </article>
                <article class="overview-card attention-card">
                    <div class="overview-heading"><div><h2>Por revisar</h2><p>Prioridades de la tienda</p></div></div>
                    <a href="pedidos/listar.php?estado=Pendiente" class="attention-item"><span><strong><?= (int)($estados['Pendiente'] ?? 0) ?></strong> pedidos pendientes</span><span aria-hidden="true">→</span></a>
                    <a href="productos/listar.php?estado=1&amp;orden=stock&amp;stock=bajo" class="attention-item"><span><strong><?= $stock_bajo ?></strong> productos con stock bajo<small>Productos activos con 0 a 5 unidades</small></span><span aria-hidden="true">→</span></a>
                    <a class="overview-analytics" href="ml/pedidos_ia.php">Consultar pronóstico de pedidos ↗</a>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
