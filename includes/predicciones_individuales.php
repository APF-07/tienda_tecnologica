<?php
require_once __DIR__.'/consultas_asistente.php';
function tipo_prediccion_individual(string $pregunta): ?string {
    $t=normalizar_consulta_admin($pregunta);
    if(preg_match('/\b(borra|elimina|modifica|actualiza|cambia|entrena|cancela)\b/u',$t))return null;
    if(preg_match('/cliente|comprador/u',$t)&&preg_match('/recompra|volver.*compr|vuelva.*compr|compr.*(?:nuevo|otra vez)|probabilidad.*compr/u',$t))return 'recompra';
    if(preg_match('/producto|articulo/u',$t)&&preg_match('/demanda|predec|predic|vender|vendera|cuando.*agot|fecha.*agot|se agot|qued.*sin stock/u',$t))return 'demanda_producto';
    if(preg_match('/pedido\s*#?\s*\d+/u',$t)&&preg_match('/riesgo|cancelacion|probabilidad/u',$t))return 'cancelacion';
    return null;
}
function consultar_prediccion_individual(PDO $db,string $tipo,int $id,?string $directorio=null): array {
    if(!in_array($tipo,['recompra','demanda_producto','cancelacion'],true)||$id<1)throw new RuntimeException('Selecciona una predicción y un identificador válido.');
    $area=['recompra'=>'clientes','demanda_producto'=>'productos','cancelacion'=>'pedidos'][$tipo];
    $tabla=['recompra'=>'clientes','demanda_producto'=>'productos','cancelacion'=>'pedidos'][$tipo];
    $campo=['recompra'=>'id_cliente','demanda_producto'=>'id_producto','cancelacion'=>'id_pedido'][$tipo];
    $q=$db->prepare('SELECT '.$campo.($tipo==='demanda_producto'?',stock,estado':'').' FROM '.$tabla.' WHERE '.$campo.'=?');$q->execute([$id]);$entidad=$q->fetch(PDO::FETCH_ASSOC);
    $r=['texto'=>'','enlaces'=>[['texto'=>'Ver modelos y evaluación','url'=>ruta('admin/ml/'.$area.'_ia.php')]],'fuente'=>'Informe ML individual guardado','fecha'=>date('d/m/Y H:i'),'modo'=>'local','privado'=>$tipo!=='demanda_producto'];
    if(!$entidad){$r['texto']='No existe ese '.['recompra'=>'cliente','demanda_producto'=>'producto','cancelacion'=>'pedido'][$tipo].' #'.$id.'.';return $r;}
    $archivo=($directorio??__DIR__.'/../ml').'/cache_todos_'.$area.'.json';$informe=is_file($archivo)?json_decode((string)file_get_contents($archivo),true):null;$modelo=null;
    foreach($informe['modelos']??[] as $m)if(($m['tipo_modelo']??null)===$tipo)$modelo=$m;
    if(!$modelo||($informe['schema_version']??0)!==2){$r['texto']='Todavía no hay un informe para esta predicción. Entrena y actualiza los modelos desde Analítica y proyección.';return $r;}
    $r['texto']='Corte: '.($informe['fecha_corte']??'sin fecha').' · Entrenado: '.($informe['generado']??'sin fecha').".\n";
    if($modelo['estado']!=='listo'){$r['texto'].='Historial insuficiente para entrenar y evaluar esta predicción. No hay un porcentaje o fecha defendible para mostrar.';return $r;}
    $p=$modelo['predicciones'][(string)$id]??null;
    if(!$p){$r['texto'].=$tipo==='recompra'?'Este cliente no tiene las dos compras confirmadas mínimas para esta predicción.':($tipo==='cancelacion'?'Este pedido no fue puntuado como Pendiente en el entrenamiento.':'Este producto no tiene suficiente historial de ventas para una predicción individual evaluada.');return $r;}
    if(($informe['fecha_corte']??'')<(new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d'))$r['texto'].="Informe desactualizado: los resultados siguientes son históricos; entrena de nuevo antes de usarlos.\n";
    if($tipo==='recompra'){
        if($p['evidencia_limitada']??false){$r['texto'].='Cliente #'.$id.': evidencia limitada. Compras confirmadas utilizadas: '.$p['compras_historial'].'. Recencia: '.$p['recencia_dias']." días.\nNo se publica una probabilidad individual: se exigen al menos cinco compras, 30 días de antigüedad y 30 casos en el rango de calibración. Esta regla conservadora no garantiza precisión individual.";return $r;}
        if(isset($p['casos_calibracion_rango']))$r['texto'].='Calibración del rango '.$p['rango_calibracion'][0].'–'.$p['rango_calibracion'][1].'%: '.$p['recompras_calibracion_rango'].' recompras entre '.$p['casos_calibracion_rango']." casos históricos. Es evidencia de un grupo, no certeza individual.\n";
        $porcentaje=$p['probabilidad_30d']>=99.95?'>99.9':($p['probabilidad_30d']<0.05?'<0.1':round($p['probabilidad_30d'],1));
        $r['texto'].='Cliente #'.$id.': probabilidad estimada de volver a comprar en los próximos 30 días del informe: '.$porcentaje." %.\nPeriodo: ".$p['desde'].' a '.$p['hasta'].'. Compras confirmadas utilizadas: '.$p['compras_historial'].'. Recencia: '.$p['recencia_dias']." días.\nNo garantiza una nueva compra. Brier de prueba: ".($modelo['metricas']['Brier de prueba']??'no disponible').'; referencia constante: '.($modelo['metricas']['Brier referencia constante']??'no disponible').'. Menor Brier es mejor.';
    }elseif($tipo==='demanda_producto'){
        if((int)$entidad['estado']!==1){$r['texto'].='El producto está inactivo; no se presenta una fecha de agotamiento operativa.';return $r;}
        $r['texto'].='Producto #'.$id.': demanda estimada de '.$p['unidades_7d'].' unidades en siete días y '.$p['unidades_30d']." en 30 días.\nStock utilizado: ".$p['stock'].' unidades. Método seleccionado: '.$p['modelo'].'.';
        if((int)$entidad['stock']!==(int)$p['stock'])$r['texto'].="\nEl stock cambió a ".$entidad['stock'].' desde el entrenamiento. Actualiza el modelo para obtener una fecha con ese stock.';
        elseif($p['agotado'])$r['texto'].="\nActualmente ya figura agotado en el informe; no es un agotamiento futuro.";
        else{$r['texto'].="\n".($p['fecha_agotamiento']?'Fecha central estimada de agotamiento: '.$p['fecha_agotamiento'].'.':'No se estima agotamiento dentro de los 30 días hasta '.$p['horizonte_hasta'].'.');$r['texto'].="\nEscenario rápido: ".($p['fecha_escenario_rapido']??'no alcanza el stock en 30 días').'; lento: '.($p['fecha_escenario_lento']??'no alcanza el stock en 30 días').'.';}
        $r['texto'].="\nSupone que no habrá reposición. Los escenarios usan el error de prueba y no son un intervalo de confianza garantizado. MAE diario de prueba del producto: ".$p['mae_diario_prueba'].' unidades.';
    }else $r['texto'].='Pedido #'.$id.': puntuación estimada de cancelación '.$p['probabilidad_cancelacion']." %. No garantiza una cancelación ni cambia su estado. Corresponde al pedido Pendiente en la fecha del informe.";
    return $r;
}
function responder_prediccion_individual(PDO $db,string $pregunta,?string $directorio=null): ?array {
    $tipo=tipo_prediccion_individual($pregunta);if(!$tipo)return null;$t=normalizar_consulta_admin($pregunta);
    if(!preg_match('/(?:cliente|comprador|producto|articulo|pedido)\s*(?:#|n[.º°o]*\s*)?\s*(\d+)/u',$t,$m))return ['texto'=>'Indica el identificador para consultar el resultado real. Ejemplos: «¿Qué probabilidad tiene el cliente #515 de volver a comprar?» o «¿Cuándo se agotará el producto #89?»','enlaces'=>[],'fuente'=>'Consulta de predicciones individuales','fecha'=>date('d/m/Y H:i'),'modo'=>'local'];
    return consultar_prediccion_individual($db,$tipo,(int)$m[1],$directorio);
}
