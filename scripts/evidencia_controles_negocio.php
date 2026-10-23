<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/conexion.php';
require __DIR__.'/../includes/controles_negocio.php';
$r=informe_controles_negocio();$tabla=[];
foreach(['productos','categorias','marcas','pedidos','detalle_pedidos','clientes'] as $nombre){$tabla[$nombre]=(int)$conexion->query('SELECT COUNT(*) FROM '.$nombre)->fetchColumn();}
$auditoria=['fecha'=>date(DATE_ATOM),'motor'=>$conexion->query('SELECT VERSION()')->fetchColumn(),'tablas'=>$tabla,
    'informe'=>$r?['generado'=>$r['generado'],'fecha_corte'=>$r['fecha_corte'],'datos_id'=>$r['datos_id'],'origen'=>$r['origen']]:null,
    'controles'=>[]];
$texto="# Validación de cinco controles ML\n\nGenerado: ".date(DATE_ATOM).". Métricas de la base académica local, no producción.\n\n";
$texto.="| Control de ML | Problema que resuelve | Datos utilizados | Algoritmo elegido | Métrica de evaluación | Resultado obtenido | Evidencia de validación | Estado de implementación |\n|---|---|---|---|---|---|---|---|\n";
foreach($r['controles']??[] as $key=>$c){
    $auditoria['controles'][$key]=array_intersect_key($c,array_flip(['estado','motivo','metricas','evidencia']));
    $celdas=[$c['nombre'],$c['problema'],$c['datos'],$c['algoritmo'],implode(', ',array_keys($c['metricas'])),json_encode($c['metricas'],JSON_UNESCAPED_UNICODE),json_encode($c['evidencia'],JSON_UNESCAPED_UNICODE),'Implementado; validación: '.$c['estado'].' · '.$c['motivo']];
    $texto.='| '.implode(' | ',array_map(fn($v)=>str_replace(["\n",'|'],[' ','/'],$v),$celdas))." |\n";
}
$destino=__DIR__.'/../docs/evidencias';if(!is_dir($destino))mkdir($destino,0775,true);
file_put_contents($destino.'/controles-negocio.json',json_encode($auditoria,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
file_put_contents($destino.'/controles-negocio.md',$texto);
echo json_encode($auditoria,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
