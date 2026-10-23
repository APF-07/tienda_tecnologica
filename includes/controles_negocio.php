<?php
require_once __DIR__.'/../config/app.php';
function informe_controles_negocio(): array {
    $ruta=__DIR__.'/../ml/cache_controles_negocio.json';
    $r=is_file($ruta)?json_decode((string)file_get_contents($ruta),true):null;
    return is_array($r)&&($r['schema_version']??0)===1?$r:[];
}
function confirmar_categoria_ml(PDO $db,array $entrada,int $actor,?array $informe=null): void {
    $r=$informe??informe_controles_negocio();$id=filter_var($entrada['producto']??null,FILTER_VALIDATE_INT);
    if(!$id||!hash_equals((string)($r['datos_id']??''),(string)($entrada['informe']??'')))throw new RuntimeException('El informe cambió. Recarga y revisa la propuesta.');
    $propuesta=null;foreach($r['controles']['clasificacion']['filas']??[] as $fila)if($fila['id']===$id)$propuesta=$fila;
    if(!$propuesta)throw new RuntimeException('No existe una propuesta para este producto.');
    $db->exec('CREATE TABLE IF NOT EXISTS ml_revision_categorias (id VARCHAR(40) PRIMARY KEY,producto INT NOT NULL,anterior INT NOT NULL,propuesta INT NOT NULL,actor INT NOT NULL,fecha VARCHAR(19) NOT NULL,informe VARCHAR(40) NOT NULL)');
    $db->beginTransaction();try{
        $q=$db->prepare('SELECT nombre,descripcion,id_categoria FROM productos WHERE id_producto=?'.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$id]);$p=$q->fetch(PDO::FETCH_ASSOC);
        if(!$p||(int)$p['id_categoria']!==$propuesta['actual']||!hash_equals($propuesta['huella'],hash('sha256',$p['nombre']."\n".($p['descripcion']??''))))throw new RuntimeException('El producto cambió después del entrenamiento. Reentrena antes de confirmar.');
        $q=$db->prepare('SELECT estado FROM categorias WHERE id_categoria=?');$q->execute([$propuesta['propuesta']]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('La categoría propuesta no está activa.');
        if($propuesta['actual']===$propuesta['propuesta'])throw new RuntimeException('El producto ya tiene esa categoría.');
        $db->prepare('UPDATE productos SET id_categoria=? WHERE id_producto=?')->execute([$propuesta['propuesta'],$id]);
        $db->prepare('INSERT INTO ml_revision_categorias VALUES(?,?,?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),$id,$propuesta['actual'],$propuesta['propuesta'],$actor,date('Y-m-d H:i:s'),$r['datos_id']]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
