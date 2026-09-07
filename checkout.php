<?php
session_start();
require_once 'config/conexion.php';

// Si el carrito está vacío, redirigir al carrito
if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    header('Location: carrito.php');
    exit();
}

$error = '';

// Procesar el formulario de compra cuando se envía por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $dni = trim($_POST['dni']);
    $direccion = trim($_POST['direccion']);
    $tipo_envio = trim($_POST['tipo_envio']);

    if (empty($nombre) || empty($apellido) || empty($correo) || empty($telefono) || empty($direccion)) {
        $error = 'Por favor, completa todos los campos obligatorios para procesar tu pedido.';
    } else {
        try {
            // Iniciar transacción segura en la base de datos
            $conexion->beginTransaction();

            // 1. Calcular total general y verificar stock una última vez
            $total_pedido = 0;
            foreach ($_SESSION['carrito'] as $item) {
                // Verificar stock actual en BD
                $stmtCheck = $conexion->prepare("SELECT stock FROM productos WHERE id_producto = :id FOR UPDATE");
                $stmtCheck->execute(['id' => $item['id_producto']]);
                $prodData = $stmtCheck->fetch();

                if (!$prodData || $prodData['stock'] < $item['cantidad']) {
                    throw new Exception("Lo sentimos, el producto '{$item['nombre']}' ya no cuenta con stock suficiente.");
                }
                $total_pedido += ($item['precio'] * $item['cantidad']);
            }

            // Añadir costo de envío si aplica (ej. $15 para fuera de la ciudad)
            $costo_envio = ($tipo_envio === 'fuera') ? 15.00 : 0.00;
            $total_general = $total_pedido + $costo_envio;

            // 2. Registrar o buscar al cliente
            $direccion_completa = "DNI: $dni | Envío: " . ($tipo_envio === 'fuera' ? 'Fuera de la ciudad (+$15)' : 'Dentro de la ciudad (Gratis)') . " | Dir: $direccion";

            $stmtCliente = $conexion->prepare("INSERT INTO clientes (nombre, apellido, correo, telefono, direccion) VALUES (:nombre, :apellido, :correo, :telefono, :direccion)");
            $stmtCliente->execute([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'correo' => $correo,
                'telefono' => $telefono,
                'direccion' => $direccion_completa
            ]);
            $id_cliente = $conexion->lastInsertId();

            // 3. Registrar el pedido principal
            $stmtPedido = $conexion->prepare("INSERT INTO pedidos (id_cliente, total, estado) VALUES (:id_cliente, :total, 'Pendiente')");
            $stmtPedido->execute([
                'id_cliente' => $id_cliente,
                'total' => $total_general
            ]);
            $id_pedido = $conexion->lastInsertId();

            // 4. Registrar detalles del pedido y descontar stock
            $stmtDetalle = $conexion->prepare("INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad, precio_unitario, subtotal) VALUES (:id_pedido, :id_producto, :cantidad, :precio_unitario, :subtotal)");
            
            $stmtStock = $conexion->prepare("UPDATE productos SET stock = stock - :cantidad WHERE id_producto = :id_producto");

            foreach ($_SESSION['carrito'] as $item) {
                $subtotal_item = $item['precio'] * $item['cantidad'];

                // Insertar detalle
                $stmtDetalle->execute([
                    'id_pedido' => $id_pedido,
                    'id_producto' => $item['id_producto'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'subtotal' => $subtotal_item
                ]);

                // Descontar stock
                $stmtStock->execute([
                    'cantidad' => $item['cantidad'],
                    'id_producto' => $item['id_producto']
                ]);
            }

            // Confirmar transacción permanentemente
            $conexion->commit();

            // Vaciar carrito
            unset($_SESSION['carrito']);

            // Redirigir a la página de pedido exitoso pasando el ID
            header('Location: pedido_exitoso.php?id=' . $id_pedido);
            exit();

        } catch (Exception $e) {
            // Si ocurre algún error, revertir toda la transacción
            $conexion->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Calcular subtotales para mostrar en la vista de checkout
$total_productos = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total_productos += ($item['precio'] * $item['cantidad']);
}
$total_carrito_items = array_sum(array_column($_SESSION['carrito'], 'cantidad'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - TechStore</title>
    
    <!-- FAVICON (Logotipo en la pestaña del navegador) -->
    <link rel="icon" type="image/png" href="img/logo.png">

    <link rel="stylesheet" href="css/tienda.css">
    
    <!-- 🔑 PEGADURA DE LA API DE GOOGLE MAPS: Reemplaza AQUÍ tu Clave de API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC9cQ4lhI7uyIEqav5yF2jG53WBwluVVcc&libraries=places"></script>

    <style>
        .checkout-container {
            max-width: 1000px;
            margin: 40px auto;
            display: flex;
            gap: 30px;
        }
        .checkout-form-box {
            flex: 2;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .checkout-summary-box {
            flex: 1;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            height: fit-content;
        }
        .checkout-title {
            font-size: 20px;
            color: #1e3c72;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 12px;
            color: #555;
        }
        .summary-total {
            border-top: 2px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 700;
            color: #1e3c72;
        }
        .btn-confirm {
            width: 100%;
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn-confirm:hover { background-color: #219653; }
        .alert-error {
            background: #fde8e8;
            color: #c0392b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        @media(max-width: 768px) {
            .checkout-container { flex-direction: column; margin: 20px; }
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
            <a href="carrito.php">🛒 Carrito <span class="cart-badge"><?php echo $total_carrito_items; ?></span></a>
            <a href="index.php">Admin Panel</a>
        </nav>
    </header>

    <div class="checkout-container">
        
        <!-- FORMULARIO DE DATOS DEL CLIENTE -->
        <div class="checkout-form-box">
            <h2 class="checkout-title">Datos de Envío y Contacto</h2>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="checkout.php" method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Tu nombre">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="apellido">Apellido *</label>
                        <input type="text" id="apellido" name="apellido" required placeholder="Tu apellido">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="correo">Correo Electrónico *</label>
                        <input type="email" id="correo" name="correo" required placeholder="correo@ejemplo.com">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="telefono">Teléfono / Celular *</label>
                        <input type="text" id="telefono" name="telefono" required placeholder="999888777">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="dni">DNI / Documento *</label>
                        <input type="text" id="dni" name="dni" required placeholder="Número de documento">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="tipo_envio">Tipo de Envío *</label>
                        <select id="tipo_envio" name="tipo_envio">
                            <option value="dentro">Dentro de la ciudad (Gratis)</option>
                            <option value="fuera">Fuera de la ciudad (+$15.00)</option>
                        </select>
                    </div>
                </div>

                <!-- CAMPO DE DIRECCIÓN CON GOOGLE PLACES AUTOCOMPLETE -->
                <div class="form-group">
                    <label for="direccion">Dirección exacta de entrega *</label>
                    <input type="text" id="direccion" name="direccion" required placeholder="Empieza a escribir tu dirección..." autocomplete="off">
                </div>

                <button type="submit" class="btn-confirm">Confirmar y Realizar Pedido</button>
            </form>
        </div>

        <!-- RESUMEN DEL PEDIDO -->
        <div class="checkout-summary-box">
            <h2 class="checkout-title">Resumen de la Orden</h2>
            
            <div style="max-height: 250px; overflow-y: auto; margin-bottom: 15px;">
                <?php foreach ($_SESSION['carrito'] as $item): ?>
                    <div class="summary-item">
                        <span><?php echo htmlspecialchars($item['nombre']); ?> (x<?php echo $item['cantidad']; ?>)</span>
                        <strong>$ <?php echo number_format($item['precio'] * $item['cantidad'], 2); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="summary-item">
                <span>Subtotal productos:</span>
                <strong>$ <?php echo number_format($total_productos, 2); ?></strong>
            </div>

            <div class="summary-item">
                <span>Envío:</span>
                <span style="font-size: 12px; color: #666;">Calculado según zona</span>
            </div>

            <div class="summary-total">
                <span>Total a Pagar:</span>
                <span>$ <?php echo number_format($total_productos, 2); ?></span>
            </div>
        </div>

    </div>

    <!-- SCRIPT DE ACTIVACIÓN DE GOOGLE PLACES AUTOCOMPLETE -->
    <script>
    function initAutocomplete() {
        const inputDir = document.getElementById('direccion');
        new google.maps.places.Autocomplete(inputDir, {
            types: ['address']
        });
    }
    window.onload = initAutocomplete;
    </script>

</body>
</html>