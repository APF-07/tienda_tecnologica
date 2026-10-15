<?php
require_once __DIR__.'/../config/app.php';

interface AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array;
}

final class AgenteInventario implements AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array {
        $filas=$db->query('SELECT id_producto,codigo,nombre,stock FROM productos WHERE estado=1 AND stock BETWEEN 0 AND 5 ORDER BY stock,id_producto')->fetchAll(PDO::FETCH_ASSOC);
        return ['emisor'=>'inventario','destino'=>'analisis','objetivo'=>'Detectar productos activos que requieren revisión de stock','productos'=>$filas,'total'=>count($filas),'agotados'=>count(array_filter($filas,static fn($p)=>(int)$p['stock']===0)),'criterio'=>'Stock bajo: 0 a 5 unidades; solo productos activos.'];
    }
}

final class AgenteAnalisis implements AgenteTienda {
    public function ejecutar(PDO $db,array $mensaje): array {
        $hoy=new DateTimeImmutable($mensaje['fecha']);$inicio=$hoy->setTime(0,0)->modify('-29 days');$fin=$hoy->setTime(0,0)->modify('+1 day');
        $q=$db->prepare("SELECT d.id_producto,SUM(d.cantidad) unidades FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido=d.id_pedido WHERE p.estado IN ('Confirmado','En preparación','Enviado','Entregado') AND p.fecha_pedido>=? AND p.fecha_pedido<? GROUP BY d.id_producto");$q->execute([$inicio->format('Y-m-d H:i:s'),$fin->format('Y-m-d H:i:s')]);$ventas=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $f)$ventas[(int)$f['id_producto']]=(int)$f['unidades'];
        $alertas=[];
        foreach($mensaje['inventario']['productos'] as $producto){
            $stock=(int)$producto['stock'];$unidades=$ventas[(int)$producto['id_producto']]??0;$diarias=$unidades/30;
            $cobertura=$diarias>0?round($stock/$diarias,1):null;
            $prioridad=$stock===0?'Crítica':($cobertura!==null&&$cobertura<=3?'Alta':'Revisar');
            $alertas[]=$producto+['prioridad'=>$prioridad,'vendidas_30_dias'=>$unidades,'cobertura_dias'=>$cobertura,'reposicion_sugerida'=>max(0,max(6,(int)ceil($diarias*14))-$stock),'motivo'=>$stock===0?'Sin existencias.':($cobertura===null?'Stock bajo sin ventas confirmadas en el periodo.':'Stock bajo; cobertura estimada con el ritmo de ventas de los últimos 30 días.')];
        }
        $peso=['Crítica'=>0,'Alta'=>1,'Revisar'=>2];usort($alertas,static fn($a,$b)=>[$peso[$a['prioridad']],$a['stock'],$a['id_producto']]<=>[$peso[$b['prioridad']],$b['stock'],$b['id_producto']]);
        return ['emisor'=>'analisis','destino'=>'notificaciones','objetivo'=>'Priorizar hallazgos usando ventas confirmadas y existencias','alertas'=>$alertas,'periodo'=>['inicio'=>$inicio->format('Y-m-d'),'fin'=>$fin->modify('-1 day')->format('Y-m-d')],'metodo'=>'Reglas de prioridad y media de ventas de 30 días. La cobertura es una estimación histórica, no una predicción ML ni una fecha de agotamiento garantizada. Reposición: objetivo de 14 días con mínimo de 6 unidades; requiere revisión humana.'];
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

final class CoordinadorTienda {
    public function ejecutar(PDO $db,array $anteriores=[],?string $fecha=null,array $recuperaciones=[]): array {
        $fecha=$fecha??date('Y-m-d H:i:s');$mensaje=['fecha'=>$fecha,'anteriores'=>$anteriores,'recuperaciones'=>$recuperaciones];$traza=[];
        foreach(['inventario'=>new AgenteInventario(),'analisis'=>new AgenteAnalisis(),'notificaciones'=>new AgenteNotificaciones()] as $nombre=>$agente){
            $inicio=microtime(true);$resultado=$agente->ejecutar($db,$mensaje);$mensaje[$nombre]=$resultado;
            $traza[]=['agente'=>$nombre,'destino'=>$resultado['destino'],'objetivo'=>$resultado['objetivo'],'estado'=>'Completado','duracion_ms'=>round((microtime(true)-$inicio)*1000,1)];
        }
        return ['version'=>1,'fecha'=>$fecha,'traza'=>$traza,'inventario'=>$mensaje['inventario'],'analisis'=>$mensaje['analisis'],'notificaciones'=>$mensaje['notificaciones']];
    }
}

function estado_multiagentes(): ?array {
    $archivo=__DIR__.'/../storage/multiagentes/estado.json';
    if(!is_file($archivo))return null;$datos=json_decode((string)file_get_contents($archivo),true);
    if(!is_array($datos)||($datos['version']??null)!==1)throw new RuntimeException('No se pudo leer el estado de los agentes.');return $datos;
}
function ejecutar_multiagentes(PDO $db,bool $enviarTelegram=false): array {
    $dir=__DIR__.'/../storage/multiagentes';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el registro de agentes.');
    $lock=fopen($dir.'/ejecucion.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Ya hay una revisión en ejecución. Intenta nuevamente en unos segundos.');
    try{
        $anterior=estado_multiagentes();$r=(new CoordinadorTienda())->ejecutar($db,$anterior['notificaciones']['activas']??[],null,$anterior['notificaciones']['recuperadas']??[]);
        $tmp=$dir.'/estado.tmp';$json=json_encode($r,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        if(file_put_contents($tmp,$json,LOCK_EX)===false||!rename($tmp,$dir.'/estado.json'))throw new RuntimeException('No se pudo guardar la revisión.');
        if($enviarTelegram){
            require_once __DIR__.'/telegram.php';
            $r['telegram']=enviar_alertas_telegram($r,configuracion_telegram());
            $r['notificaciones']['canal']='Panel local. Telegram: '.$r['telegram']['estado'].'.';
            guardar_registro_telegram($dir.'/estado.json',$r);
        }
        return $r;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
