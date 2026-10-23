<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/registro_agentes.php';
require_once __DIR__.'/proyeccion_agente.php';
require_once __DIR__.'/evaluacion_prospectiva.php';
require_once __DIR__.'/centro_operaciones.php';

interface AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array;
}

final class AgenteInventario implements AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array {
        $filas=$db->query('SELECT id_producto,codigo,nombre,stock FROM productos WHERE estado=1 AND stock BETWEEN 0 AND 5 ORDER BY stock,id_producto')->fetchAll(PDO::FETCH_ASSOC);
        return ['emisor'=>'inventario','destino'=>'analisis','objetivo'=>'Detectar productos activos que requieren revisión de stock','productos'=>$filas,'total'=>count($filas),'agotados'=>count(array_filter($filas,static fn($p)=>(int)$p['stock']===0)),'criterio'=>'Stock bajo: 0 a 5 unidades; solo productos activos.'];
    }
}

final class AgenteSeguimiento implements AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array {
        $minutos=max(1,(int)(getenv('PEDIDOS_DEMORA_MINUTOS')?:5));
        $limite=(new DateTimeImmutable($mensaje['fecha']))->modify('-'.$minutos.' minutes')->format('Y-m-d H:i:s');
        $q=$db->prepare("SELECT COUNT(*) FROM pedidos WHERE estado='Pendiente' AND fecha_pedido<=?");$q->execute([$limite]);$total=(int)$q->fetchColumn();
        $q=$db->prepare("SELECT id_pedido,fecha_pedido FROM pedidos WHERE estado='Pendiente' AND fecha_pedido<=? ORDER BY fecha_pedido LIMIT 20");$q->execute([$limite]);
        return ['emisor'=>'seguimiento','destino'=>'analisis','objetivo'=>'Detectar pedidos pendientes fuera del plazo sin cambiar su estado','total'=>$total,'minutos'=>$minutos,'pedidos'=>$q->fetchAll(PDO::FETCH_ASSOC),'criterio'=>'Antigüedad desde la creación; incluye histórico en el indicador. Avisos solo desde activación.'];
    }
}
final class AgenteProyeccion implements AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array {
        $riesgos=[];$ruta=($mensaje['directorio_ml']??__DIR__.'/../ml').'/cache_todos_productos.json';$informe=is_file($ruta)?json_decode((string)file_get_contents($ruta),true):null;
        $fecha=new DateTimeImmutable($mensaje['fecha']);
        if(($informe['fecha_corte']??'')>=$fecha->modify('-1 day')->format('Y-m-d')){
            $consulta=$db->prepare('SELECT stock,estado FROM productos WHERE id_producto=?');
            foreach($informe['modelos']??[] as $m)if(($m['tipo_modelo']??'')==='demanda_producto')foreach($m['predicciones']??[] as $p){
                if(!$p['fecha_agotamiento']||$p['agotado']||$p['fecha_agotamiento']>$fecha->modify('+7 days')->format('Y-m-d'))continue;
                $consulta->execute([$p['id_producto']]);$actual=$consulta->fetch(PDO::FETCH_ASSOC);
                if($actual&&(int)$actual['estado']===1&&(int)$actual['stock']===(int)$p['stock'])$riesgos[]=['id_producto'=>$p['id_producto'],'fecha_estimada'=>$p['fecha_agotamiento'],'unidades_7d'=>$p['unidades_7d']];
            }
        }
        return ['emisor'=>'proyeccion','destino'=>'analisis','objetivo'=>'Consultar pronósticos ML y comprobar su vigencia','informes'=>resumen_proyeccion_agente($mensaje['directorio_ml']??null,$mensaje['fecha']),'individuales'=>resumen_modelos_individuales($mensaje['directorio_ml']??null),'riesgos_agotamiento'=>$riesgos,'metodo'=>'Lectura de informes evaluados; no entrena al refrescar ni inventa pronósticos ausentes.'];
    }
}
final class AgenteAnalisis implements AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array {
        preparar_gestion_avanzada($db);$condiciones=condiciones_reposicion($db);
        $hoy=new DateTimeImmutable($mensaje['fecha']);$inicio=$hoy->setTime(0,0)->modify('-29 days');$fin=$hoy->setTime(0,0)->modify('+1 day');
        $q=$db->prepare("SELECT d.id_producto,SUM(d.cantidad) unidades FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido=d.id_pedido WHERE p.estado IN ('Confirmado','En preparación','Enviado','Entregado') AND p.fecha_pedido>=? AND p.fecha_pedido<? GROUP BY d.id_producto");$q->execute([$inicio->format('Y-m-d H:i:s'),$fin->format('Y-m-d H:i:s')]);$ventas=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $f)$ventas[(int)$f['id_producto']]=(int)$f['unidades'];
        $alertas=[];
        foreach($mensaje['inventario']['productos'] as $producto){
            $stock=(int)$producto['stock'];$unidades=$ventas[(int)$producto['id_producto']]??0;$diarias=$unidades/30;
            $cobertura=$diarias>0?round($stock/$diarias,1):null;
            $prioridad=$stock===0?'Crítica':($cobertura!==null&&$cobertura<=3?'Alta':'Revisar');
            $condicion=$condiciones[$producto['id_producto']]??['proveedor'=>null,'plazo'=>null,'pendientes'=>0];$dias=$condicion['plazo']===null?14:$condicion['plazo']+7;
            $alertas[]=$producto+['prioridad'=>$prioridad,'vendidas_30_dias'=>$unidades,'cobertura_dias'=>$cobertura,'proveedor'=>$condicion['proveedor'],'plazo_proveedor'=>$condicion['plazo'],'dias_objetivo'=>$dias,'unidades_pendientes'=>$condicion['pendientes'],'reposicion_sugerida'=>max(0,max(6,(int)ceil($diarias*$dias))-$stock-$condicion['pendientes']),'motivo'=>$stock===0?'Sin existencias.':($cobertura===null?'Stock bajo sin ventas confirmadas en el periodo.':'Stock bajo; cobertura estimada con el ritmo de ventas de los últimos 30 días.')];
        }
        $peso=['Crítica'=>0,'Alta'=>1,'Revisar'=>2];usort($alertas,static fn($a,$b)=>[$peso[$a['prioridad']],$a['stock'],$a['id_producto']]<=>[$peso[$b['prioridad']],$b['stock'],$b['id_producto']]);
        $recomendaciones=[];
        if(count($alertas))$recomendaciones[]=['prioridad'=>'Alta','texto'=>'Revisar '.count($alertas).' productos con stock de 0 a 5; priorizar los agotados.','url'=>'admin/productos/listar.php?estado=1&orden=stock&stock=bajo','fuente'=>'Inventario actual y ventas confirmadas de 30 días'];
        if(($mensaje['seguimiento']['total']??0)>0)$recomendaciones[]=['prioridad'=>'Alta','texto'=>'Atender '.$mensaje['seguimiento']['total'].' pedidos que siguen pendientes fuera del plazo.','url'=>'admin/pedidos/listar.php?estado=Pendiente','fuente'=>'Fecha de creación y estado actual del pedido'];
        foreach($mensaje['proyeccion']['informes']??[] as $tipo=>$informe)if($informe['estado']!=='Vigente')$recomendaciones[]=['prioridad'=>'Revisar','texto'=>'Actualizar el pronóstico de '.$tipo.': '.$informe['estado'].'.','url'=>'admin/ml/'.$tipo.'_ia.php','fuente'=>'Vigencia del informe ML; no se usa como predicción actual'];
        if(count($mensaje['proyeccion']['riesgos_agotamiento']??[]))$recomendaciones[]=['prioridad'=>'Revisar','texto'=>count($mensaje['proyeccion']['riesgos_agotamiento']).' productos podrían agotar sus existencias en siete días si no se reponen.','url'=>'admin/ml/productos_ia.php','fuente'=>'Demanda por producto evaluada; fecha orientativa con stock sin cambios'];
        return ['emisor'=>'analisis','destino'=>'notificaciones','objetivo'=>'Priorizar inventario, pedidos demorados y vigencia del ML','recomendaciones'=>$recomendaciones,'alertas'=>$alertas,'periodo'=>['inicio'=>$inicio->format('Y-m-d'),'fin'=>$fin->modify('-1 day')->format('Y-m-d')],'metodo'=>'Reglas de prioridad y media de ventas de 30 días. La cobertura es histórica, no una fecha ML garantizada. Reposición: plazo del último proveedor activo más siete días; sin proveedor, 14 días; mínimo seis unidades objetivo. Se descuentan stock y órdenes abiertas pendientes, cuya llegada debe verificarse. Requiere revisión humana.'];
    }
}

final class AgenteNotificaciones implements AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array {
        $anteriores=$mensaje['anteriores']??[];$actuales=[];$nuevas=0;
        foreach($mensaje['analisis']['alertas'] as $alerta){
            $clave=(string)$alerta['id_producto'];$firma=hash('sha256',json_encode([$alerta['stock'],$alerta['prioridad']]));$anterior=$anteriores[$clave]??null;
            $nueva=!$anterior||($anterior['firma']??'')!==$firma;if($nueva)$nuevas++;
            $actuales[$clave]=['firma'=>$firma,'creada'=>$nueva?$mensaje['fecha']:$anterior['creada'],'actualizada'=>$mensaje['fecha'],'estado'=>'Local','producto'=>$alerta,'mensaje'=>'Stock '.$alerta['prioridad'].' · '.$alerta['nombre'].' ('.$alerta['codigo'].') · '.$alerta['stock'].' unidades. Reposición sugerida: '.$alerta['reposicion_sugerida'].' unidades.'];
        }
        $recuperadas=$mensaje['recuperaciones']??[];$nuevasRecuperadas=0;
        $consulta=$db->prepare('SELECT id_producto,codigo,nombre,stock,estado FROM productos WHERE id_producto=?');
        foreach(array_diff_key($anteriores,$actuales) as $id=>$anterior){
            $consulta->execute([(int)$id]);$producto=$consulta->fetch(PDO::FETCH_ASSOC);
            // Desactivar o eliminar un producto no significa que se haya repuesto.
            if(!$producto||(int)$producto['estado']!==1||(int)$producto['stock']<=5)continue;
            $evento=hash('sha256',$id.'|'.$anterior['creada'].'|'.$anterior['firma']);
            if(isset($recuperadas[$evento]))continue;
            $recuperadas[$evento]=['tipo'=>'recuperado','firma'=>$evento,'creada'=>$mensaje['fecha'],'actualizada'=>$mensaje['fecha'],'stock_anterior'=>(int)$anterior['producto']['stock'],'producto'=>$producto,'mensaje'=>'Stock recuperado · '.$producto['nombre'].' · '.$producto['stock'].' unidades.'];$nuevasRecuperadas++;
        }
        // No enviar una recuperación pendiente si el producto ya volvió a stock bajo o se desactivó.
        foreach($recuperadas as $evento=>$recuperacion){$consulta->execute([(int)$recuperacion['producto']['id_producto']]);$producto=$consulta->fetch(PDO::FETCH_ASSOC);if(!$producto||(int)$producto['estado']!==1||(int)$producto['stock']<=5){unset($recuperadas[$evento]);continue;}$recuperadas[$evento]['producto']=$producto;}
        return ['emisor'=>'notificaciones','destino'=>'coordinador','objetivo'=>'Crear alertas de stock bajo y recuperado sin duplicarlas','nuevas'=>$nuevas,'activas'=>$actuales,'recuperadas'=>$recuperadas,'nuevas_recuperadas'=>$nuevasRecuperadas,'resueltas'=>count(array_diff_key($anteriores,$actuales)),'canal'=>'Panel local. Telegram pendiente de configuración; no se ha enviado ningún mensaje externo.'];
    }
}

final class AgenteAuditor implements AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array {
        $errores=[];$avisos=[];$comprobaciones=[];
        foreach(['inventario','seguimiento','proyeccion','analisis','notificaciones'] as $nombre){
            if(($mensaje[$nombre]['emisor']??null)!==$nombre)$errores[]='Falta la salida identificable de '.$nombre.'.';
        }
        $comprobaciones[]='Identidad y presencia de los cinco agentes de tareas';
        if((int)($mensaje['inventario']['total']??-1)!==count($mensaje['inventario']['productos']??[]))$errores[]='El total de inventario no coincide con su detalle.';
        $comprobaciones[]='Consistencia entre contador y detalle del inventario';
        if((int)$db->query('SELECT COUNT(*) FROM productos WHERE estado=1 AND stock<0')->fetchColumn()>0)$errores[]='Hay productos activos con existencias negativas.';
        $comprobaciones[]='Ausencia de stock negativo en productos activos';
        $ids=[];foreach($mensaje['analisis']['alertas']??[] as $a){
            if(isset($ids[$a['id_producto']]))$errores[]='Recomendación duplicada para un producto.';$ids[$a['id_producto']]=true;
            if((int)$a['reposicion_sugerida']<0)$errores[]='Reposición sugerida negativa.';
        }
        $comprobaciones[]='Reposiciones no negativas y productos sin duplicar';
        foreach($mensaje['proyeccion']['informes']??[] as $tipo=>$r){
            if(($r['estado']??'')!=='Vigente')$avisos[]='Pronóstico de '.$tipo.': '.($r['estado']??'Sin informe').'.';
            $wape=$r['metricas']['Error ponderado WAPE (%)']??null;
            if(is_numeric($wape)&&(float)$wape>=50)$avisos[]='Error histórico alto en '.$tipo.' (WAPE '.$wape.'%). Umbral operativo de revisión: 50%; no usar el pronóstico como única base de decisiones.';
        }
        $comprobaciones[]='Vigencia explícita de los pronósticos; no se sustituyen datos ausentes';
        return ['emisor'=>'auditor','destino'=>'coordinador','objetivo'=>'Verificar consistencia y vigencia antes de publicar el reporte y autorizar envíos',
            'estado'=>$errores?'Bloqueado':($avisos?'Con observaciones':'Aprobado'),'autoriza_envio'=>!$errores,'errores'=>$errores,'observaciones'=>$avisos,'comprobaciones'=>$comprobaciones,
            'alcance'=>'Auditoría por reglas de consistencia. No certifica exactitud futura del ML ni sustituye una revisión contable.'];
    }
}

function reporte_agentes(array $r): string {
    $lineas=['🤖 TechStore · Reporte de agentes',$r['fecha'],'Auditor: '.$r['auditor']['estado'],
        'Stock bajo: '.$r['inventario']['total'].' · Agotados: '.$r['inventario']['agotados'],
        'Pedidos fuera del plazo: '.$r['seguimiento']['total']];
    foreach(array_slice($r['analisis']['recomendaciones'],0,3) as $a)$lineas[]='• '.$a['texto'];
    foreach($r['proyeccion']['informes']??[] as $tipo=>$p)$lineas[]='ML '.ucfirst($tipo).': '.($p['estado']??'Sin informe');
    foreach($r['auditor']['errores'] as $e)$lineas[]='Bloqueo: '.$e;
    foreach(array_slice($r['auditor']['observaciones'],0,2) as $e)$lineas[]='Revisión: '.$e;
    $lineas[]='Recomendaciones para revisión humana; no se realizan compras automáticamente.';
    return implode("\n",$lineas);
}

final class CoordinadorTienda {
    public function ejecutar(PDO $db,array $anteriores=[],?string $fecha=null,array $recuperaciones=[]): array {
        $fecha=$fecha??date('Y-m-d H:i:s');$mensaje=['fecha'=>$fecha,'anteriores'=>$anteriores,'recuperaciones'=>$recuperaciones];$traza=[];
        foreach(['inventario'=>new AgenteInventario(),'seguimiento'=>new AgenteSeguimiento(),'proyeccion'=>new AgenteProyeccion(),'analisis'=>new AgenteAnalisis(),'notificaciones'=>new AgenteNotificaciones(),'auditor'=>new AgenteAuditor()] as $nombre=>$agente){
            $inicio=microtime(true);$resultado=$agente->ejecutar($db,$mensaje);$mensaje[$nombre]=$resultado;
            $traza[]=['agente'=>$nombre,'destino'=>$resultado['destino'],'objetivo'=>$resultado['objetivo'],'estado'=>'Completado','duracion_ms'=>round((microtime(true)-$inicio)*1000,1)];
        }
        $r=['version'=>1,'fecha'=>$fecha,'traza'=>$traza,'inventario'=>$mensaje['inventario'],'seguimiento'=>$mensaje['seguimiento'],'proyeccion'=>$mensaje['proyeccion'],'analisis'=>$mensaje['analisis'],'notificaciones'=>$mensaje['notificaciones'],'auditor'=>$mensaje['auditor']];
        $r['reporte']=reporte_agentes($r);return $r;
    }
}

function estado_multiagentes(?PDO $db=null): ?array {
    if($db!==null){preparar_registro_agentes($db);$estado=leer_estado_agentes($db,'ultima');if($estado!==null)return $estado;}
    $archivo=__DIR__.'/../storage/multiagentes/estado.json';
    if(!is_file($archivo))return null;$datos=json_decode((string)file_get_contents($archivo),true);
    if(!is_array($datos)||($datos['version']??null)!==1)throw new RuntimeException('No se pudo leer el estado de los agentes.');return $datos;
}
function ejecutar_multiagentes(PDO $db,bool $enviarTelegram=false): array {
    preparar_registro_agentes($db);
    $dir=__DIR__.'/../storage/multiagentes';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el registro de agentes.');
    $lock=fopen($dir.'/ejecucion.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Ya hay una revisión en ejecución. Intenta nuevamente en unos segundos.');
    try{
        $idEjecucion=bin2hex(random_bytes(16));$db->prepare('INSERT INTO agentes_ejecuciones(id,inicio,estado) VALUES(?,?,?)')->execute([$idEjecucion,date('Y-m-d H:i:s'),'En curso']);
        $anterior=estado_multiagentes($db);$r=(new CoordinadorTienda())->ejecutar($db,$anterior['notificaciones']['activas']??[],null,$anterior['notificaciones']['recuperadas']??[]);
        foreach($db->query('SELECT id_producto,codigo,nombre,stock,estado FROM productos')->fetchAll(PDO::FETCH_ASSOC) as $p)registrar_transicion_stock($db,$p);
        $r['proyeccion']['seguimiento_real']=evaluar_pronosticos_guardados($db);
        sincronizar_acciones_dia($db);
        escribir_estado_agentes($db,'reporte-'.date('Y-m-d'),['fecha'=>$r['fecha'],'texto'=>$r['reporte'],'auditor'=>$r['auditor']]);
        escribir_estado_agentes($db,'ultima',$r);
        $tmp=$dir.'/estado.tmp';$json=json_encode($r,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if(file_put_contents($tmp,$json,LOCK_EX)===false||!rename($tmp,$dir.'/estado.json'))throw new RuntimeException('No se pudo guardar la revisión.');
        if($enviarTelegram&&$r['auditor']['autoriza_envio']){
            require_once __DIR__.'/telegram.php';
            $operativas=revisar_alertas_operativas($db);$r['telegram']=['estado'=>$operativas['estado'],'enviadas'=>$operativas['enviadas'],'detalle'=>$operativas['enviadas'].' avisos enviados desde el registro MySQL.'];
            $r['notificaciones']['canal']='Panel local. Telegram: '.$r['telegram']['estado'].'.';
            guardar_registro_telegram($dir.'/estado.json',$r);
        }
        if($enviarTelegram&&!$r['auditor']['autoriza_envio'])$r['telegram']=['estado'=>'Bloqueado por auditor','enviadas'=>0,'detalle'=>'Corrige las inconsistencias del reporte antes de enviar.'];
        escribir_estado_agentes($db,'ultima',$r);
        $resumen=['fecha'=>$r['fecha'],'traza'=>$r['traza'],'stock_bajo'=>$r['inventario']['total'],'demoras'=>$r['seguimiento']['total'],'recomendaciones'=>$r['analisis']['recomendaciones'],'auditor'=>$r['auditor'],'reporte'=>$r['reporte'],'telegram'=>$r['telegram']??null];
        $db->prepare('UPDATE agentes_ejecuciones SET fin=?,estado=?,resultado=? WHERE id=?')->execute([date('Y-m-d H:i:s'),'Completado',json_encode($resumen,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$idEjecucion]);
        return $r;
    }catch(Throwable $e){if(isset($idEjecucion))$db->prepare('UPDATE agentes_ejecuciones SET fin=?,estado=?,error=? WHERE id=?')->execute([date('Y-m-d H:i:s'),'Error','La ejecución falló; revisar registro del servidor.',$idEjecucion]);throw $e;}finally{flock($lock,LOCK_UN);fclose($lock);}
}
