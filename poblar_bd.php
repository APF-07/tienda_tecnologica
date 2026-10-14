<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Conexión a la base de datos
require_once 'config/conexion.php';
require_once 'includes/catalogo.php'; 

echo "<div style='font-family: Arial; padding: 20px;'>";
echo "<h2>Iniciando la inyección de 1000 registros (500 Clientes + 500 Productos)...</h2>";

// ==========================================
// 1. GENERACIÓN DE 500 CLIENTES PERUANOS
// ==========================================
$nombres = ['Juan', 'Carlos', 'Luis', 'Jorge', 'Pedro', 'Miguel', 'Angel', 'Abraham', 'Jose', 'Ana', 'Maria', 'Rosa', 'Carmen', 'Nataly', 'Lucia', 'Milagros', 'Luz', 'Diana', 'Diego', 'Renzo'];
$apellidos = ['Quispe', 'Mamani', 'Flores', 'Condori', 'Rojas', 'Huaman', 'Chuquilin', 'Yupanqui', 'Pastor', 'Fuero', 'Ramirez', 'Matos', 'Castillo', 'Espinoza', 'Paucar', 'Soto', 'Vargas', 'Mendoza'];
$departamentos = ['Pasco (Cerro de Pasco)', 'Lima', 'Junin (Huancayo)', 'Arequipa', 'Cusco', 'Piura', 'Cajamarca', 'La Libertad (Trujillo)', 'Lambayeque (Chiclayo)', 'Puno', 'Ayacucho', 'Tacna', 'Ica'];
$calles = ['Av. Bolognesi', 'Jr. 9 de Diciembre', 'Av. Los Incas', 'Jr. de la Unión', 'Av. El Sol', 'Psj. Pedro Caballero', 'Calle San José', 'Av. Brasil', 'Av. Javier Prado'];

$clientes_insertados = 0;

for ($i = 0; $i < 500; $i++) {
    $nombre_rand = $nombres[array_rand($nombres)];
    $apellidos_completos = $apellidos[array_rand($apellidos)] . ' ' . $apellidos[array_rand($apellidos)];
    
    // Uniqid evita correos duplicados (tu BD exige correos únicos)
    $correo = strtolower($nombre_rand) . uniqid() . '@gmail.com';
    $telefono = '9' . rand(10000000, 99999999);
    $dni = str_pad(rand(1000000, 99999999), 8, '0', STR_PAD_LEFT);
    
    $dep_rand = $departamentos[array_rand($departamentos)];
    $calle_rand = $calles[array_rand($calles)];
    $direccion = "DNI: $dni | Envío: $dep_rand | Dir: $calle_rand " . rand(100, 999);

    try {
        $sql = "INSERT INTO clientes (nombre, apellido, correo, telefono, direccion) VALUES (:nombre, :apellido, :correo, :telefono, :direccion)";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre_rand,
            ':apellido' => $apellidos_completos,
            ':correo' => $correo,
            ':telefono' => $telefono,
            ':direccion' => $direccion
        ]);
        $clientes_insertados++;
    } catch (Exception $e) {
        continue; 
    }
}
echo "<p style='color: green;'>✔ $clientes_insertados Clientes peruanos insertados.</p>";

// ==========================================
// 2. GENERACIÓN DE 500 PRODUCTOS TECNOLÓGICOS
// ==========================================
$tipos_producto = ['Laptop', 'PC Gamer', 'Monitor IPS', 'Teclado Mecánico', 'Mouse Inalámbrico', 'Memoria RAM 16GB', 'SSD 1TB M.2', 'Smartphone', 'Auriculares', 'Placa Madre'];
$adjetivos = ['Pro', 'Ultra', 'Elite', 'Lite', 'Plus', 'Evo', 'Max', 'Essential'];
$categorias_por_nombre=[];foreach($conexion->query('SELECT * FROM categorias WHERE estado=1')->fetchAll() as $categoria) $categorias_por_nombre[clave_catalogo($categoria['nombre'])]=$categoria['id_categoria'];
$marcas_disponibles=$conexion->query('SELECT * FROM marcas WHERE estado=1')->fetchAll();

$productos_insertados = 0;

for ($i = 0; $i < 500; $i++) {
    $tipo = $tipos_producto[array_rand($tipos_producto)];
    $adjetivo = $adjetivos[array_rand($adjetivos)];
    $nombre_producto = "$tipo $adjetivo Edición " . rand(2023, 2026);
    
    // Códigos únicos como "PRD-A1B2C3D4"
    $codigo = 'PRD-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $descripcion = "Excelente $tipo modelo $adjetivo con garantía de 12 meses. Ideal para uso exigente.";
    
    // Usamos los IDs reales de tus tablas categorías y marcas
    $categoria_tipo=categoria_por_nombre($tipo);
    $id_categoria=$categorias_por_nombre[$categoria_tipo] ?? null;
    $compatibles=array_values(array_filter($marcas_disponibles,fn($marca)=>marca_admitida_producto($tipo,$categoria_tipo,$marca['nombre'])));
    if(!$id_categoria || !$compatibles) continue;
    $marca_elegida=$compatibles[array_rand($compatibles)];
    $id_marca=$marca_elegida['id_marca'];
    $nombre_producto="$tipo {$marca_elegida['nombre']} $adjetivo Edición ".rand(2023,2026);     
    $rangos=['laptops'=>[1700,7000],'computadoras'=>[1800,9000],'monitores'=>[400,2500],'componentes'=>[120,1600],'almacenamiento'=>[160,900],'perifericos'=>[35,650],'celulares'=>[500,4000]];
    [$minimo,$maximo]=$rangos[$categoria_tipo];
    $precio=rand($minimo,$maximo)+(rand(0,99)/100); // Precios realistas con decimales
    $stock = rand(0, 50);
    $imagen = 'default_producto.webp'; // Imagen genérica para no romper el front-end

    try {
        $sql_prod = "INSERT INTO productos (codigo, nombre, descripcion, id_categoria, id_marca, precio, stock, imagen, estado) 
                     VALUES (:codigo, :nombre, :descripcion, :id_categoria, :id_marca, :precio, :stock, :imagen, 1)";
        $stmt_prod = $conexion->prepare($sql_prod);
        $stmt_prod->execute([
            ':codigo' => $codigo,
            ':nombre' => $nombre_producto,
            ':descripcion' => $descripcion,
            ':id_categoria' => $id_categoria,
            ':id_marca' => $id_marca,
            ':precio' => $precio,
            ':stock' => $stock,
            ':imagen' => $imagen
        ]);
        $productos_insertados++;
    } catch (Exception $e) {
        continue;
    }
}
echo "<p style='color: green;'>✔ $productos_insertados Productos generados en el catálogo.</p>";
echo "<br><a href='admin/dashboard.php' style='padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a>";
echo "</div>";
?>