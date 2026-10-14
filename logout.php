<?php
// =====================================================
// SCRIPT DE CIERRE DE SESIÓN (LOGOUT)
// =====================================================
require_once __DIR__ . '/includes/seguridad.php';
exigir_post();

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, borramos también la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Redirigir al index principal
header('Location: index.php');
exit();
?>
