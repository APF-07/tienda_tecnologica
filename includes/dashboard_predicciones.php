<?php
require_once __DIR__.'/predicciones_individuales.php';
function datos_dashboard_predicciones(PDO $db,string $vista,?int $id=null,?string $directorio=null): array {
    $areas=['ventas'=>'ventas','pedidos'=>'pedidos','recompra'=>'clientes','inventario'=>'productos','cancelacion'=>'pedidos'];
    if(!isset($areas[$vista]))throw new RuntimeException('Vista no válida.');
    $area=$areas[$vista];$tipo=['recompra'=>'recompra','inventario'=>'demanda_producto','cancelacion'=>'cancelacion'][$vista]??null;
    $ruta=($directorio??__DIR__.'/../ml').'/cache_todos_'.$area.'.json';$informe=is_file($ruta)?json_decode((string)file_get_contents($ruta),true):null;$modelo=null;
    if(($informe['schema_version']??0)===2)foreach($informe['modelos']??[] as $m){
        if($tipo!==null&&($m['tipo_modelo']??null)===$tipo){$modelo=$m;break;}
        if($tipo===null)foreach($m['graficos']??[] as $g)if(str_starts_with($g['titulo']??'','Próximos 7 días')){$modelo=$m;break 2;}
    }
    $r=['vista'=>$vista,'estado'=>$modelo['estado']??'sin_informe','generado'=>$informe['generado']??null,'corte'=>$informe['fecha_corte']??null,'vigente'=>false,'metricas'=>$modelo['metricas']??[],'calidad'=>$modelo['calidad']??null,'opciones'=>array_map('intval',array_keys($modelo['predicciones']??[])),'id'=>$id,'graficos'=>[],'tarjetas'=>[],'notas'=>[],'detalle'=>'','evaluacion_url'=>ruta('admin/ml/'.$area.'_ia.php')];
    $r['vigente']=$r['corte']!==null&&$r['corte']===(new DateTimeImmutable('yesterday'))->format('Y-m-d');
    $r['actividad']=in_array($vista,['ventas','pedidos'],true)?actividad_actual_predicciones($db,$vista):null;
    if($vista==='recompra')return dashboard_recompra_cliente($db,$r,$modelo,$id,$directorio);
    if(!$modelo||$modelo['estado']!=='listo'){$r['detalle']='No hay un modelo evaluado disponible para esta vista. Entrena desde Analítica y proyección; no se inventan gráficos.';return $r;}
    if($vista==='ventas'||$vista==='pedidos'){
        if(isset($modelo['control']))$r['notas'][]='Modo '.$modelo['control']['modo'].': '.$modelo['control']['utilizado'].'. '.$modelo['control']['motivo'];
        if(isset($modelo['decision']))$r['notas'][]=$modelo['decision']['advertencia'];
        $g=null;foreach($modelo['graficos'] as $grafico)if(str_starts_with($grafico['titulo']??'','Próximos 7 días')){$g=$grafico;break;}
        if(!$g)return $r;
        $corte=new DateTimeImmutable($r['corte']);$inicio=$corte->modify('-29 days');$fin=$corte->modify('+1 day');
        $estados=$vista==='ventas'?"'Confirmado','En preparación','Enviado','Entregado'":"'Pendiente','Confirmado','En preparación','Enviado','Entregado'";
        $suma=$vista==='ventas'?'SUM(total)':'COUNT(*)';$q=$db->prepare("SELECT DATE(fecha_pedido) fecha,$suma valor FROM pedidos WHERE estado IN ($estados) AND fecha_pedido>=? AND fecha_pedido<? GROUP BY DATE(fecha_pedido) ORDER BY fecha");$q->execute([$inicio->format('Y-m-d'),$fin->format('Y-m-d')]);$mapa=array_column($q->fetchAll(PDO::FETCH_ASSOC),'valor','fecha');$labels=[];$reales=[];
        for($i=0;$i<30;$i++){$dia=$inicio->modify('+'.$i.' days')->format('Y-m-d');$labels[]=$dia;$reales[]=(float)($mapa[$dia]??0);}
        $futuro=$g['datos']['valores'];$etiquetas=$g['datos']['labels'];$labels=array_merge($labels,$etiquetas);
        $r['graficos'][]=['titulo'=>'Histórico y proyección de '.$vista,'tipo'=>'linea','unidad'=>$vista==='ventas'?'PEN':'pedidos','labels'=>$labels,'series'=>[['nombre'=>'Histórico observado','valores'=>array_merge($reales,array_fill(0,count($futuro),null))],['nombre'=>'Pronóstico','valores'=>array_merge(array_fill(0,29,null),[end($reales)],$futuro),'discontinua'=>true]]];
        $r['tarjetas']=['Total previsto · 7 días'=>array_sum($futuro),'Media diaria prevista'=>count($futuro)?array_sum($futuro)/count($futuro):0,'Días previstos'=>count($futuro)];
        $r['notas'][]='El histórico termina en el corte del modelo. Incluye días sin actividad. Las líneas futuras son estimaciones.';
        $r['notas'][]=$vista==='ventas'?'Importes confirmados, incluyen envío; no acreditan pagos recibidos.':'Volumen de pedidos no cancelados; incluye pendientes.';
        return $r;
    }
    if($id===null&&$r['opciones'])$id=$r['opciones'][0];$r['id']=$id;
    if($id===null){$r['detalle']='No hay registros individuales con datos suficientes.';return $r;}
    $respuesta=consultar_prediccion_individual($db,$tipo,$id,$directorio);$r['detalle']=$respuesta['texto'];$p=$modelo['predicciones'][(string)$id]??null;
    if(!$p)return $r;
    if($vista==='inventario'){
        $q=$db->prepare('SELECT stock,estado FROM productos WHERE id_producto=?');$q->execute([$id]);$actual=$q->fetch(PDO::FETCH_ASSOC);
        if(!$actual||(int)$actual['estado']!==1||(int)$actual['stock']!==(int)$p['stock']){$r['notas'][]='No se dibuja stock proyectado porque el estado o las existencias cambiaron. Actualiza el entrenamiento.';return $r;}
        $r['tarjetas']=['Stock utilizado'=>$p['stock'],'Demanda prevista · 7 días'=>$p['unidades_7d'],'Agotamiento central'=>$p['agotado']?'Ya agotado':($p['fecha_agotamiento']??'No previsto en 30 días')];
        if(isset($p['serie'])){
            $serie=$p['serie'];$labels=array_merge(['Stock inicial'],$serie['fechas']);$series=[];
            foreach(['stock_central'=>'Stock central','stock_rapido'=>'Escenario rápido','stock_lento'=>'Escenario lento'] as $clave=>$nombre)$series[]=['nombre'=>$nombre,'valores'=>array_merge([$p['stock']],$serie[$clave]),'discontinua'=>$clave!=='stock_central'];
            $r['graficos'][]=['titulo'=>'Stock proyectado sin reposición · producto #'.$id,'tipo'=>'linea','unidad'=>'unidades','labels'=>$labels,'series'=>$series,'marca'=>$p['fecha_agotamiento'],'marca_texto'=>$p['agotado']?'Ya agotado':'Agotamiento central'];
            $r['graficos'][]=['titulo'=>'Demanda diaria prevista','tipo'=>'barras','unidad'=>'unidades','labels'=>$serie['fechas'],'series'=>[['nombre'=>'Unidades estimadas','valores'=>$serie['demanda']]]];
        }else $r['notas'][]='Este informe antiguo no contiene la serie diaria. Entrena y actualiza para dibujarla; no se reconstruye repartiendo una media.';
        $r['notas'][]='Los escenarios usan el error de prueba. No son intervalos de confianza ni fechas garantizadas. No hay historial de stock real para dibujar una curva pasada.';
    }else{
        $q=$db->prepare('SELECT estado FROM pedidos WHERE id_pedido=?');$q->execute([$id]);if($q->fetchColumn()!=='Pendiente'){$r['notas'][]='El pedido ya no está Pendiente. Su puntuación del informe es histórica; no se dibuja como riesgo actual.';return $r;}
        $r['tarjetas']=['Puntuación de cancelación (%)'=>$p['probabilidad_cancelacion'],'Pedido consultado'=>'#'.$id];
        $r['graficos'][]=['titulo'=>'Puntuación estimada · pedido #'.$id,'tipo'=>'indicador','unidad'=>'porcentaje','valor'=>$p['probabilidad_cancelacion']];
        $pendientes=array_flip($db->query("SELECT id_pedido FROM pedidos WHERE estado='Pendiente'")->fetchAll(PDO::FETCH_COLUMN));$lista=array_filter($modelo['predicciones'],static fn($x)=>isset($pendientes[$x['id_pedido']]));usort($lista,static fn($a,$b)=>$b['probabilidad_cancelacion']<=>$a['probabilidad_cancelacion']);$lista=array_slice($lista,0,10);
        $r['graficos'][]=['titulo'=>'Diez pedidos pendientes con mayor puntuación','tipo'=>'barras','unidad'=>'porcentaje','labels'=>array_map(static fn($x)=>'#'.$x['id_pedido'],$lista),'series'=>[['nombre'=>'Riesgo estimado (%)','valores'=>array_column($lista,'probabilidad_cancelacion')]]];
        $r['notas'][]='Puntuaciones del informe, no decisiones automáticas. No se cancelan pedidos. La fecha del entrenamiento determina su vigencia.';
    }
    return $r;
}

function actividad_actual_predicciones(PDO $db,string $vista): array {
    $hoy=new DateTimeImmutable('today');$inicio=$hoy->modify('-6 days');$fin=$hoy->modify('+1 day');
    $q=$db->prepare("SELECT DATE(fecha_pedido) fecha,COUNT(*) pedidos,
        COALESCE(SUM(CASE WHEN estado IN ('Confirmado','En preparación','Enviado','Entregado') THEN total ELSE 0 END),0) ventas,
        COALESCE(SUM(CASE WHEN estado='Pendiente' THEN total ELSE 0 END),0) importe_pendiente,
        SUM(CASE WHEN estado='Pendiente' THEN 1 ELSE 0 END) pendientes,
        SUM(CASE WHEN estado='Cancelado' THEN 1 ELSE 0 END) cancelados
        FROM pedidos WHERE fecha_pedido>=? AND fecha_pedido<? GROUP BY DATE(fecha_pedido) ORDER BY fecha");
    $q->execute([$inicio->format('Y-m-d'),$fin->format('Y-m-d')]);$mapa=array_column($q->fetchAll(PDO::FETCH_ASSOC),null,'fecha');
    $labels=[];$pedidos=[];$ventas=[];$pendientes=[];
    for($i=0;$i<7;$i++){$dia=$inicio->modify('+'.$i.' days')->format('Y-m-d');$labels[]=$dia;$pedidos[]=(int)($mapa[$dia]['pedidos']??0);$ventas[]=(float)($mapa[$dia]['ventas']??0);$pendientes[]=(float)($mapa[$dia]['importe_pendiente']??0);}
    $dia=$mapa[$hoy->format('Y-m-d')]??[];
    $tarjetas=[['titulo'=>'Pedidos creados hoy','valor'=>(int)($dia['pedidos']??0),'unidad'=>'pedidos'],
        ['titulo'=>'Ventas confirmadas hoy','valor'=>(float)($dia['ventas']??0),'unidad'=>'PEN'],
        ['titulo'=>'Importe pendiente de hoy','valor'=>(float)($dia['importe_pendiente']??0),'unidad'=>'PEN'],
        ['titulo'=>'Pedidos pendientes de hoy','valor'=>(int)($dia['pendientes']??0),'unidad'=>'pedidos']];
    $q=$db->query('SELECT id_pedido,fecha_pedido,estado,total FROM pedidos ORDER BY fecha_pedido DESC,id_pedido DESC LIMIT 10');
    return ['leido'=>date('c'),'tarjetas'=>$tarjetas,'recientes'=>$q->fetchAll(PDO::FETCH_ASSOC),
        'grafico'=>['titulo'=>$vista==='ventas'?'Importes observados · últimos 7 días, incluido hoy':'Pedidos creados · últimos 7 días, incluido hoy','tipo'=>'barras','unidad'=>$vista==='ventas'?'PEN':'pedidos','labels'=>$labels,
            'series'=>$vista==='ventas'?[['nombre'=>'Confirmados','valores'=>$ventas],['nombre'=>'Pendientes','valores'=>$pendientes]]:[['nombre'=>'Pedidos creados','valores'=>$pedidos]]],
        'nota'=>'Hoy es un día en curso. Los pedidos creados incluyen cancelados; las ventas confirmadas excluyen pendientes y cancelados, incluyen envío y no acreditan cobro. Los importes corresponden a la fecha de creación del pedido y su estado actual.'];
}

function dashboard_recompra_cliente(PDO $db,array $r,?array $modelo,?int $id,?string $directorio): array {
    $r['opciones']=array_map('intval',$db->query('SELECT id_cliente FROM clientes ORDER BY id_cliente')->fetchAll(PDO::FETCH_COLUMN));
    $id=$id??($r['opciones'][0]??null);$r['id']=$id;
    if($id===null||!in_array($id,$r['opciones'],true)){$r['estado']='sin_registro';$r['detalle']='No existe ese cliente o todavía no hay clientes registrados.';return $r;}
    $r['detalle']=consultar_prediccion_individual($db,'recompra',$id,$directorio)['texto'];
    $p=($modelo['estado']??null)==='listo'?($modelo['predicciones'][(string)$id]??null):null;
    $publicable=$p!==null&&!($p['evidencia_limitada']??false);
    $corte=new DateTimeImmutable($r['corte']??'yesterday');$inicio=$corte->modify('first day of this month')->modify('-5 months');
    $q=$db->prepare("SELECT DATE(fecha_pedido) fecha,COUNT(*) valor FROM pedidos WHERE id_cliente=? AND estado IN ('Confirmado','En preparación','Enviado','Entregado') AND fecha_pedido>=? AND fecha_pedido<? GROUP BY DATE(fecha_pedido) ORDER BY fecha");
    $q->execute([$id,$inicio->format('Y-m-d'),$corte->modify('+1 day')->format('Y-m-d')]);$filas=$q->fetchAll(PDO::FETCH_ASSOC);
    $meses=[];for($i=0;$i<6;$i++)$meses[$inicio->modify('+'.$i.' months')->format('Y-m')]=0;
    foreach($filas as $fila)$meses[substr($fila['fecha'],0,7)]+=(int)$fila['valor'];
    $r['tarjetas']=['Compras confirmadas · 6 meses'=>array_sum($meses),'Meses con compras'=>count(array_filter($meses)),'Evaluación individual'=>$publicable?'Predicción disponible':'Evidencia limitada'];
    if($publicable){
        $r['tarjetas']=['Recompra en 30 días (%)'=>$p['probabilidad_30d'],'Compras utilizadas'=>$p['compras_historial'],'Recencia (días)'=>$p['recencia_dias']];
        $r['graficos'][]=['titulo'=>'Probabilidad estimada de recompra · cliente #'.$id,'tipo'=>'indicador','valor'=>$p['probabilidad_30d'],'unidad'=>'porcentaje'];
        if(isset($p['casos_calibracion_rango']))$r['notas'][]='Calibración temporal: '.$p['recompras_calibracion_rango'].' recompras de '.$p['casos_calibracion_rango'].' casos en el rango '.$p['rango_calibracion'][0].'–'.$p['rango_calibracion'][1].' %. No garantiza el resultado individual.';
    }else{$r['estado']='evidencia_limitada';$r['notas'][]=$r['detalle'];}
    $r['graficos'][]=['titulo'=>'Actividad mensual · cliente #'.$id,'tipo'=>'barras','compacto'=>true,'unidad'=>'compras','labels'=>array_keys($meses),'series'=>[['nombre'=>'Compras confirmadas reales','valores'=>array_values($meses)]]];
    $r['notas'][]='Actividad real de seis meses hasta '.$corte->format('Y-m-d').'. El último mes es parcial. Los meses sin compras se muestran en cero; este gráfico no es un pronóstico.';
    return $r;
}
