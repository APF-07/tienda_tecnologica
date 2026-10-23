<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/multiagentes.php';
require __DIR__.'/../includes/asistente.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);preparar_registro_agentes($db);
$db->exec('CREATE TABLE productos(id_producto INT,codigo TEXT,nombre TEXT,stock INT,estado INT); CREATE TABLE pedidos(id_pedido INT,estado TEXT,fecha_pedido TEXT,total DOUBLE); CREATE TABLE detalle_pedidos(id_pedido INT,id_producto INT,cantidad INT)');
$db->exec("INSERT INTO productos VALUES(1,'TEST','Equipo',15,1); INSERT INTO pedidos VALUES(1,'Pendiente','2026-10-16 08:00:00',100),(2,'Entregado','2026-10-16 07:00:00',200)");
$n=0;$check=function($ok,$texto)use(&$n){if(!$ok)throw new RuntimeException($texto);$n++;echo "OK $texto\n";};
$r=(new CoordinadorTienda())->ejecutar($db,[],'2026-10-16 09:00:00');
$check(count($r['traza'])===6,'Seis agentes y auditor ejecutados con traza');
$check($r['seguimiento']['total']===1,'Seguimiento excluye pedidos atendidos');
$check(count($r['analisis']['recomendaciones'])>=1,'Análisis incorpora hallazgos de seguimiento y proyección');
registrar_transicion_stock($db,['id_producto'=>1,'codigo'=>'TEST','nombre'=>'Equipo','stock'=>15,'estado'=>1]);
$db->beginTransaction();$db->exec('UPDATE productos SET stock=3 WHERE id_producto=1');registrar_transicion_stock($db,['id_producto'=>1,'codigo'=>'TEST','nombre'=>'Equipo','stock'=>3,'estado'=>1]);$db->rollBack();
$check((int)$db->query('SELECT COUNT(*) FROM alertas_operativas')->fetchColumn()===0,'Rollback no deja alertas ni transición');
$db->exec('UPDATE productos SET stock=3 WHERE id_producto=1');$p=['id_producto'=>1,'codigo'=>'TEST','nombre'=>'Equipo','stock'=>3,'estado'=>1];registrar_transicion_stock($db,$p);registrar_transicion_stock($db,$p);
$check((int)$db->query('SELECT COUNT(*) FROM alertas_operativas')->fetchColumn()===1,'Venta y revisión usan el mismo registro sin duplicar stock');
$db->exec('UPDATE productos SET stock=15 WHERE id_producto=1');$p['stock']=15;registrar_transicion_stock($db,$p);registrar_transicion_stock($db,$p);
$check((int)$db->query("SELECT COUNT(*) FROM alertas_operativas WHERE tipo='stock_recuperado'")->fetchColumn()===1,'Recuperación única guardada en MySQL');
escribir_estado_agentes($db,'ultima',$r);$estado=leer_estado_agentes($db,'ultima');
$check($estado['traza']===$r['traza'],'Estado se conserva íntegro en base de datos');
$respuesta=responder_asistente($db,'¿Qué debería atender primero hoy?');
$check(str_contains($respuesta['texto'],'Atender 1 pedidos')&&$respuesta['fuente']==='Resultados del coordinador en MySQL','Asistente explica prioridades con fuente real');
$falta=resumen_proyeccion_agente(__DIR__.'/carpeta-que-no-existe','2026-10-16');
$check($falta['ventas']['estado']==='Sin informe'&&$falta['ventas']['valores']===[],'Sin ML no se inventan pronósticos');
$tmp=sys_get_temp_dir().'/techstore-ml-'.bin2hex(random_bytes(5));mkdir($tmp);
try{
 $informe=['schema_version'=>2,'generado'=>'2026-10-16 08:00:00','fecha_corte'=>'2026-10-15','modelos'=>[['metricas'=>['Modelo recomendado'=>'Referencia semanal'],'graficos'=>[['titulo'=>'Próximos 7 días','datos'=>['labels'=>['2026-10-17'],'valores'=>[7]]]]]]];
 file_put_contents($tmp.'/cache_todos_pedidos.json',json_encode($informe));
 $check(resumen_proyeccion_agente($tmp,'2026-10-16')['pedidos']['estado']==='Vigente'&&resumen_proyeccion_agente($tmp,'2026-10-20')['pedidos']['estado']==='Desactualizado','Vigencia cambia con la fecha, no con cifras inventadas');
 evaluar_pronosticos_guardados($db,$tmp,new DateTimeImmutable('2026-10-16 09:00:00'));evaluar_pronosticos_guardados($db,$tmp,new DateTimeImmutable('2026-10-16 09:00:00'));
 $check((int)$db->query('SELECT COUNT(*) FROM ml_pronosticos')->fetchColumn()===1,'Archiva pronósticos futuros sin duplicar');
 $db->exec("INSERT INTO pedidos VALUES(3,'Entregado','2026-10-17 10:00:00',50)");$evaluado=evaluar_pronosticos_guardados($db,$tmp,new DateTimeImmutable('2026-10-18'));
 $check(count($evaluado)===1&&(float)$evaluado[0]['mae']===6.0,'Evalúa posteriormente contra datos reales y referencia semanal');
}finally{unlink($tmp.'/cache_todos_pedidos.json');rmdir($tmp);}
echo "$n pruebas correctas. SQLite y archivos de prueba; sin mensajes externos.\n";
