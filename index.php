<?php
require_once __DIR__ . '/includes/seguridad.php';
$error = $_SESSION['error_login'] ?? '';
$correo = $_SESSION['correo_login'] ?? '';
unset($_SESSION['error_login'], $_SESSION['correo_login']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b1220">
    <title>Iniciar sesión | TechStore</title>
    <link rel="stylesheet" href="css/login.css?v=20261013-tienda">
    <script src="js/login.js?v=20261014-acceso" defer></script>
<link rel="stylesheet" href="css/experiencia.css?v=20261014"><script src="js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="css/login-creativo.css?v=2">
</head>
<body>
    <main class="login-layout">
        <section class="welcome" aria-labelledby="welcome-title">
            <a class="brand" href="tienda.php" aria-label="TechStore, ir a la tienda"><span class="brand-mark" aria-hidden="true">T</span>Tech<span>Store</span></a>
            <div class="welcome-content">
                <span class="eyebrow">TECNOLOGÍA PARA TU SIGUIENTE NIVEL</span>
                <h1 id="welcome-title">Grandes ideas.<br><span>El equipo correcto.</span></h1>
                <p>Encuentra el equipo ideal para crear, trabajar y jugar. Explora laptops, componentes y mucho más.</p>
                <a class="store-link" href="tienda.php">Explorar la tienda <span aria-hidden="true">↗</span></a>
                <div class="tech-scene" aria-hidden="true">
                    <div class="scene-orbit orbit-one"></div><div class="scene-orbit orbit-two"></div>
                    <span class="scene-cross cross-one">+</span><span class="scene-cross cross-two">+</span>
                    <div class="scene-label"><span></span> TU PRÓXIMA GRAN IDEA</div>
                    <img class="hero-image" src="uploads/ilustraciones/hero.svg" alt="" width="800" height="500">
                    <div class="scene-chip chip-performance"><span class="chip-icon">↗</span><div><small>Más posibilidades</small><strong>Crea sin límites</strong></div></div>
                    <div class="scene-chip chip-connect"><span class="chip-icon">✦</span><div><small>Tu mundo conectado</small><strong>Todo empieza aquí</strong></div></div>
                    <span class="scene-coordinate">01 / EXPLORA LO QUE SIGUE</span>
                </div>
                <div class="categories" aria-label="Categorías de la tienda"><span>Laptops</span><span>Componentes</span><span>Celulares</span></div>

            </div>
            <p class="welcome-footer">El siguiente paso empieza con la tecnología correcta.</p>
        </section>
        <section class="access" aria-labelledby="login-title">
            <a class="back-link" href="tienda.php"><span aria-hidden="true">←</span> Volver a la tienda</a>
            <div class="login-card">
                <div class="login-card-topline"><span class="access-mini-brand" aria-hidden="true">T</span><span class="access-panel-name">Tu espacio de gestión</span><small>TECHSTORE</small></div>
                <div class="access-heading">
                <div class="access-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></div>
                <div>
                <span class="eyebrow">ACCESO ADMINISTRATIVO</span>
                <h2 id="login-title">Bienvenido de nuevo</h2>
                </div></div>
                <p class="form-description">Todo listo para tu próximo movimiento.<br>Ingresa y toma el control de tu tienda.</p>
                <?php if ($error !== ''): ?>
                    <div class="alert-error" role="alert"><strong>No pudimos iniciar sesión</strong><span><?= e($error) ?></span></div>
                <?php endif; ?>
                <form action="login.php" method="POST" id="login-form">
                    <?= csrf_campo() ?>
                    <div class="form-group">
                        <label for="correo-login">Correo electrónico</label>
                        <div class="login-input-wrap"><svg class="login-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg><input type="email" id="correo-login" name="correo" placeholder="nombre@correo.com" value="<?= e($correo) ?>" required autocomplete="username" spellcheck="false" autocapitalize="none" aria-describedby="correo-ayuda"></div>
                        <small class="login-field-hint" id="correo-ayuda">Usa el correo de tu cuenta administrativa.</small>
                    </div>
                    <div class="form-group">
                        <label for="clave-login">Contraseña</label>
                        <div class="password-field login-input-wrap">
                            <svg class="login-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="8" cy="9" r="4"/><path d="m11 12 9 9m-3-3 3-3m-6 0 3-3"/></svg>
                            <input type="password" id="clave-login" name="password" placeholder="Ingresa tu contraseña" required autocomplete="current-password">
                            <button class="password-toggle" type="button" aria-label="Mostrar contraseña" aria-controls="clave-login" aria-pressed="false" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                        <p id="caps-warning" class="caps-warning" role="status" hidden>Bloq Mayús está activado.</p>
                    </div>
                    <button class="submit-button" type="submit"><span>Iniciar sesión</span><span aria-hidden="true">→</span></button>
                </form>
                <div class="login-card-bottom"><span class="access-note"><span class="status-dot" aria-hidden="true"></span> Solo administradores</span><a href="tienda.php">Ir a la tienda <span aria-hidden="true">↗</span></a></div>
            </div>
            <p class="access-footer">TechStore · Panel de administración</p>
        </section>
    </main>
</body>
</html>
