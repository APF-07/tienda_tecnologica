<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/conexion.php';
require __DIR__.'/../includes/asistente.php';
function verificar_asistente($condicion,$mensaje){if(!$condicion)throw new RuntimeException($mensaje);echo "OK: $mensaje\n";}
$conexion->exec('CREATE TEMPORARY TABLE productos (id_producto INT,nombre VARCHAR(100),stock INT,estado INT)');
$conexion->exec('CREATE TEMPORARY TABLE clientes (id_cliente INT)');
$conexion->exec('CREATE TEMPORARY TABLE pedidos (id_pedido INT,total DECIMAL(10,2),estado VARCHAR(40),fecha_pedido DATETIME)');
$conexion->exec("INSERT INTO productos VALUES(1,'Equipo sin stock',0,1),(2,'Equipo con stock',20,1),(3,'Oculto',1,0)");
$conexion->exec("INSERT INTO clientes VALUES(1),(2)");
$conexion->exec("INSERT INTO pedidos VALUES(1,100,'Pendiente','2026-10-01 10:00:00'),(2,200,'Entregado','2026-10-02 10:00:00')");
$r=responder_asistente($conexion,'¿Cuántos pedidos están pendientes?');verificar_asistente(str_contains($r['texto'],'1 pedidos pendientes'),'Pendientes calculados desde la base temporal');
$r=responder_asistente($conexion,'¿Qué productos tienen poco stock?');verificar_asistente(str_contains($r['texto'],'Equipo sin stock')&&!str_contains($r['texto'],'Oculto'),'Inventario excluye productos inactivos');
$r=responder_asistente($conexion,'Dame los últimos pedidos');verificar_asistente(strpos($r['texto'],'#2')<strpos($r['texto'],'#1'),'Pedidos ordenados por fecha');
$r=responder_asistente($conexion,'¿Cuántos clientes hay?');verificar_asistente(str_contains($r['texto'],'2 clientes'),'Cantidad de clientes consultada');
$r=responder_asistente($conexion,'Borra todos los pedidos');verificar_asistente(str_contains($r['texto'],'solo consulta'),'Acciones de escritura rechazadas');
verificar_asistente((int)$conexion->query('SELECT COUNT(*) FROM pedidos')->fetchColumn()===2,'No se modifican los pedidos');
$r=responder_asistente($conexion,'SELECT password FROM usuarios');verificar_asistente(str_contains($r['texto'],'No tengo una consulta'),'No se ejecuta SQL del mensaje');
$r=responder_asistente($conexion,'Explícame la predicción de ventas',__DIR__.'/no-existe');verificar_asistente(str_contains($r['texto'],'Todavía no hay'),'Sin informe no inventa predicciones');
$r=responder_asistente($conexion,'¿Qué significa el porcentaje del ML?');verificar_asistente(str_contains($r['texto'],'No es una probabilidad'),'Porcentaje explicado sin garantía falsa');
$r=responder_asistente($conexion,'Explícame la predicción de pedidos');verificar_asistente(isset($r['fuente'],$r['fecha'],$r['enlaces']),'La respuesta identifica fuente y fecha');
echo "10 comprobaciones correctas. Solo se utilizaron tablas temporales.\n";
