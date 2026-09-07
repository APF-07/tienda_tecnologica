<?php
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

} catch (PDOException $e) {
    // Si falla la conexión, detener la ejecución y mostrar un mensaje limpio
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>