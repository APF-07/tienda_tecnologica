<?php
require_once __DIR__.'/../config/app.php';
function guardar_clasificacion(PDO $db,string $tabla,array $entrada,int $id=0): void {
    if(!in_array($tabla,['categorias','marcas'],true)) throw new InvalidArgumentException('Clasificación no válida.');
    $nombre=is_string($entrada['nombre'] ?? null)?trim($entrada['nombre']):'';
    $descripcion=is_string($entrada['descripcion'] ?? null)?trim($entrada['descripcion']):'';
    $estado=is_scalar($entrada['estado'] ?? null)?(string)$entrada['estado']:'1';
    if($nombre==='' || longitud_texto($nombre)>100) throw new RuntimeException('Escribe un nombre de hasta 100 caracteres.');
    if(strlen($descripcion)>60000 || !in_array($estado,['0','1'],true)) throw new RuntimeException('Revisa la descripción y el estado.');
    $campo=$tabla==='categorias'?'id_categoria':'id_marca';
    if($id) { $stmt=$db->prepare("UPDATE $tabla SET nombre=?,descripcion=?,estado=? WHERE $campo=?");$stmt->execute([$nombre,$descripcion,$estado,$id]); }
    else { $stmt=$db->prepare("INSERT INTO $tabla(nombre,descripcion,estado) VALUES(?,?,?)");$stmt->execute([$nombre,$descripcion,$estado]); }
}
