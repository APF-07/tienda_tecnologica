<?php
require_once __DIR__.'/alertas_operativas.php';
require_once __DIR__.'/gestion_avanzada.php';
function preparar_registro_agentes(PDO $db): void {
    preparar_gestion_avanzada($db);
    preparar_alertas_operativas($db);
    $db->exec('CREATE TABLE IF NOT EXISTS agentes_estado (clave VARCHAR(100) PRIMARY KEY, datos MEDIUMTEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS agentes_ejecuciones (id VARCHAR(40) PRIMARY KEY, inicio VARCHAR(19) NOT NULL, fin VARCHAR(19) NULL, estado VARCHAR(20) NOT NULL, resultado MEDIUMTEXT NULL, error TEXT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS agentes_inventario (id_producto INT PRIMARY KEY, stock INT NOT NULL, activo INT NOT NULL, revision INT NOT NULL)');
}
function leer_estado_agentes(PDO $db,string $clave): ?array {
    $q=$db->prepare('SELECT datos FROM agentes_estado WHERE clave=?');$q->execute([$clave]);$valor=$q->fetchColumn();
    return $valor===false?null:json_decode($valor,true,512,JSON_THROW_ON_ERROR);
}
function escribir_estado_agentes(PDO $db,string $clave,array $valor): void {
    $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO agentes_estado(clave,datos) VALUES(?,?) ON CONFLICT(clave) DO UPDATE SET datos=excluded.datos':'INSERT INTO agentes_estado(clave,datos) VALUES(?,?) ON DUPLICATE KEY UPDATE datos=VALUES(datos)';
    $db->prepare($sql)->execute([$clave,json_encode($valor,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR)]);
}
// Un mismo registro de transición para ventas, edición y revisiones de inventario.
function registrar_transicion_stock(PDO $db,array $p,?int $anterior=null,string $origen='conciliacion',string $referencia='',string $motivo='Cambio observado al revisar existencias',?int $usuario=null): void {
    $propia=!$db->inTransaction();if($propia)$db->beginTransaction();
    try{
        // Leer el stock vigente con bloqueo evita registrar una lectura antigua del agente.
        $actual=$db->prepare('SELECT id_producto,codigo,nombre,stock,estado FROM productos WHERE id_producto=?'.bloqueo_gestion($db));$actual->execute([$p['id_producto']]);$vigente=$actual->fetch(PDO::FETCH_ASSOC);
        if(!$vigente){if($propia)$db->commit();return;}$p=array_replace($p,$vigente);
        $q=$db->prepare('SELECT * FROM agentes_inventario WHERE id_producto=?'.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$p['id_producto']]);$prev=$q->fetch(PDO::FETCH_ASSOC);
        $stock=(int)$p['stock'];$activo=(int)($p['estado']??1);$antes=$prev?(int)$prev['stock']:($anterior??$stock);$version=$prev?(int)$prev['revision']+1:1;
        movimiento_gestion($db,$p,$anterior,$origen,$referencia,$motivo,$usuario);
        if($prev&&$antes===$stock&&(int)$prev['activo']===$activo){if($propia)$db->commit();return;}
        if($prev)$db->prepare('UPDATE agentes_inventario SET stock=?,activo=?,revision=? WHERE id_producto=?')->execute([$stock,$activo,$version,$p['id_producto']]);
        else $db->prepare('INSERT INTO agentes_inventario(id_producto,stock,activo,revision) VALUES(?,?,?,?)')->execute([$p['id_producto'],$stock,$activo,$version]);
        $recuperado=$antes<=5&&$stock>5&&(!$prev||(int)$prev['activo']===1);
        $cambio=$antes!==$stock||($prev&&(int)$prev['activo']!==$activo);
        if($activo===1&&$cambio&&($stock<=5||$recuperado)){
            $texto=($recuperado?'✅ STOCK RECUPERADO':'⚠️ STOCK BAJO')."\n".mb_substr($p['nombre'],0,120).' ['.$p['codigo']."]\nStock anterior: $antes unidades\nStock actual: $stock unidades".($stock===0?"\nProducto agotado · prioridad crítica.":'');
            registrar_alerta_operativa($db,'stock-'.$p['id_producto'].'-'.$version,$recuperado?'stock_recuperado':'stock_bajo',(int)$p['id_producto'],$texto);
        }
        if($propia)$db->commit();
    }catch(Throwable $e){if($propia&&$db->inTransaction())$db->rollBack();throw $e;}
}
