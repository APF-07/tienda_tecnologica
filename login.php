<?php
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
require_once 'config/conexion.php';
require_once 'includes/permisos.php';
preparar_roles($conexion);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $_SESSION['correo_login'] = $correo;

    if ($correo === '' || $password === '') {
        $_SESSION['error_login'] = 'Por favor, completa todos los campos.';
        header('Location: index.php');
        exit();
    }

    try {
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = :correo AND estado = 1 LIMIT 1");
        $stmt->execute(['correo' => $correo]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['correo_login'], $_SESSION['error_login']);
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['correo'] = $usuario['correo'];
            $_SESSION['rol'] = rol_usuario($conexion,(int)$usuario['id_usuario']);
            if($_SESSION['rol']===null){unset($_SESSION['id_usuario']);$_SESSION['error_login']='Cuenta sin permisos activos.';header('Location: index.php');exit;}

            header('Location: '.($_SESSION['rol']==='administrador'?'admin/dashboard.php':'admin/gestion.php'));
            exit();
        } else {
            $_SESSION['error_login'] = 'Correo o contraseña incorrectos.';
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_login'] = 'Error en el sistema.';
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>
