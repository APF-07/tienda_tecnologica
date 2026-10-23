<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/predicciones_individuales.php';
$db=new PDO('sqlite::memory:');$db->exec('CREATE TABLE clientes(id_cliente INT); CREATE TABLE productos(id_producto INT,stock INT,estado INT); CREATE TABLE pedidos(id_pedido INT)');$db->exec('INSERT INTO clientes VALUES(1); INSERT INTO productos VALUES(1,10,1)');
$dir=sys_get_temp_dir().'/predicciones-'.bin2hex(random_bytes(5));mkdir($dir);$n=0;
$check=function($ok,$t)use(&$n){if(!$ok)throw new RuntimeException($t);$n++;echo "OK $t\n";};
try{
 $check(tipo_prediccion_individual('¿Qué probabilidad tiene el cliente #1 de volver a comprar?')==='recompra','Reconoce recompra individual');
 $check(tipo_prediccion_individual('¿Cuándo se agotará el producto #1?')==='demanda_producto','Reconoce agotamiento individual');
 $check(tipo_prediccion_individual('¿Qué productos están agotados?')===null,'No confunde inventario actual con predicción');
 $r=consultar_prediccion_individual($db,'recompra',1,$dir);$check(str_contains($r['texto'],'Todavía no hay'),'Informe ausente no inventa probabilidades');
 $informe=['schema_version'=>2,'generado'=>date('Y-m-d H:i:s'),'fecha_corte'=>date('Y-m-d',strtotime('-1 day')),'modelos'=>[['tipo_modelo'=>'recompra','estado'=>'listo','metricas'=>['Brier de prueba'=>.1,'Brier referencia constante'=>.2],'predicciones'=>['1'=>['probabilidad_30d'=>65.2,'desde'=>'2026-10-16','hasta'=>'2026-11-14','compras_historial'=>4,'recencia_dias'=>5]]]]];
 file_put_contents($dir.'/cache_todos_clientes.json',json_encode($informe));$r=consultar_prediccion_individual($db,'recompra',1,$dir);$check(str_contains($r['texto'],'65.2 %')&&$r['privado'],'Porcentaje viene del informe y permanece privado');
 $informe['modelos'][0]['predicciones']['1']['evidencia_limitada']=true;
 $informe['modelos'][0]['predicciones']['1']['probabilidad_30d']=95;
 file_put_contents($dir.'/cache_todos_clientes.json',json_encode($informe));$r=consultar_prediccion_individual($db,'recompra',1,$dir);
 $check(str_contains($r['texto'],'evidencia limitada')&&!str_contains($r['texto'],'95'),'Asistente no publica 95 por ciento con evidencia limitada');
 $r=consultar_prediccion_individual($db,'recompra',999,$dir);$check(str_contains($r['texto'],'No existe'),'Cliente inexistente no recibe predicción');
 $r=responder_prediccion_individual($db,'¿Cuándo se agotará este producto?',$dir);$check(str_contains($r['texto'],'identificador'),'Sin identificador solicita aclaración');
 $informe['modelos']=[['tipo_modelo'=>'demanda_producto','estado'=>'listo','predicciones'=>['1'=>['stock'=>11,'unidades_7d'=>3,'unidades_30d'=>12,'modelo'=>'Prueba','agotado'=>false,'fecha_agotamiento'=>'2026-11-01','fecha_escenario_rapido'=>'2026-10-25','fecha_escenario_lento'=>null,'horizonte_hasta'=>'2026-11-14','mae_diario_prueba'=>1]]]];
 file_put_contents($dir.'/cache_todos_productos.json',json_encode($informe));$r=consultar_prediccion_individual($db,'demanda_producto',1,$dir);
 $check(str_contains($r['texto'],'stock cambió')&&!str_contains($r['texto'],'Fecha central'),'Stock modificado no presenta fecha operativa antigua');
 $db->exec('UPDATE productos SET stock=11');$r=consultar_prediccion_individual($db,'demanda_producto',1,$dir);
 $check(str_contains($r['texto'],'2026-11-01')&&str_contains($r['texto'],'no habrá reposición'),'Fecha se acompaña del supuesto y escenarios');
 $informe['fecha_corte']='2020-01-01';file_put_contents($dir.'/cache_todos_productos.json',json_encode($informe));$r=consultar_prediccion_individual($db,'demanda_producto',1,$dir);
 $check(str_contains($r['texto'],'Informe desactualizado'),'Informa cuando el pronóstico ya no es vigente');
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
echo "$n pruebas correctas. Sin datos reales ni APIs.\n";
