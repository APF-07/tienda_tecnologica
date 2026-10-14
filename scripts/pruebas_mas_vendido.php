<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/asistente.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE productos(id_producto INTEGER,codigo TEXT,nombre TEXT,precio REAL)');
$db->exec('CREATE TABLE pedidos(id_pedido INTEGER,estado TEXT,fecha_pedido TEXT)');
$db->exec('CREATE TABLE detalle_pedidos(id_pedido INTEGER,id_producto INTEGER,cantidad INTEGER,subtotal REAL)');
$db->exec("INSERT INTO productos VALUES(1,'LAP','Laptop',9999),(2,'MOU','Mouse',9999),(3,'CAN','Otro',9999)");
$db->exec("INSERT INTO pedidos VALUES(1,'Confirmado','2026-10-14 10:00:00'),(2,'Entregado','2026-10-14 10:01:00'),(3,'Entregado','2026-10-14 10:02:00'),(4,'Entregado','2026-10-14 10:03:00'),(5,'Cancelado','2026-10-14 10:04:00'),(6,'Pendiente','2026-10-14 10:05:00'),(7,'Entregado','2026-08-01 10:00:00')");
$db->exec('INSERT INTO detalle_pedidos VALUES(1,1,10,100),(2,2,1,10),(3,2,1,10),(4,2,1,10),(5,3,999,999),(6,3,999,999),(7,2,50,500)');
function comprobar_top($c,$m){if(!$c)throw new RuntimeException($m);echo "OK: $m\n";}
comprobar_top(intencion_asistente('El producto mas vendido?')==='producto_mas_vendido','La pregunta de la captura identifica producto, no resumen de ventas');
comprobar_top(intencion_asistente('Ventas de este mes')==='ventas_actuales','El resumen de ventas conserva su intención');
$hoy=new DateTimeImmutable('2026-10-14');$r=consultar_mas_vendido_admin($db,'Producto más vendido hoy',$hoy);
comprobar_top(count($r['productos'])===1&&$r['productos'][0]['id_producto']===1&&(int)$r['productos'][0]['unidades']===10,'Se cuentan unidades y se excluyen pendientes y cancelados');
comprobar_top((float)$r['productos'][0]['importe']===100.0,'Importe proviene del detalle histórico, no del precio actual');
$r=consultar_mas_vendido_admin($db,'El producto mas vendido?',$hoy);
comprobar_top($r['productos'][0]['id_producto']===2&&(int)$r['productos'][0]['unidades']===53,'Sin periodo se consulta todo el historial');
$db->exec("INSERT INTO pedidos VALUES(8,'Enviado','2026-10-14 11:00:00')");$db->exec('INSERT INTO detalle_pedidos VALUES(8,2,7,70)');
$r=consultar_mas_vendido_admin($db,'Producto más vendido este mes',$hoy);
comprobar_top(count($r['productos'])===2,'Los empates no se esconden');
$r=consultar_mas_vendido_admin($db,'Producto más vendido hoy',new DateTimeImmutable('2026-01-01'));
comprobar_top($r['productos']===[],'No se inventa un producto cuando no hay ventas');
comprobar_top(!requiere_gemini_asistente('El producto mas vendido?'),'La respuesta no depende de disponibilidad del LLM');
echo "8 pruebas correctas. Sin datos reales ni API externa.\n";
