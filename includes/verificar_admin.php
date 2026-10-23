<?php
// =====================================================
// VALIDADOR DE SEGURIDAD PARA EL PANEL ADMINISTRATIVO
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
}

require_once __DIR__ . "/seguridad.php";

// Verificar si la sesión del administrador está activa y su rol es correcto
if (!isset($_SESSION['id_usuario'])) {
    // Redirigir al acceso administrativo conservando el carrito del visitante.
    
    // Como las rutas admin están en una subcarpeta, subimos un nivel con ../
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $pos = strpos($base . '/', '/admin/');
    header('Location: ' . ($pos !== false ? substr($base, 0, $pos) : '') . '/index.php');
    exit();
}
require_once __DIR__.'/../config/conexion.php';
require_once __DIR__.'/permisos.php';
preparar_roles($conexion);$rolEquipo=rol_usuario($conexion,(int)$_SESSION['id_usuario']);
if($rolEquipo===null){unset($_SESSION['id_usuario'],$_SESSION['rol']);header('Location: '.ruta('index.php'));exit;}
$_SESSION['rol']=$rolEquipo;
$rutaEquipo=strtolower(str_replace('\\','/',$_SERVER['SCRIPT_NAME']??''));$posEquipo=strpos($rutaEquipo,'/admin/');$rutaEquipo=$posEquipo===false?'':substr($rutaEquipo,$posEquipo+7);
if(!permite_ruta_equipo($rolEquipo,$rutaEquipo)){http_response_code(403);echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Acceso restringido</title><p>Tu rol no permite abrir esta sección.</p><a href="'.e(ruta('admin/gestion.php')).'">Volver a Gestión de la tienda</a></html>';exit;}
?>
