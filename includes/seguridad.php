<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/interfaz.php';
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true]);
}
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_campo() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
function verificar_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('La sesión del formulario venció. Vuelve a la página anterior, recarga e inténtalo nuevamente.');
    }
}
function exigir_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Esta acción requiere un formulario.');
    }
    verificar_csrf();
}
function csv_seguro($valor) {
    return is_string($valor) && preg_match('/^[\s]*[=+@\-]/u', $valor) ? "'" . $valor : $valor;
}
if (PHP_SAPI !== 'cli' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') verificar_csrf();
