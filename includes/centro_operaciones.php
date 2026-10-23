<?php
require_once __DIR__.'/evaluacion_prospectiva.php';

function preparar_centro_operaciones(PDO $db): void {
    preparar_evaluacion_prospectiva($db);
    $db->exec("CREATE TABLE IF NOT EXISTS acciones_dia (clave VARCHAR(64) PRIMARY KEY, dia VARCHAR(10) NOT NULL, tipo VARCHAR(30) NOT NULL, entidad INT NOT NULL, prioridad INT NOT NULL, titulo VARCHAR(190) NOT NULL, motivo TEXT NOT NULL, url VARCHAR(190) NOT NULL, estado VARCHAR(20) NOT NULL, vigente INT NOT NULL DEFAULT 1, creada VARCHAR(19) NOT NULL, actualizada VARCHAR(19) NOT NULL)");
    $db->exec('CREATE TABLE IF NOT EXISTS acciones_historial (id VARCHAR(64) PRIMARY KEY, accion VARCHAR(64) NOT NULL, fecha VARCHAR(19) NOT NULL, usuario INT NOT NULL, estado VARCHAR(20) NOT NULL, motivo TEXT NOT NULL)');
}

function sincronizar_acciones_dia(PDO $db,?string $directorio=null,?DateTimeImmutable $reloj=null): void {
    preparar_centro_operaciones($db);$ahora=$reloj??new DateTimeImmutable();$dia=$ahora->format('Y-m-d');$fecha=$ahora->format('Y-m-d H:i:s');$items=[];
    foreach($db->query('SELECT id_producto,nombre,stock FROM productos WHERE estado=1 AND stock<=5 ORDER BY stock,id_producto')->fetchAll(PDO::FETCH_ASSOC) as $p){
        $stock=(int)$p['stock'];$items[]=['inventario',(int)$p['id_producto'],$stock===0?0:1,'Reponer: '.mb_substr($p['nombre'],0,140),'Stock actual: '.$stock.' unidades. '.($stock===0?'Producto agotado.':'Umbral de stock bajo: hasta cinco unidades.').' Verifica proveedor y cantidad antes de comprar.','admin/productos/editar.php?id='.(int)$p['id_producto']];
    }
    $minutos=max(1,(int)(getenv('PEDIDOS_DEMORA_MINUTOS')?:5));$q=$db->prepare("SELECT id_pedido,fecha_pedido FROM pedidos WHERE estado='Pendiente' AND fecha_pedido<=? ORDER BY fecha_pedido,id_pedido");$q->execute([$ahora->modify('-'.$minutos.' minutes')->format('Y-m-d H:i:s')]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $p)$items[]=['pedido',(int)$p['id_pedido'],1,'Revisar pedido #'.$p['id_pedido'],'Sigue pendiente desde '.$p['fecha_pedido'].'. Plazo de revisión: '.$minutos.' minutos. Revisar antes de confirmar o cancelar.','admin/pedidos/detalle.php?id='.(int)$p['id_pedido']];
    foreach(resumen_proyeccion_agente($directorio,$dia) as $tipo=>$p)if($p['estado']!=='Vigente')$items[]=['modelo_'.$tipo,0,2,'Actualizar pronóstico de '.$tipo,$p['estado'].'. Revisa el informe y ejecuta el entrenamiento para disponer de un pronóstico vigente.','admin/ml/'.$tipo.'_ia.php'];
    // Por día y entidad: las actualizaciones no reabren tareas atendidas/descartadas.
    $propia=!$db->inTransaction();if($propia)$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT clave FROM acciones_dia WHERE dia=? AND vigente=1');$q->execute([$dia]);$anteriores=$q->fetchAll(PDO::FETCH_COLUMN);$actuales=[];
        foreach($items as [$tipo,$entidad,$prioridad,$titulo,$motivo,$url]){
            $clave=hash('sha256',$dia.'|'.$tipo.'|'.$entidad);$actuales[]=$clave;
            $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?
                "INSERT INTO acciones_dia(clave,dia,tipo,entidad,prioridad,titulo,motivo,url,estado,vigente,creada,actualizada) VALUES(?,?,?,?,?,?,?,?,'Pendiente',1,?,?) ON CONFLICT(clave) DO UPDATE SET prioridad=excluded.prioridad,titulo=excluded.titulo,motivo=excluded.motivo,vigente=1,actualizada=excluded.actualizada,estado=CASE WHEN acciones_dia.estado='Resuelta' THEN 'Pendiente' ELSE acciones_dia.estado END":
                "INSERT INTO acciones_dia(clave,dia,tipo,entidad,prioridad,titulo,motivo,url,estado,vigente,creada,actualizada) VALUES(?,?,?,?,?,?,?,?,'Pendiente',1,?,?) ON DUPLICATE KEY UPDATE prioridad=VALUES(prioridad),titulo=VALUES(titulo),motivo=VALUES(motivo),vigente=1,actualizada=VALUES(actualizada),estado=IF(estado='Resuelta','Pendiente',estado)";
            $db->prepare($sql)->execute([$clave,$dia,$tipo,$entidad,$prioridad,$titulo,$motivo,$url,$fecha,$fecha]);
        }
        foreach(array_diff($anteriores,$actuales) as $clave)$db->prepare("UPDATE acciones_dia SET vigente=0,estado=CASE WHEN estado='Pendiente' THEN 'Resuelta' ELSE estado END,actualizada=? WHERE clave=?")->execute([$fecha,$clave]);
        if($propia)$db->commit();
    }catch(Throwable $e){if($propia&&$db->inTransaction())$db->rollBack();throw $e;}
}

function gestionar_accion_dia(PDO $db,string $clave,string $estado,string $motivo,int $usuario): void {
    if(!in_array($estado,['Atendida','Descartada','Pendiente'],true)||!preg_match('/^[a-f0-9]{64}$/D',$clave)||mb_strlen(trim($motivo))<5||mb_strlen($motivo)>500)throw new InvalidArgumentException('Selecciona un estado e indica un motivo de 5 a 500 caracteres.');
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT estado FROM acciones_dia WHERE clave=?'.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$clave]);$anterior=$q->fetchColumn();
        if($anterior===false)throw new InvalidArgumentException('La acción ya no está disponible.');
        if($anterior!==$estado){
            $fecha=date('Y-m-d H:i:s');$db->prepare('UPDATE acciones_dia SET estado=?,actualizada=? WHERE clave=?')->execute([$estado,$fecha,$clave]);
            $db->prepare('INSERT INTO acciones_historial(id,accion,fecha,usuario,estado,motivo) VALUES(?,?,?,?,?,?)')->execute([bin2hex(random_bytes(24)),$clave,$fecha,$usuario,$estado,trim($motivo)]);
        }
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function comparacion_real(PDO $db,string $tipo='ventas'): array {
    if(!in_array($tipo,['ventas','pedidos'],true))throw new InvalidArgumentException('Tipo de pronóstico inválido.');
    // Un pronóstico por día: el primero archivado antes de comenzar el objetivo.
    // Evita contar un mismo resultado siete veces por los distintos entrenamientos.
    $fechaObjetivo=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?"(p.objetivo||' 00:00:00')":"CONCAT(p.objetivo,' 00:00:00')";
    $q=$db->prepare("SELECT p.*,a.registrado,a.corte FROM ml_pronosticos p JOIN ml_pronosticos_auditoria a ON a.clave=p.clave WHERE p.tipo=? AND a.registrado<$fechaObjetivo ORDER BY p.objetivo DESC,a.registrado ASC,p.generado ASC,p.clave ASC LIMIT 3000");
    $q->execute([$tipo]);$dias=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $fila)if(!isset($dias[$fila['objetivo']])&&count($dias)<60)$dias[$fila['objetivo']]=$fila;
    ksort($dias);$error=0;$referencia=0;$n=0;$pendientes=0;
    foreach($dias as $fila){if($fila['evaluado']===null){$pendientes++;continue;}$error+=abs($fila['estimado']-$fila['observado']);$referencia+=abs($fila['referencia']-$fila['observado']);$n++;}
    return ['tipo'=>$tipo,'filas'=>array_values($dias),'evaluados'=>$n,'pendientes'=>$pendientes,'mae'=>$n?$error/$n:null,'mae_referencia'=>$n?$referencia/$n:null];
}
