<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/gemini.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE clientes(id_cliente INTEGER,nombre TEXT,apellido TEXT)');
$db->exec('CREATE TABLE pedidos(id_pedido INTEGER,id_cliente INTEGER,estado TEXT,total REAL)');
$db->exec("INSERT INTO clientes VALUES(1,'Ana','Prueba'),(2,'Luis','Prueba')");
function comprobar_comprador($valor,$texto){if(!$valor)throw new RuntimeException($texto);echo "OK: $texto\n";}
$r=responder_asistente($db,'Quiero saber quien es el comprador mas frecuente');comprobar_comprador(str_contains($r['texto'],'Todavía no hay'),'Sin compras no se inventa un ganador');
$db->exec("INSERT INTO pedidos VALUES(1,1,'Entregado',100),(2,1,'Confirmado',200),(3,2,'Entregado',100),(4,2,'Cancelado',900),(5,2,'Pendiente',900)");
$r=responder_asistente($db,'¿Qué cliente compra más?');comprobar_comprador(str_contains($r['texto'],'Ana Prueba')&&!str_contains($r['texto'],'Luis Prueba'),'Frecuencia excluye pendientes y cancelados');
comprobar_comprador(str_contains($r['texto'],'2 compras confirmadas')&&str_contains($r['texto'],'300.00'),'Cantidad e importe se calculan con pedidos válidos');
$db->exec("INSERT INTO pedidos VALUES(6,2,'Enviado',200)");
$r=responder_asistente($db,'Comprador más frecuente');comprobar_comprador(str_contains($r['texto'],'empate')&&str_contains($r['texto'],'Ana Prueba')&&str_contains($r['texto'],'Luis Prueba'),'Empates se muestran explícitamente');
$llamadas=0;$transporte=static function($c,$p)use(&$llamadas){$llamadas++;return 'No debería llamarse';};
$r=responder_con_gemini($db,'Comprador más frecuente',[],$transporte,['habilitado'=>true,'clave'=>'simulada','modelo'=>'gemini-3.1-flash-lite']);
comprobar_comprador($llamadas===0&&$r['modo']==='local','Consulta local no envía nombres ni consume API');
try{interpretar_gemini(503,[]);throw new LogicException('No se detectó error');}catch(RuntimeException $error){comprobar_comprador($error->getCode()===503,'Error 503 se explica como indisponibilidad temporal');}
echo "6 pruebas correctas. Sin datos reales ni llamadas externas.\n";
