<?php
session_start();
require_once 'config/conexion.php';

$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_pedido <= 0) {
    header('Location: tienda.php');
    exit();
}

try {
    // Obtener información principal del pedido y del cliente asociado
    $stmtPedido = $conexion->prepare("SELECT p.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.correo, c.telefono, c.direccion 
                                       FROM pedidos p 
                                       INNER JOIN clientes c ON p.id_cliente = c.id_cliente 
                                       WHERE p.id_pedido = :id LIMIT 1");
    $stmtPedido->execute(['id' => $id_pedido]);
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        header('Location: tienda.php');
        exit();
    }

    // Obtener los detalles (productos) de este pedido
    $stmtDetalles = $conexion->prepare("SELECT dp.*, pr.nombre AS producto_nombre, pr.codigo 
                                      FROM detalle_pedidos dp 
                                      INNER JOIN productos pr ON dp.id_producto = pr.id_producto 
                                      WHERE dp.id_pedido = :id");
    $stmtDetalles->execute(['id' => $id_pedido]);
    $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    header('Location: tienda.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Exitoso - TechStore</title>
    <link rel="stylesheet" href="css/tienda.css">
    <style>
        .success-container {
            max-width: 800px;
            margin: 40px auto;
            background: #1E293B;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
            border: 1px solid rgba(255, 255, 255, 0.04);
            text-align: center;
            color: #F8FAFC;
        }
        .success-icon {
            font-size: 50px;
            color: #34D399;
            margin-bottom: 15px;
        }
        .success-title {
            font-size: 26px;
            color: #F8FAFC;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .success-subtitle {
            font-size: 15px;
            color: #94A3B8;
            margin-bottom: 30px;
        }
        .tracking-box {
            background: #0F172A;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(56, 189, 248, 0.2);
            box-shadow: inset 0 0 15px rgba(56, 189, 248, 0.05);
            text-align: center;
        }
        .order-info-card {
            background: #0F172A;
            border-radius: 10px;
            padding: 20px;
            text-align: left;
            margin-bottom: 30px;
            border-left: 4px solid #38BDF8;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        .order-info-card h3 {
            font-size: 16px;
            color: #38BDF8;
            margin-bottom: 12px;
        }
        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            font-size: 14px;
            color: #CBD5E1;
        }
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            text-align: left;
            background: #0F172A;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        .order-table th, .order-table td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 14px;
            color: #F8FAFC;
        }
        .order-table th {
            background-color: #1E293B;
            color: #94A3B8;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        .order-total-box {
            text-align: right;
            font-size: 18px;
            font-weight: 700;
            color: #34D399;
            margin-bottom: 30px;
        }
        .actions-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .btn-return {
            background: linear-gradient(135deg, #38BDF8, #2563EB);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.25);
            transition: all 0.3s;
        }
        .btn-return:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(56, 189, 248, 0.35); 
        }
        .btn-whatsapp {
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            transition: all 0.3s;
        }
        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 211, 102, 0.4);
        }
    </style>
</head>
<body>

    <!-- HEADER PÚBLICO -->
    <header class="store-header">
        <a href="tienda.php" class="store-logo">TechStore 🚀</a>
        <nav class="store-nav">
            <a href="tienda.php">Catálogo</a>
            <a href="rastrear.php">Rastrear Pedido</a>
            <a href="carrito.php">🛒 Carrito</a>
            <a href="admin/dashboard.php">Admin Panel</a>
        </nav>
    </header>

    <div class="success-container">
        <div class="success-icon">✅</div>
        <h1 class="success-title">¡Pedido Registrado con Éxito!</h1>
        <p class="success-subtitle">Gracias por tu compra. Hemos registrado tu orden correctamente y pronto nos pondremos en contacto contigo.</p>

        <!-- TARJETA DESTACADA DEL ID DE SEGUIMIENTO -->
        <div class="tracking-box">
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #94A3B8; display: block; margin-bottom: 6px;">Tu Número de Seguimiento</span>
            <div style="font-size: 36px; font-weight: 800; color: #38BDF8; letter-spacing: 1px; margin-bottom: 10px;">
                #<?php echo $pedido['id_pedido']; ?>
            </div>
            <p style="font-size: 13.5px; color: #CBD5E1; max-width: 500px; margin: 0 auto; line-height: 1.5;">
                ⚠️ <strong>Guarda muy bien este número.</strong> Lo necesitarás en la sección <a href="rastrear.php" style="color: #38BDF8; text-decoration: underline;">Rastrear Pedido</a> para consultar el estado de tu entrega en cualquier momento.
            </p>
        </div>

        <!-- TARJETA DE DATOS DEL PEDIDO -->
        <div class="order-info-card">
            <h3>Detalles de la Orden #<?php echo $pedido['id_pedido']; ?></h3>
            <div class="order-info-grid">
                <div><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nombre'] . ' ' . $pedido['cliente_apellido']); ?></div>
                <div><strong>Correo:</strong> <?php echo htmlspecialchars($pedido['correo']); ?></div>
                <div><strong>Teléfono:</strong> <?php echo htmlspecialchars($pedido['telefono']); ?></div>
                <div><strong>Fecha:</strong> <?php echo $pedido['fecha_pedido']; ?></div>
                <div style="grid-column: 1 / -1;"><strong>Datos de Envío:</strong> <?php echo htmlspecialchars($pedido['direccion']); ?></div>
                <div><strong>Estado Actual:</strong> <span style="color: #FBBF24; font-weight: 600;"><?php echo $pedido['estado']; ?></span></div>
            </div>
        </div>

        <!-- TABLA DE PRODUCTOS COMPRADOS -->
        <h3 style="text-align: left; font-size: 16px; color: #38BDF8; margin-bottom: 12px;">Productos Adquiridos</h3>
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
                        <td><code style="color: #38BDF8;"><?php echo htmlspecialchars($det['codigo']); ?></code></td>
                        <td><strong><?php echo htmlspecialchars($det['producto_nombre']); ?></strong></td>
                        <td><?php echo $det['cantidad']; ?></td>
                        <td>$ <?php echo number_format($det['precio_unitario'], 2); ?></td>
                        <td><strong style="color: #34D399;">$ <?php echo number_format($det['subtotal'], 2); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="order-total-box">
            Total Cancelado: $ <?php echo number_format($pedido['total'], 2); ?>
        </div>

        <!-- BOTONES DE ACCIÓN (WHATSAPP AL CLIENTE + VOLVER) -->
        <?php
            // 1. Tomar y limpiar el teléfono que ingresó el cliente en el formulario
            $telefono_cliente = preg_replace('/[^0-9]/', '', $pedido['telefono']);

            // Si el número tiene 9 dígitos (formato local de Perú), le agregamos el código de país '51' automáticamente
            if (strlen($telefono_cliente) == 9) {
                $telefono_cliente = "51" . $telefono_cliente;
            }

            // 2. Redactar el mensaje en texto plano ordenado (sin asteriscos para evitar símbolos raros en WhatsApp)
            $texto_mensaje = "¡Hola, " . $pedido['cliente_nombre'] . "! Gracias por tu compra en TechStore.\n\n" .
                             "Aquí tienes el resumen de tu pedido:\n" .
                             "Orden: #" . $pedido['id_pedido'] . "\n" .
                             "Total: $" . number_format($pedido['total'], 2) . "\n" .
                             "Direccion: " . $pedido['direccion'] . "\n\n" .
                             "Guarda este número de orden para realizar tu rastreo en nuestra web. ¡Pronto nos comunicaremos contigo!";

            // 3. Generar el enlace oficial de WhatsApp con codificación segura
            $link_whatsapp = "https://wa.me/{$telefono_cliente}?text=" . rawurlencode($texto_mensaje);
        ?>

        <div class="actions-container">
            <a href="<?php echo $link_whatsapp; ?>" target="_blank" class="btn-whatsapp">
                💬 Guardar Comprobante en mi WhatsApp
            </a>
            <a href="tienda.php" class="btn-return">Volver a la Tienda</a>
        </div>
    </div>

</body>
</html>