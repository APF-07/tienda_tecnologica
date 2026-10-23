<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/catalogo.php';
require_once __DIR__ . '/registro_agentes.php';
function datos_producto(array $entrada): array {
    $datos=[];
    foreach(['codigo','nombre','descripcion','id_categoria','id_marca','precio','stock','estado'] as $campo) $datos[$campo]=is_scalar($entrada[$campo] ?? null)?trim((string)$entrada[$campo]):'';
    if ($datos['estado']==='') $datos['estado']='1';
    return $datos;
}
function validar_producto(array $datos): void {
    if ($datos['codigo']==='' || longitud_texto($datos['codigo'])>50 || $datos['nombre']==='' || longitud_texto($datos['nombre'])>150) throw new RuntimeException('Completa un código de hasta 50 caracteres y un nombre de hasta 150.');
    if (strlen($datos['descripcion'])>60000) throw new RuntimeException('La descripción es demasiado larga.');
    if (precio_centimos($datos['precio'])<=0) throw new RuntimeException('El precio debe ser mayor que cero.');
    if (!ctype_digit($datos['stock']) || (int)$datos['stock']>2147483647) throw new RuntimeException('El stock debe ser un número entero mayor o igual que cero.');
    foreach(['id_categoria','id_marca'] as $campo) if (!ctype_digit($datos[$campo]) || (int)$datos[$campo]<1) throw new RuntimeException('Selecciona una categoría y una marca válidas.');
    if (!in_array($datos['estado'],['0','1'],true)) throw new RuntimeException('El estado del producto no es válido.');
}
function guardar_imagen_producto(?array $archivo): ?string {
    if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    if (($archivo['error'] ?? -1)!==UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir la imagen. Revisa su tamaño e inténtalo nuevamente.');
    $temporal=$archivo['tmp_name'] ?? '';
    if (!is_string($temporal) || !is_uploaded_file($temporal)) throw new RuntimeException('El archivo de imagen no es válido.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($temporal);
    $tipos=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($tipos[$mime]) || filesize($temporal)>5*1024*1024 || !@getimagesize($temporal)) throw new RuntimeException('Adjunta una imagen JPG, PNG o WebP válida de hasta 5 MB.');
    $directorio=__DIR__.'/../uploads/productos/';
    if (!is_dir($directorio) && !mkdir($directorio,0755,true)) throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
    $nombre=bin2hex(random_bytes(16)).'.'.$tipos[$mime];
    if (!move_uploaded_file($temporal,$directorio.$nombre)) throw new RuntimeException('No se pudo guardar la imagen. Revisa los permisos de la carpeta de productos.');
    return $nombre;
}
function guardar_producto(PDO $db,array $datos,?array $archivo=null,int $id=0,?array &$anterior=null): int {
    validar_producto($datos);
    preparar_registro_agentes($db);
    $imagenNueva=null;
    try {
        $db->beginTransaction();
        $imagen=null;
        if ($id) {
            $stmt=$db->prepare('SELECT imagen,stock,estado FROM productos WHERE id_producto=? FOR UPDATE'); $stmt->execute([$id]); $actual=$stmt->fetch();
            if(!$actual) throw new RuntimeException('El producto ya no existe.');
            $imagen=$actual['imagen'];
            $anterior=$actual;
        }
        $clasificacion=[];
        foreach(['categorias'=>'id_categoria','marcas'=>'id_marca'] as $tabla=>$campo) {
            $stmt=$db->prepare("SELECT nombre FROM $tabla WHERE $campo=?"); $stmt->execute([$datos[$campo]]);
            $clasificacion[$tabla]=$stmt->fetchColumn();
            if($clasificacion[$tabla]===false) throw new RuntimeException('La categoría o marca seleccionada ya no existe.');
        }
        validar_clasificacion_producto($datos['nombre'],$clasificacion['categorias'],$clasificacion['marcas']);
        $imagenNueva=guardar_imagen_producto($archivo);
        $valores=[$datos['codigo'],$datos['nombre'],$datos['descripcion'],$datos['id_categoria'],$datos['id_marca'],decimal_centimos(precio_centimos($datos['precio'])),$datos['stock'],$imagenNueva ?? $imagen,$datos['estado']];
        if($id) { $stmt=$db->prepare('UPDATE productos SET codigo=?,nombre=?,descripcion=?,id_categoria=?,id_marca=?,precio=?,stock=?,imagen=?,estado=? WHERE id_producto=?'); $valores[]=$id; }
        else $stmt=$db->prepare('INSERT INTO productos(codigo,nombre,descripcion,id_categoria,id_marca,precio,stock,imagen,estado) VALUES(?,?,?,?,?,?,?,?,?)');
        $stmt->execute($valores);
        $resultado=$id ?: (int)$db->lastInsertId();
        registrar_transicion_stock($db,['id_producto'=>$resultado]+$datos,$id?(int)$actual['stock']:0,$id?'ajuste':'alta',(string)$resultado,$id?'Ajuste desde la ficha del producto':'Alta de producto');
        $db->commit();
        return $resultado;
    } catch(Throwable $error) {
        if($db->inTransaction()) $db->rollBack();
        // Solo se elimina el archivo generado por esta operación fallida.
        if($imagenNueva) @unlink(__DIR__.'/../uploads/productos/'.$imagenNueva);
        throw $error;
    }
}
