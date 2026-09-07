<?php
// =====================================================
// VALIDADOR DE SEGURIDAD PARA EL PANEL ADMINISTRATIVO
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si la sesión del administrador está activa y su rol es correcto
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrador') {
    // Si no está autorizado, destruir sesión por seguridad y redirigir al index con error
    session_unset();
    session_destroy();
    
    // Como las rutas admin están en una subcarpeta, subimos un nivel con ../
    header('Location: ../index.php');
    exit();
}
?>