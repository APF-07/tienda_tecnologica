<?php
session_start();
require_once 'config/conexion.php';

$pedido = null;
$detalles = [];
$error = '';

if (isset($_GET['id_pedido'])) {
    $id_pedido = intval($_GET['id_pedido']);
    
    if ($id_pedido > 0) {
        try {
            // Buscar la información principal del pedido y del cliente
            $stmtPedido = $conexion->prepare("SELECT p.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.correo, c.telefono, c.direccion 
                                               FROM pedidos p 
                                               INNER JOIN clientes c ON p.id_cliente = c.id_cliente 
                                               WHERE p.id_pedido = :id LIMIT 1");
            $stmtPedido->execute(['id' => $id_pedido]);
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

            if ($pedido) {
                // Obtener los productos asociados a este pedido
                $stmtDetalles = $conexion->prepare("SELECT dp.*, pr.nombre AS producto_nombre, pr.codigo 
                                                  FROM detalle_pedidos dp 
                                                  INNER JOIN productos pr ON dp.id_producto = pr.id_producto 
                                                  WHERE dp.id_pedido = :id");
                $stmtDetalles->execute(['id' => $id_pedido]);
                $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $error = "No se encontró ningún pedido con el ID #{$id_pedido}. Verifica el número e intenta nuevamente.";
            }
        } catch (Exception $e) {
            $error = "Ocurrió un error al consultar el pedido. Inténtalo más tarde.";
        }
    } else {
        $error = "Por favor, ingresa un número de pedido válido.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastrear Pedido - TechStore</title>
    <link rel="stylesheet" href="css/tienda.css">
    <style>
        .tracker-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .tracker-card {
            background: #1E293B;
            border-radius: 12px;
            padding: 35px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
            border: 1px solid rgba(255, 255, 255, 0.04);
            color: #F8FAFC;
            margin-bottom: 30px;
        }
        .tracker-title {
            font-size: 24px;
            font-weight: 700;
            color: #F8FAFC;
            margin-bottom: 8px;
            text-align: center;
        }
        .tracker-subtitle {
            font-size: 14.5px;
            color: #94A3B8;
            margin-bottom: 25px;
            text-align: center;
        }
        .search-form {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .search-form input {
            flex: 1;
            padding: 12px 16px;
            background: #0F172A;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #F8FAFC;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .search-form input:focus {
            outline: none;
            border-color: #38BDF8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }
        .search-form button {
            background: linear-gradient(135deg, #38BDF8, #2563EB);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.25);
            transition: all 0.3s ease;
        }
        .search-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(56, 189, 248, 0.35);
        }
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #F87171;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .order-info-card {
            background: #0F172A;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            border-left: 4px solid #38BDF8;
            border: 1px solid rgba(255, 255, 255, 0.04);
        }
        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            font-size: 14px;
            color: #CBD5E1;
            margin-top: 10px;
        }
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
            text-align: left;
        }
        .order-table th {
            background-color: #1E293B;
            color: #94A3B8;
            text-transform: uppercase;
            font-size: 12px;
        }
    </style>
</head>
<body>

    <!-- NAVBAR PÚBLICO -->
    <header class="store-header">
        <a href="tienda.php" class="store-logo">TechStore 🚀</a>
        <nav class="store-nav">
            <a href="tienda.php">Catálogo</a>
            <a href="rastrear.php" class="active">Rastrear Pedido</a>
            <a href="carrito.php">🛒 Carrito</a>
            <a href="admin/dashboard.php">Admin Panel</a>
        </nav>
    </header>

    <div class="tracker-container">
        <div class="tracker-card">
            <h1 class="tracker-title">Rastreo de Pedidos</h1>
            <p class="tracker-subtitle">Ingresa el número de tu orden para consultar su estado actual en tiempo real.</p>

            <form action="rastrear.php" method="GET" class="search-form">
                <input type="number" name="id_pedido" placeholder="Ej. 1, 2, 3..." value="<?php echo isset($_GET['id_pedido']) ? htmlspecialchars($_GET['id_pedido']) : ''; ?>" required>
                <button type="submit">Buscar Pedido</button>
            </form>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($pedido): ?>
                <div class="order-info-card">
                    <h3 style="color: #38BDF8; font-size: 16px; margin-bottom: 5px;">Orden #<?php echo $pedido['id_pedido']; ?></h3>
                    <div class="order-info-grid">
                        <div><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nombre'] . ' ' . $pedido['cliente_apellido']); ?></div>
                        <div><strong>Correo:</strong> <?php echo htmlspecialchars($pedido['correo']); ?></div>
                        <div><strong>Fecha:</strong> <?php echo $pedido['fecha_pedido']; ?></div>
                        <div><strong>Estado Actual:</strong> <span style="color: #FBBF24; font-weight: 700;"><?php echo $pedido['estado']; ?></span></div>
                        <div style="grid-column: 1 / -1;"><strong>Dirección de Envío:</strong> <?php echo htmlspecialchars($pedido['direccion']); ?></div>
                    </div>
                </div>

                <h3 style="font-size: 15px; color: #38BDF8; margin-top: 25px; margin-bottom: 10px;">Productos en la Orden</h3>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $det): ?>
                            <tr>
                                <td><code style="color: #38BDF8;"><?php echo htmlspecialchars($det['codigo']); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($det['producto_nombre']); ?></strong></td>
                                <td><?php echo $det['cantidad']; ?></td>
                                <td><strong style="color: #34D399;">$ <?php echo number_format($det['subtotal'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="text-align: right; font-size: 17px; font-weight: 700; color: #34D399; margin-top: 15px;">
                    Total Cancelado: $ <?php echo number_format($pedido['total'], 2); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>