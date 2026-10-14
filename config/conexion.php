<?php
require_once __DIR__ . "/../includes/seguridad.php";
// =====================================================
// CONFIGURACIÓN DE CONEXIÓN A LA BASE DE DATOS (PDO)
// =====================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'tienda_tecnologica';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

try {
    // Crear la instancia de conexión PDO incluyendo el puerto por compatibilidad en la nube
    $conexion = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configurar el modo de error de PDO para que lance excepciones
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurar el modo de fetch predeterminado como array asociativo
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $conexion->exec("SET time_zone = '-05:00'");

} catch (PDOException $e) {
    // Si falla la conexión, detener la ejecución y mostrar un mensaje limpio
    error_log($e->getMessage());
    if (PHP_SAPI === 'cli') { fwrite(STDERR, 'No se pudo conectar a MySQL.' . PHP_EOL); exit(1); }
    http_response_code(503);
    exit('No se pudo conectar a MySQL. Comprueba que esté iniciado y revisa la configuración DB_HOST / DB_NAME.');
}
?>