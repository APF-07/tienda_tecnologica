<?php
session_start();
require_once 'config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($correo === '' || $password === '') {
        $_SESSION['error_login'] = 'Por favor, completa todos los campos.';
        header('Location: index.php');
        exit();
    }

    try {
        $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE correo = :correo AND rol = 'administrador' AND estado = 1 LIMIT 1");
        $stmt->execute(['correo' => $correo]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password'])) {
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['correo'] = $usuario['correo'];
            $_SESSION['rol'] = $usuario['rol'];

            header('Location: admin/dashboard.php');
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