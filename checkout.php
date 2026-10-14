<?php
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/compras.php';
require_once __DIR__ . '/config/maps.php';
$clave_maps=clave_google_maps();
if (empty($_SESSION['carrito'])) { header('Location: carrito.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = datos_compra($_POST);
    extract($datos, EXTR_SKIP);
    try {
            $id_pedido = registrar_compra($conexion, $_SESSION['carrito'], $datos);
            $_SESSION['pedidos_permitidos'][$id_pedido] = true;
            unset($_SESSION['carrito']);
            header('Location: pedido_exitoso.php?id=' . $id_pedido); exit;
        } catch (Throwable $e) {
            unset($item);
            if ($conexion->inTransaction()) $conexion->rollBack();
            error_log($e->getMessage());
            $error = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'No pudimos registrar tu pedido. Inténtalo nuevamente.';
        }
}
$total_productos = array_sum(array_map(fn($item) => $item['precio'] * $item['cantidad'], $_SESSION['carrito']));
$total_carrito_items = array_sum(array_column($_SESSION['carrito'], 'cantidad'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra - TechStore</title>
    
    <link rel="icon" href="uploads/productos/default.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tienda.css">
    

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

        /* Estilos para los métodos de pago */
        .metodos-pago-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }
        .metodo-opcion {
            display: flex;
            align-items: center;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .metodo-opcion:hover {
            border-color: #1e3c72;
            background-color: #f8fafc;
        }
        .metodo-opcion input {
            width: auto;
            margin-right: 10px;
        }

        @media(max-width: 768px) {
            .checkout-container { flex-direction: column; margin: 20px; }
        }
    </style>
<link rel="stylesheet" href="css/mejoras.css">
<link rel="stylesheet" href="css/experiencia.css?v=20261014"><script src="js/experiencia.js?v=20261014" defer></script>
</head>
<body>

    <?php render_store_header(''); ?>

    <div class="checkout-container">
        
        <div class="checkout-form-box">
            <h2 class="checkout-title">Datos de Envío y Contacto</h2>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="checkout.php" method="POST">
                <?php echo csrf_campo(); ?>
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Tu nombre" value="<?php echo htmlspecialchars($nombre ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="apellido">Apellido *</label>
                        <input type="text" id="apellido" name="apellido" required placeholder="Tu apellido" value="<?php echo htmlspecialchars($apellido ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="correo">Correo Electrónico *</label>
                        <input type="email" id="correo" name="correo" required placeholder="correo@ejemplo.com" value="<?php echo htmlspecialchars($correo ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="telefono">Teléfono / Celular *</label>
                        <input type="text" id="telefono" name="telefono" required placeholder="999888777" value="<?php echo htmlspecialchars($telefono ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label for="dni">DNI / Documento *</label>
                        <input type="text" id="dni" name="dni" required placeholder="Número de documento" value="<?php echo htmlspecialchars($dni ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="tipo_envio">Tipo de Envío *</label>
                        <select id="tipo_envio" name="tipo_envio" onchange="calcularTotalGeneral()">
                            <option value="dentro">Dentro de la ciudad (Gratis)</option>
                            <option value="fuera" <?php if (($tipo_envio ?? '') === 'fuera') echo 'selected'; ?>>Fuera de la ciudad (+S/ 15.00)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="direccion">Dirección exacta de entrega *</label>
                    <input type="text" id="direccion" name="direccion" required placeholder="Empieza a escribir tu dirección..." autocomplete="off" value="<?php echo htmlspecialchars($direccion ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <!-- SECCIÓN DE MÉTODO DE PAGO -->
                <div class="form-group" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                    <h2 class="checkout-title">Método de pago</h2>

                    <div class="metodos-pago-container">
                        <label class="metodo-opcion">
                            <input type="radio" name="metodo_pago" value="Yape/Plin" required onchange="mostrarOpcionesPago()">
                            <strong>📱 Yape / Plin</strong>
                        </label>
                        <label class="metodo-opcion">
                            <input type="radio" name="metodo_pago" value="Transferencia BCP" <?php if (!MODO_DEMO && !getenv('BCP_CUENTA')) echo 'disabled'; ?> onchange="mostrarOpcionesPago()">
                            <strong>🏦 Transferencia BCP<?php if (!MODO_DEMO && !getenv('BCP_CUENTA')) echo ' (no disponible)'; ?></strong>
                        </label>
                        <label class="metodo-opcion">
                            <input type="radio" name="metodo_pago" value="Contraentrega" required onchange="mostrarOpcionesPago()">
                            <strong>💵 Pago a Contraentrega</strong>
                        </label>
                    </div>

                    <!-- Contenedor dinámico de instrucciones (Sin campo de operación) -->
                    <div id="detalles_pago" style="display: none; padding: 20px; border: 1px solid #bfdbfe; border-radius: 8px; background: #eff6ff;">

                        <!-- SECCIÓN YAPE / PLIN -->
                        <div id="info_yape" style="display: none; text-align: center; margin-bottom: 15px;">
                            <p style="font-size: 16px; margin-bottom: 5px; color: #1e293b;">
                                <strong>Yapea o Plinea al:</strong> <span style="color: #7e22ce; font-size: 20px; font-weight: bold;">971 968 701</span>
                            </p>
                            <p style="margin-bottom: 10px; color: #475569;">Titular: Abraham Pastor</p>

                            <!-- IMAGEN DEL QR -->
                            <div style="background: white; display: inline-block; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1;">
                                <img src="uploads/qr-yape.jpeg" alt="Código QR Yape" style="width: 160px; height: 160px; object-fit: contain; display: block;">
                            </div>
                            <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Escanea el código desde tu app</p>
                        </div>

                        <!-- SECCIÓN TRANSFERENCIA BCP -->
                        <div id="info_transferencia" style="display: none; margin-bottom: 15px;">
                            <p style="margin-bottom: 8px; color: #1e293b;"><strong>Cta BCP Soles:</strong> <?php echo e(MODO_DEMO ? 'Cuenta de demostración' : getenv('BCP_CUENTA')); ?></p>
                            <p style="margin-bottom: 8px; color: #1e293b;"><strong>CCI:</strong> <?php echo e(MODO_DEMO ? 'CCI de demostración' : getenv('BCP_CCI')); ?></p>
                            <p style="margin-bottom: 15px; color: #1e293b;"><strong>Titular:</strong> <?php echo e(MODO_DEMO ? 'TechStore · Proyecto académico' : getenv('BCP_TITULAR')); ?></p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-confirm">Confirmar y Realizar Pedido</button>
            </form>
        </div>

        <div class="checkout-summary-box">
            <h2 class="checkout-title">Resumen de la Orden</h2>
            
            <div style="max-height: 250px; overflow-y: auto; margin-bottom: 15px;">
                <?php foreach ($_SESSION['carrito'] as $item): ?>
                    <div class="summary-item">
                        <span><?php echo htmlspecialchars($item['nombre']); ?> (x<?php echo $item['cantidad']; ?>)</span>
                        <strong>S/ <?php echo number_format($item['precio'] * $item['cantidad'], 2); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="summary-item">
                <span>Subtotal productos:</span>
                <strong>S/ <?php echo number_format($total_productos, 2); ?></strong>
            </div>

            <div class="summary-item">
                <span>Envío:</span>
                <strong id="costo-envio">Gratis</strong>
            </div>

            <div class="summary-total">
                <span>Total a Pagar:</span>
                <span id="total-general">S/ <?php echo number_format($total_productos, 2); ?></span>
            </div>
        </div>

    </div>

    <script>
    function initAutocomplete() {
        if (!window.google?.maps?.places) return;
        const inputDir = document.getElementById('direccion');
        new google.maps.places.Autocomplete(inputDir, {
            types: ['address'], fields: ['formatted_address'], componentRestrictions: {country:'pe'}
        });
    }

    function mostrarOpcionesPago() {
        const radios = document.querySelector('input[name="metodo_pago"]:checked');
        if (!radios) return;

        const metodo = radios.value;
        const contenedorDetalles = document.getElementById('detalles_pago');
        const infoYape = document.getElementById('info_yape');
        const infoTransferencia = document.getElementById('info_transferencia');

        if (metodo === 'Yape/Plin' || metodo === 'Transferencia BCP') {
            contenedorDetalles.style.display = 'block';

            if (metodo === 'Yape/Plin') {
                infoYape.style.display = 'block';
                infoTransferencia.style.display = 'none';
                calcularTotalGeneral();
            } else {
                infoYape.style.display = 'none';
                infoTransferencia.style.display = 'block';
            }
        } else {
            // Contraentrega
            contenedorDetalles.style.display = 'none';
        }
    }

    function calcularTotalGeneral() {
        const envio = document.getElementById('tipo_envio').value === 'fuera' ? 15 : 0;
        document.getElementById('costo-envio').textContent = envio ? 'S/ 15.00' : 'Gratis';
        document.getElementById('total-general').textContent = 'S/ ' + (<?php echo json_encode((float)$total_productos); ?> + envio).toFixed(2);
    }
    document.addEventListener('DOMContentLoaded', calcularTotalGeneral);
    </script>
    <?php if ($clave_maps!==''): ?>
    <script async src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($clave_maps) ?>&amp;loading=async&amp;libraries=places&amp;callback=initAutocomplete"></script>
    <?php endif; ?>

</body>
</html>
