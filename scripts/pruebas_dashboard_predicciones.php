<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/dashboard_predicciones.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE pedidos(id_pedido INT,id_cliente INT,fecha_pedido TEXT,estado TEXT,total REAL); CREATE TABLE clientes(id_cliente INT); CREATE TABLE productos(id_producto INT,stock INT,estado INT)');
$db->exec("INSERT INTO clientes VALUES(1); INSERT INTO productos VALUES(1,10,1); INSERT INTO pedidos VALUES(1,1,'2026-10-15 10:00:00','Pendiente',100),(2,1,'2026-10-15 11:00:00','Entregado',200)");
$dir=sys_get_temp_dir().'/dash-pred-'.bin2hex(random_bytes(5));mkdir($dir);$n=0;$check=function($ok,$t)use(&$n){if(!$ok)throw new RuntimeException($t);$n++;echo "OK $t\n";};
$base=['schema_version'=>2,'generado'=>'2026-10-16 08:00:00','fecha_corte'=>'2026-10-15'];
try{
 $r=datos_dashboard_predicciones($db,'ventas',null,$dir);$check(!$r['graficos']&&$r['estado']==='sin_informe','Sin informe no dibuja curvas');
 $modelo=['estado'=>'listo','metricas'=>[],'graficos'=>[['titulo'=>'Próximos 7 días','datos'=>['labels'=>['2026-10-16','2026-10-17'],'valores'=>[3,4]]]]];
 file_put_contents($dir.'/cache_todos_ventas.json',json_encode($base+['modelos'=>[$modelo]]));$r=datos_dashboard_predicciones($db,'ventas',null,$dir);
 $check($r['tarjetas']['Total previsto · 7 días']===7,'Totales salen del informe guardado');
 $check($r['graficos'][0]['series'][0]['valores'][29]===200.0&&$r['graficos'][0]['series'][0]['valores'][30]===null,'Histórico confirmado separado del futuro con huecos explícitos');
 $recompra=['tipo_modelo'=>'recompra','estado'=>'listo','metricas'=>[],'predicciones'=>['1'=>['probabilidad_30d'=>65,'compras_historial'=>2,'recencia_dias'=>1,'desde'=>'2026-10-16','hasta'=>'2026-11-14']]];
 file_put_contents($dir.'/cache_todos_clientes.json',json_encode($base+['modelos'=>[$recompra]]));$r=datos_dashboard_predicciones($db,'recompra',1,$dir);
 $check($r['graficos'][0]['valor']===65,'Indicador utiliza probabilidad evaluada');
 $recompra['predicciones']['1']['evidencia_limitada']=true;
 file_put_contents($dir.'/cache_todos_clientes.json',json_encode($base+['modelos'=>[$recompra]]));$r=datos_dashboard_predicciones($db,'recompra',1,$dir);
 $check(count($r['graficos'])===1&&!isset($r['tarjetas']['Recompra en 30 días (%)'])&&str_contains($r['detalle'],'No se publica'),'Poca evidencia conserva actividad real sin porcentaje engañoso');
 $check($r['graficos'][0]['series'][0]['valores']===[0,0,0,0,0,1],'Actividad mensual incluye ceros y excluye pedidos pendientes');
 $db->exec('INSERT INTO clientes VALUES(2)');$r=datos_dashboard_predicciones($db,'recompra',2,$dir);
 $check(in_array(2,$r['opciones'],true)&&$r['graficos'][0]['series'][0]['valores']===[0,0,0,0,0,0],'Cliente sin modelo ni compras tiene su propio gráfico en cero');
 $r=datos_dashboard_predicciones($db,'recompra',999,$dir);$check(!$r['graficos']&&$r['estado']==='sin_registro','No fabrica dashboard para cliente inexistente');
 $p=['stock'=>10,'unidades_7d'=>3,'unidades_30d'=>12,'fecha_agotamiento'=>'2026-11-01','fecha_escenario_rapido'=>'2026-10-25','fecha_escenario_lento'=>null,'horizonte_hasta'=>'2026-11-14','mae_diario_prueba'=>1,'agotado'=>false,'modelo'=>'Prueba'];
 file_put_contents($dir.'/cache_todos_productos.json',json_encode($base+['modelos'=>[['tipo_modelo'=>'demanda_producto','estado'=>'listo','predicciones'=>['1'=>$p]]]]));$r=datos_dashboard_predicciones($db,'inventario',1,$dir);
 $check(!$r['graficos']&&str_contains(implode(' ',$r['notas']),'no contiene'),'No inventa serie diaria desde un total agregado');
 $p['serie']=['fechas'=>['2026-10-16'],'demanda'=>[2],'stock_central'=>[8],'stock_rapido'=>[7],'stock_lento'=>[9]];
 file_put_contents($dir.'/cache_todos_productos.json',json_encode($base+['modelos'=>[['tipo_modelo'=>'demanda_producto','estado'=>'listo','predicciones'=>['1'=>$p]]]]));$r=datos_dashboard_predicciones($db,'inventario',1,$dir);
 $check($r['graficos'][0]['series'][0]['valores']===[10,8]&&count($r['graficos'])===2,'Curvas preservan stock inicial y escenarios del modelo');
 $db->exec('UPDATE productos SET stock=20');$r=datos_dashboard_predicciones($db,'inventario',1,$dir);$check(!$r['graficos'],'Stock cambiado impide dibujar una proyección operativa antigua');
 file_put_contents($dir.'/cache_todos_pedidos.json',json_encode($base+['modelos'=>[['tipo_modelo'=>'cancelacion','estado'=>'listo','predicciones'=>['1'=>['id_pedido'=>1,'probabilidad_cancelacion'=>30],'2'=>['id_pedido'=>2,'probabilidad_cancelacion'=>80]]]]]));$r=datos_dashboard_predicciones($db,'cancelacion',1,$dir);
 $check($r['graficos'][1]['labels']===['#1'],'Barras excluyen pedidos ya atendidos');
 $r=datos_dashboard_predicciones($db,'cancelacion',2,$dir);$check(!$r['graficos'],'Pedido atendido no se dibuja como riesgo actual');
 $antes=datos_dashboard_predicciones($db,'ventas',null,$dir);$hoy=date('Y-m-d').' 12:00:00';
 $q=$db->prepare('INSERT INTO pedidos VALUES(3,1,?,?,123.45)');$q->execute([$hoy,'Pendiente']);
 $nuevo=datos_dashboard_predicciones($db,'ventas',null,$dir);$check($nuevo['actividad']['tarjetas'][0]['valor']===$antes['actividad']['tarjetas'][0]['valor']+1,'Compra nueva aparece hoy sin reentrenar ni depender del corte ML');
 $check($nuevo['actividad']['tarjetas'][1]['valor']===$antes['actividad']['tarjetas'][1]['valor']&&$nuevo['actividad']['tarjetas'][2]['valor']===123.45,'Pendiente se muestra aparte y no se presenta como venta confirmada');
 $check($nuevo['tarjetas']['Total previsto · 7 días']===$antes['tarjetas']['Total previsto · 7 días'],'Actividad real no modifica artificialmente el pronóstico guardado');
 $db->exec("UPDATE pedidos SET estado='Confirmado' WHERE id_pedido=3");$confirmado=datos_dashboard_predicciones($db,'ventas',null,$dir);
 $check($confirmado['actividad']['tarjetas'][1]['valor']===123.45&&$confirmado['actividad']['tarjetas'][2]['valor']===0.0,'Confirmación mueve el importe a ventas en la siguiente lectura');
 $db->exec("UPDATE pedidos SET estado='Cancelado' WHERE id_pedido=3");$cancelado=datos_dashboard_predicciones($db,'ventas',null,$dir);
 $check($cancelado['actividad']['tarjetas'][1]['valor']===0.0,'Cancelación retira el importe de ventas confirmadas');
 $sinModelo=datos_dashboard_predicciones($db,'pedidos',null,$dir);$check($sinModelo['actividad']['tarjetas'][0]['valor']===1&&count($sinModelo['actividad']['recientes'])===3,'Pedidos de hoy y anteriores se muestran aunque falte el pronóstico');
 $check(array_column($sinModelo['actividad']['recientes'],'id_pedido')===[3,2,1],'Historial incluye fechas anteriores en orden descendente sin sumar esos pedidos al contador de hoy');
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
echo "$n pruebas correctas, SQLite y archivos temporales. Sin APIs externas.\n";
