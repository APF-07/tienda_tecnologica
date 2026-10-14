<?php
if(PHP_SAPI!=='cli') { http_response_code(404);exit; }
require __DIR__.'/../config/conexion.php';require_once __DIR__.'/../includes/catalogo.php';
$categorias=[];foreach($conexion->query('SELECT * FROM categorias')->fetchAll() as $c) $categorias[clave_catalogo($c['nombre'])]=$c;
$marcas=[];foreach($conexion->query('SELECT * FROM marcas')->fetchAll() as $m) $marcas[clave_catalogo($m['nombre'])]=$m;
$cambios=[];
foreach($conexion->query('SELECT p.*,c.nombre categoria,m.nombre marca FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca ORDER BY p.id_producto')->fetchAll() as $p) {
    $tipo=categoria_por_nombre($p['nombre']);if(!$tipo || !isset($categorias[$tipo])) continue;
    $marca=clave_catalogo($p['marca']);
    foreach($marcas as $clave=>$m) if(preg_match('/\b'.preg_quote($clave,'/').'\b/',clave_catalogo($p['nombre'])) && marca_admitida_producto($p['nombre'],$tipo,$clave)) { $marca=$clave;break; }
    if(!marca_admitida_producto($p['nombre'],$tipo,$marca)) {
        $posibles=array_values(array_filter(array_keys($marcas),fn($clave)=>marca_admitida_producto($p['nombre'],$tipo,$clave)));
        if(!$posibles) throw new RuntimeException('No existe una marca compatible con '.$tipo.'.');
        $marca=$posibles[(int)$p['id_producto']%count($posibles)];
    }
    $nombre=$p['nombre'];
    foreach(['TUF'=>'asus','ROG'=>'asus','Legion'=>'lenovo'] as $linea=>$fabricante) if($marca!==$fabricante) $nombre=preg_replace('/\b'.$linea.'\s*/u','',$nombre);
    $nombre=trim(preg_replace('/\s+/u',' ',$nombre));
    $categoriaId=(int)$categorias[$tipo]['id_categoria'];$marcaId=(int)$marcas[$marca]['id_marca'];
    if($categoriaId!==(int)$p['id_categoria'] || $marcaId!==(int)$p['id_marca'] || $nombre!==$p['nombre']) $cambios[]=['id'=>(int)$p['id_producto'],'anterior'=>['nombre'=>$p['nombre'],'categoria'=>(int)$p['id_categoria'],'marca'=>(int)$p['id_marca']],'nuevo'=>['nombre'=>$nombre,'categoria'=>$categoriaId,'marca'=>$marcaId]];
}
echo 'Productos con asignaciones a corregir: '.count($cambios).PHP_EOL;
if(!in_array('--aplicar',$argv,true)) { echo 'Vista previa. Usa --aplicar para guardar las correcciones.'.PHP_EOL;exit; }
$backup=__DIR__.'/../database/respaldo_catalogo_'.date('Ymd_His').'.json';
if(file_put_contents($backup,json_encode($cambios,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))===false) throw new RuntimeException('No se pudo crear el respaldo.');
try {
    $conexion->beginTransaction();$stmt=$conexion->prepare('UPDATE productos SET nombre=?,id_categoria=?,id_marca=? WHERE id_producto=?');
    foreach($cambios as $c) $stmt->execute([$c['nuevo']['nombre'],$c['nuevo']['categoria'],$c['nuevo']['marca'],$c['id']]);
    $conexion->commit();echo 'Catálogo corregido. IDs, precios, stock y pedidos conservados. Respaldo: '.basename($backup).PHP_EOL;
} catch(Throwable $e) { if($conexion->inTransaction()) $conexion->rollBack();throw $e; }
