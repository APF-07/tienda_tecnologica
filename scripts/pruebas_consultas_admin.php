<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/asistente.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE productos(id_producto INTEGER,nombre TEXT,codigo TEXT,stock INTEGER,precio REAL,estado INTEGER,id_marca INTEGER,id_categoria INTEGER)');
$db->exec('CREATE TABLE marcas(id_marca INTEGER,nombre TEXT)');$db->exec('CREATE TABLE categorias(id_categoria INTEGER,nombre TEXT)');
$db->exec("INSERT INTO marcas VALUES(1,'ASUS'),(2,'Samsung')");$db->exec("INSERT INTO categorias VALUES(1,'Laptops'),(2,'Celulares')");
$db->exec("INSERT INTO productos VALUES(1,'Laptop ASUS','LAP1',4,1200,1,1,1),(2,'Celular Samsung','CEL1',20,500,1,2,2),(3,'ASUS oculto','LAP2',2,100,0,1,1)");
$db->exec('CREATE TABLE pedidos(id_pedido INTEGER,total REAL,estado TEXT,fecha_pedido TEXT)');
$db->exec("INSERT INTO pedidos VALUES(1,100,'Entregado','2026-10-14 10:00:00'),(2,200,'Confirmado','2026-10-14 11:00:00'),(3,999,'Cancelado','2026-10-14 12:00:00'),(4,999,'Pendiente','2026-10-14 13:00:00'),(5,50,'Entregado','2026-10-13 23:59:59'),(6,700,'Entregado','2026-10-15 00:00:00')");
function verificar_admin_chat($c,$m){if(!$c)throw new RuntimeException($m);echo "OK: $m\n";}
$r=responder_asistente($db,'¿Qué stock tiene ASUS?');verificar_admin_chat(count($r['datos']['productos'])===1&&$r['datos']['productos'][0]['nombre']==='Laptop ASUS','Pregunta específica filtra por marca y excluye inactivos');
$r=responder_asistente($db,'Busca productos que-no-existen');verificar_admin_chat($r['datos']['total_coincidencias']===0&&str_contains($r['texto'],'No hay'),'Producto inexistente no devuelve todo el catálogo');
$r=consultar_productos_admin($db,"' OR 1=1 --");verificar_admin_chat($r['total_coincidencias']===0,'Búsqueda parametrizada no ejecuta SQL del usuario');
$r=consultar_productos_admin($db,'%');verificar_admin_chat($r['total_coincidencias']===0,'Los comodines se buscan como texto literal');
$hoy=new DateTimeImmutable('2026-10-14');$r=consultar_ventas_admin($db,'¿Cuánto vendí hoy?',$hoy);
verificar_admin_chat((int)$r['pedidos']===2&&(float)$r['importe']===300.0,'Ventas del día excluyen pendientes, cancelados y otras fechas');
$r=consultar_ventas_admin($db,'ventas ayer',$hoy);verificar_admin_chat((float)$r['importe']===50.0,'Periodo de ayer respeta límites de medianoche');
$r=periodo_ventas_admin('ventas de este mes',$hoy);verificar_admin_chat($r['inicio']==='2026-10-01 00:00:00','Mes inicia en su primer día');
verificar_admin_chat(intencion_asistente('Explícame la predicción de ventas')==='ml_ventas','Pronóstico se diferencia de ventas observadas');
verificar_admin_chat(intencion_asistente('¿Cuánto vendí hoy?')==='ventas_actuales','Pregunta natural reconoce ventas del día');
echo "9 pruebas correctas. Sin datos reales ni API externa.\n";
