<?php
// =====================================================
// VALIDADOR DE SEGURIDAD PARA EL PANEL ADMINISTRATIVO
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
}

require_once __DIR__ . "/seguridad.php";

// Verificar si la sesión del administrador está activa y su rol es correcto
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    // Redirigir al acceso administrativo conservando el carrito del visitante.
    
    // Como las rutas admin están en una subcarpeta, subimos un nivel con ../
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $pos = strpos($base . '/', '/admin/');
    header('Location: ' . ($pos !== false ? substr($base, 0, $pos) : '') . '/index.php');
    exit();
}
?>