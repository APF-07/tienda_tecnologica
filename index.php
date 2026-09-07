<?php
session_start();
$error = isset($_SESSION['error_login']) ? $_SESSION['error_login'] : '';
unset($_SESSION['error_login']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechStore | Plataforma Tecnológica</title>
    <!-- Tipografía moderna Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: #0B1020;
            color: #F8FAFC;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            position: relative;
        }

        /* EFECTOS DE ILUMINACIÓN DE FONDO (FUTURISTAS Y SUAVES) */
        .bg-glow-1 {
            position: absolute;
            top: -10%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(108, 99, 255, 0.15) 0%, rgba(11, 16, 32, 0) 70%);
            z-index: 0;
            pointer-events: none;
        }

        .bg-glow-2 {
            position: absolute;
            bottom: -10%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.1) 0%, rgba(11, 16, 32, 0) 70%);
            z-index: 0;
            pointer-events: none;
        }

        /* CONTENEDOR PRINCIPAL */
        .main-container {
            position: relative;
            z-index: 1;
            display: flex;
            width: 100%;
            max-width: 1200px;
            min-height: 650px;
            margin: 20px;
            background: rgba(21, 27, 46, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(108, 99, 255, 0.15);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 
                        0 0 40px rgba(108, 99, 255, 0.1);
            overflow: hidden;
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* =========================================
           LADO IZQUIERDO: TIENDA VIRTUAL
           ========================================= */
        .welcome-side {
            flex: 1.2;
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .brand-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            font-size: 38px;
            font-weight: 800;
            background: linear-gradient(135deg, #F8FAFC 0%, #22D3EE 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        .brand-badge {
            background: rgba(34, 211, 238, 0.1);
            color: #22D3EE;
            border: 1px solid rgba(34, 211, 238, 0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero-content {
            margin: 40px 0;
        }

        .hero-content h1 {
            font-size: 48px;
            font-weight: 800;
            color: #F8FAFC;
            line-height: 1.15;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .hero-content h1 span {
            background: linear-gradient(135deg, #6C63FF 0%, #22D3EE 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-content p {
            font-size: 16px;
            color: #94A3B8;
            line-height: 1.7;
            max-width: 460px;
            margin-bottom: 35px;
        }

        /* GRID DE ICONOS TECNOLÓGICOS FLOTANTES */
        .tech-grid {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }

        .tech-pill {
            background: rgba(21, 27, 46, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 10px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #94A3B8;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            animation: float 4s ease-in-out infinite;
        }

        .tech-pill:nth-child(2) { animation-delay: 1s; }
        .tech-pill:nth-child(3) { animation-delay: 2s; }
        .tech-pill:nth-child(4) { animation-delay: 1.5s; }
        .tech-pill:nth-child(5) { animation-delay: 2.5s; }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .tech-pill:hover {
            border-color: #6C63FF;
            color: #F8FAFC;
            background: rgba(108, 99, 255, 0.1);
        }

        /* BOTÓN IR A LA TIENDA */
        .btn-safari {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, #6C63FF 0%, #2563EB 100%);
            color: #F8FAFC;
            padding: 16px 36px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 10px 25px -5px rgba(108, 99, 255, 0.5);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-safari::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, #22D3EE 0%, #2563EB 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .btn-safari span {
            position: relative;
            z-index: 2;
        }

        .btn-safari:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -5px rgba(34, 211, 238, 0.4);
        }

        .btn-safari:hover::after {
            opacity: 1;
        }


        /* =========================================
           LADO DERECHO: PANEL ADMINISTRADOR
           ========================================= */
        .login-side {
            flex: 1;
            background: rgba(15, 23, 42, 0.4);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .login-card h2 {
            font-size: 28px;
            font-weight: 800;
            color: #F8FAFC;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .login-card p {
            font-size: 14px;
            color: #94A3B8;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #F87171;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            color: #94A3B8;
            margin-bottom: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #64748B;
            font-size: 16px;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        /* INPUTS MODERNOS */
        .form-group input {
            width: 100%;
            height: 52px;
            padding: 0 16px 0 48px;
            background: rgba(11, 16, 32, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            color: #F8FAFC;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .form-group input::placeholder {
            color: #475569;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6C63FF;
            background: rgba(21, 27, 46, 0.9);
            box-shadow: 0 0 0 4px rgba(108, 99, 255, 0.15),
                        0 0 15px rgba(108, 99, 255, 0.1);
        }

        .form-group input:focus ~ .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: #22D3EE;
        }

        /* BOTÓN INICIAR SESIÓN */
        .btn-login-wild {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, #6C63FF 0%, #2563EB 100%);
            border: none;
            border-radius: 12px;
            color: #F8FAFC;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 10px 25px -5px rgba(108, 99, 255, 0.4);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-login-wild::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, #22D3EE 0%, #2563EB 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn-login-wild span {
            position: relative;
            z-index: 2;
        }

        .btn-login-wild:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(34, 211, 238, 0.3);
        }

        .btn-login-wild:hover::after {
            opacity: 1;
        }


        /* =========================================
           RESPONSIVE DESIGN
           ========================================= */
        @media (max-width: 968px) {
            .main-container {
                flex-direction: column;
                max-width: 600px;
                margin: 20px;
            }
            .welcome-side {
                padding: 40px 30px;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            }
            .hero-content h1 {
                font-size: 36px;
            }
            .login-side {
                padding: 40px 30px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            .main-container {
                margin: 0;
                border-radius: 16px;
            }
            .hero-content h1 {
                font-size: 28px;
            }
            .welcome-side, .login-side {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- EFECTOS DE ILUMINACIÓN DE FONDO -->
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <div class="main-container">

        <!-- LADO IZQUIERDO: TIENDA VIRTUAL -->
        <div class="welcome-side">
            <div class="brand-container">
                <span class="brand-logo">TechStore</span>
                <span class="brand-badge">PRO v2.0</span>
            </div>

            <div class="hero-content">
                <h1>Innovación y poder en <span>hardware y tecnología</span></h1>
                <p>Explora la vanguardia en equipos de alto rendimiento, componentes avanzados y dispositivos diseñados para acelerar tu potencial.</p>

                <!-- Elementos tecnológicos decorativos flotantes -->
                <div class="tech-grid">
                    <div class="tech-pill">💻 Laptops Pro</div>
                    <div class="tech-pill">📱 Smartphones</div>
                    <div class="tech-pill">🎧 Audio Hi-Fi</div>
                    <div class="tech-pill">⌨️ Mecánicos</div>
                    <div class="tech-pill">🎮 Gaming Rig</div>
                </div>
            </div>

            <div>
                <a href="tienda.php" class="btn-safari">
                    <span>Explorar la Tienda 🚀</span>
                </a>
            </div>
        </div>

        <!-- LADO DERECHO: PANEL ADMINISTRADOR -->
        <div class="login-side">
            <div class="login-card">
                <h2>Panel Admin</h2>
                <p>Ingresa tus credenciales de gestión</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error">
                        <span>⚠️</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label>Correo Electrónico</label>
                        <div class="input-wrapper">
                            <span class="input-icon">✉</span>
                            <input type="email" name="correo" placeholder="admin@admin.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Contraseña</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                        </div>
                    </div>

                    <button type="submit" class="btn-login-wild">
                        <span>INICIAR SESIÓN</span>
                    </button>
                </form>
            </div>
        </div>

    </div>

</body>
</html>