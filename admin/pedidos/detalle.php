<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';

$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pedido <= 0) {
    header('Location: listar.php');
    exit();
}

try {
    // Obtener información del pedido y del cliente
    $stmtPedido = $conexion->prepare("SELECT p.*, COALESCE(p.cliente_nombre, c.nombre) AS cliente_nombre, COALESCE(p.cliente_apellido, c.apellido) AS cliente_apellido, COALESCE(p.cliente_correo, c.correo) AS correo, COALESCE(p.cliente_telefono, c.telefono) AS telefono, COALESCE(p.cliente_direccion, c.direccion) AS direccion
                                       FROM pedidos p 
                                       INNER JOIN clientes c ON p.id_cliente = c.id_cliente 
                                       WHERE p.id_pedido = :id LIMIT 1");
    $stmtPedido->execute(['id' => $id_pedido]);
    $pedido = $stmtPedido->fetch();

    if (!$pedido) {
        header('Location: listar.php');
        exit();
    }

    // Obtener productos de este pedido
    $stmtDetalles = $conexion->prepare("SELECT dp.*, pr.nombre AS producto_nombre, pr.codigo 
                                       FROM detalle_pedidos dp 
                                       INNER JOIN productos pr ON dp.id_producto = pr.id_producto 
                                       WHERE dp.id_pedido = :id");
    $stmtDetalles->execute(['id' => $id_pedido]);
    $detalles = $stmtDetalles->fetchAll();

} catch (Exception $e) {
    header('Location: listar.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido #<?php echo $id_pedido; ?> - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .detail-card {
            background: #151B2E;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
            border: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 25px;
            color: #F8FAFC;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            font-size: 14px;
            color: #CBD5E1;
            margin-bottom: 25px;
        }
        .order-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #151B2E;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .order-table th, .order-table td {
            padding: 14px 18px;
            text-align: left;
            font-size: 14px;
        }
        .order-table th {
            background-color: rgba(11, 16, 32, 0.5);
            color: #94A3B8;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .order-table td {
            color: #F8FAFC;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }
        .status-form {
            display: flex;
            gap: 15px;
            align-items: center;
            background: #0B1020;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .status-form select {
            padding: 12px 16px;
            background-color: #151B2E !important;
            color: #F8FAFC !important;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            font-size: 14px;
            flex: 1;
            outline: none;
        }
        .btn-update-status {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transition: all 0.3s ease;
        }
        .btn-update-status:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4); 
        }
        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            color: #38BDF8;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s;
        }
        .btn-back:hover { color: #7DD3FC; text-decoration: underline; }
        
        /* Estilos para el contenedor del mapa de ruta */
        .map-container {
            margin-top: 20px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: #0B1020;
        }
        .map-container iframe {
            width: 100%;
            height: 350px;
            border: 0;
            display: block;
        }
    </style>
<link rel="stylesheet" href="../../css/admin-mejoras.css?v=20261013-menu">
<link rel="stylesheet" href="../../css/experiencia.css?v=20261014"><script src="../../js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="../../css/admin-experiencia.css?v=1"><script src="../../js/admin-experiencia.js?v=1" defer></script>
</head>
<body>

    <?php render_admin_sidebar('pedidos'); ?>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Detalle del Pedido #<?php echo $pedido['id_pedido']; ?></h1>
            <div class="admin-user-info">Admin: <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong></div>
        </header>

        <main class="admin-content">
            <a href="listar.php" class="btn-back">&larr; Volver al Listado de Pedidos</a>

            <!-- INFORMACIÓN GENERAL -->
            <div class="detail-card">
                <h3 style="font-size: 16px; color: #22D3EE; margin-bottom: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Información del Cliente y Envío</h3>
                <div class="detail-grid">
                    <div><strong>Cliente:</strong> <span style="color: #F8FAFC;"><?php echo htmlspecialchars($pedido['cliente_nombre'] . ' ' . $pedido['cliente_apellido']); ?></span></div>
                    <div><strong>Correo:</strong> <span style="color: #F8FAFC;"><?php echo htmlspecialchars($pedido['correo']); ?></span></div>
                    <div><strong>Teléfono:</strong> <span style="color: #F8FAFC;"><?php echo htmlspecialchars($pedido['telefono']); ?></span></div>
                    <div><strong>Fecha del Pedido:</strong> <span style="color: #F8FAFC;"><?php echo $pedido['fecha_pedido']; ?></span></div>
                    <div style="grid-column: 1 / -1;"><strong>Datos de Envío:</strong> <span style="color: #F8FAFC;"><?php echo htmlspecialchars($pedido['direccion']); ?></span></div>

<!-- NUEVOS CAMPOS DE PAGO -->
<div style="grid-column: 1 / -1; background: rgba(34, 211, 238, 0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(34, 211, 238, 0.2); margin-top: 10px; display: flex; gap: 30px; align-items: center;">
    <div>
        <strong style="color: #22D3EE;">Método de Pago:</strong>
        <span style="color: #F8FAFC;"><?php echo htmlspecialchars($pedido['metodo_pago'] ?? 'No registrado'); ?></span>
    </div>
    <div>
        <strong style="color: #22D3EE;">N° Operación:</strong>
        <?php if (!empty($pedido['codigo_operacion'])): ?>
            <span style="color: #10B981; font-weight: bold; font-size: 15px; letter-spacing: 1px; background: rgba(16, 185, 129, 0.1); padding: 4px 10px; border-radius: 6px;">
                <?php echo htmlspecialchars($pedido['codigo_operacion']); ?>
            </span>
        <?php else: ?>
            <span style="color: #94A3B8;">No aplica (Contraentrega)</span>
        <?php endif; ?>
    </div>
</div>
                </div>

                <!-- GOOGLE MAPS CON EL RECORRIDO (DESDE LA TIENDA HASTA EL CLIENTE) -->
                <h3 style="font-size: 14px; color: #22D3EE; margin-bottom: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Recorrido de Entrega (Tienda &rarr; Cliente)</h3>
                <div class="map-container">
                    <?php 
                        // Origen fijo: Tu tienda tecnológica en Cerro de Pasco
                        $origen_tienda = "Av. Circunvalacion Arenales Mz.P Lt.8, Cerro de Pasco";
                        
                        // Extraemos solo la parte de la dirección usando una expresión regular
                        // Esto busca lo que esté después de "Envío:" o toma la cadena limpia si no tiene ese formato
                        $texto_envio = $pedido['direccion'];
                        if (preg_match('/(?:^|\|)\s*Dir:\s*(.*)$/iu', $texto_envio, $coincidencias)) {
                            $destino_limpio = trim($coincidencias[1]);
                        } else {
                            // Si por alguna razón no tiene la etiqueta, limpiamos por si acaso el DNI inicial
                            $destino_limpio = preg_replace('/^DNI:\s*\d+\s*\|\s*/i', '', $texto_envio);
                        }
                        
                        // Codificamos ambas variables para evitar errores en la URL
                        $saddr = urlencode($origen_tienda);
                        $daddr = urlencode($destino_limpio);
                    ?>
                    <iframe 
                        src="https://maps.google.com/maps?saddr=<?php echo $saddr; ?>&daddr=<?php echo $daddr; ?>&output=embed" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>

                <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.08); margin: 25px 0;">

                <!-- CAMBIAR ESTADO -->
                <h3 style="font-size: 14px; color: #94A3B8; margin-bottom: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Actualizar Estado del Pedido</h3>
                <form action="cambiar_estado.php" method="POST" class="status-form">
                <?php echo csrf_campo(); ?>
                    <input type="hidden" name="id_pedido" value="<?php echo $pedido['id_pedido']; ?>">
                    <select name="estado">
                        <?php 
                        $estados = ['Pendiente', 'Confirmado', 'En preparación', 'Enviado', 'Entregado', 'Cancelado'];
                        foreach ($estados as $est): 
                        ?>
                            <option value="<?php echo $est; ?>" <?php echo ($pedido['estado'] === $est) ? 'selected' : ''; ?>>
                                <?php echo $est; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-update-status">Cambiar Estado</button>
                </form>
                <small style="color: #94A3B8; display: block; margin-top: 10px;">* Nota: Si cambias el estado a <strong style="color: #F87171;">Cancelado</strong>, el stock de los productos se repondrá automáticamente.</small>
            </div>

            <!-- PRODUCTOS DEL PEDIDO -->
            <div class="detail-card">
                <h3 style="font-size: 16px; color: #22D3EE; margin-bottom: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Productos Incluidos en la Orden</h3>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $det): ?>
                            <tr>
                                <td><code style="color: #38BDF8; background: rgba(56, 189, 248, 0.1); padding: 3px 6px; border-radius: 4px;"><?php echo htmlspecialchars($det['codigo']); ?></code></td>
                                <td><strong style="color: #F8FAFC;"><?php echo htmlspecialchars($det['producto_nombre']); ?></strong></td>
                                <td style="color: #CBD5E1;"><?php echo $det['cantidad']; ?></td>
                                <td style="color: #CBD5E1;">S/ <?php echo number_format($det['precio_unitario'], 2); ?></td>
                                <td><strong style="color: #34D399;">S/ <?php echo number_format($det['subtotal'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: right; font-size: 18px; font-weight: 700; color: #34D399; margin-top: 15px;">
                    Total General: S/ <?php echo number_format($pedido['total'], 2); ?>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
