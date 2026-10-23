<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/centro_operaciones.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE productos(id_producto INT,nombre TEXT,stock INT,estado INT); CREATE TABLE pedidos(id_pedido INT,fecha_pedido TEXT,estado TEXT,total DOUBLE)');
$db->exec("INSERT INTO productos VALUES(1,'Agotado',0,1),(2,'Normal',20,1),(3,'Inactivo',0,0); INSERT INTO pedidos VALUES(1,'2026-10-19 08:00:00','Pendiente',100),(2,'2026-10-20 12:00:00','Entregado',300),(3,'2026-10-20 13:00:00','Cancelado',999)");
$dir=sys_get_temp_dir().'/operaciones-'.bin2hex(random_bytes(5));mkdir($dir);$n=0;
$check=function($ok,$texto)use(&$n){if(!$ok)throw new RuntimeException($texto);$n++;echo "OK $texto\n";};
$reloj=new DateTimeImmutable('2026-10-19 09:00:00');
try{
 sincronizar_acciones_dia($db,$dir,$reloj);sincronizar_acciones_dia($db,$dir,$reloj);
 $check((int)$db->query('SELECT COUNT(*) FROM acciones_dia')->fetchColumn()===4,'Una acción por día y entidad sin duplicación');
 $a=$db->query("SELECT * FROM acciones_dia WHERE tipo='inventario'")->fetch();$check((int)$a['entidad']===1&&(int)$a['prioridad']===0,'Agotados prioritarios y productos inactivos excluidos');
 gestionar_accion_dia($db,$a['clave'],'Atendida','Se solicitó reposición al proveedor',1);sincronizar_acciones_dia($db,$dir,$reloj);
 $check($db->query("SELECT estado FROM acciones_dia WHERE tipo='inventario'")->fetchColumn()==='Atendida','Revisar el panel no reabre acciones atendidas');
 gestionar_accion_dia($db,$a['clave'],'Atendida','Mismo seguimiento repetido',1);
 $check((int)$db->query('SELECT COUNT(*) FROM acciones_historial')->fetchColumn()===1,'Estado y motivo auditados sin duplicar envío de formulario');
 try{gestionar_accion_dia($db,$a['clave'],'Borrar','texto',1);$check(false,'Estado inválido');}catch(InvalidArgumentException $e){$check(true,'Rechaza estados no permitidos');}
 $db->exec("UPDATE pedidos SET estado='Confirmado' WHERE id_pedido=1");sincronizar_acciones_dia($db,$dir,$reloj);
 $check($db->query("SELECT estado FROM acciones_dia WHERE tipo='pedido'")->fetchColumn()==='Resuelta','Pedido atendido resuelve automáticamente la tarea pendiente');
 $db->exec('UPDATE productos SET stock=12 WHERE id_producto=1');sincronizar_acciones_dia($db,$dir,$reloj);
 $check((int)$db->query("SELECT vigente FROM acciones_dia WHERE tipo='inventario'")->fetchColumn()===0,'Reposición marca condición desaparecida y conserva seguimiento humano');
 $db->exec('UPDATE productos SET stock=0 WHERE id_producto=1');sincronizar_acciones_dia($db,$dir,new DateTimeImmutable('2026-10-20 09:00:00'));
 $check((int)$db->query("SELECT COUNT(*) FROM acciones_dia WHERE tipo='inventario'")->fetchColumn()===2,'Problema persistente genera tarea nueva al día siguiente');
 $report=['schema_version'=>2,'generado'=>'2026-10-19 08:30:00','fecha_corte'=>'2026-10-18','modelos'=>[['estado'=>'listo','graficos'=>[['titulo'=>'Próximos 7 días','datos'=>['labels'=>['2026-10-19','2026-10-20','2026-10-21'],'valores'=>[999,250,200]]]]]]];
 file_put_contents($dir.'/cache_todos_ventas.json',json_encode($report));evaluar_pronosticos_guardados($db,$dir,$reloj);evaluar_pronosticos_guardados($db,$dir,$reloj);
 $check((int)$db->query('SELECT COUNT(*) FROM ml_pronosticos')->fetchColumn()===2,'Archiva futuro una vez y nunca reconstruye el día iniciado');
 $c=comparacion_real($db,'ventas');$check($c['evaluados']===0&&$c['pendientes']===2&&$c['mae']===null,'No calcula error mientras falta el resultado real');
 evaluar_pronosticos_guardados($db,$dir,new DateTimeImmutable('2026-10-21 09:00:00'));$c=comparacion_real($db,'ventas');
 $check($c['evaluados']===1&&$c['mae']===50.0&&$c['mae_referencia']===300.0,'Contrasta con venta confirmada y excluye cancelación');
 $db->exec("UPDATE pedidos SET total=800 WHERE id_pedido=2");evaluar_pronosticos_guardados($db,$dir,new DateTimeImmutable('2026-10-21 09:00:00'));
 $check(comparacion_real($db,'ventas')['mae']===50.0,'No reescribe resultados evaluados tras modificaciones posteriores');
 $db->exec("INSERT INTO ml_pronosticos VALUES('otra','ventas','2026-10-19 09:00:00','2026-10-20',1,0,300,'2026-10-21 09:00:00'); INSERT INTO ml_pronosticos_auditoria VALUES('otra','2026-10-19 10:00:00','2026-10-18')");
 $check(comparacion_real($db,'ventas')['evaluados']===1&&comparacion_real($db,'ventas')['mae']===50.0,'Un día no se cuenta dos veces por varios entrenamientos');
 $db->exec("INSERT INTO ml_pronosticos VALUES('legado','ventas','2026-10-18','2026-10-19',1,0,10,'2026-10-20')");
 $check(comparacion_real($db,'ventas')['evaluados']===1,'Excluye registros antiguos sin trazabilidad de captura');
 $report['generado']='2026-10-22 10:00:00';file_put_contents($dir.'/cache_todos_ventas.json',json_encode($report));$antes=(int)$db->query('SELECT COUNT(*) FROM ml_pronosticos')->fetchColumn();evaluar_pronosticos_guardados($db,$dir,$reloj);
 $check((int)$db->query('SELECT COUNT(*) FROM ml_pronosticos')->fetchColumn()===$antes,'No archiva informes con fecha de generación futura');
}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
echo "$n pruebas correctas. SQLite temporal; sin Telegram, APIs ni datos del proyecto.\n";
