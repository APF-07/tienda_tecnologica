<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/asistente.php';
$db=new PDO('sqlite::memory:');$db->exec('CREATE TABLE clientes(id_cliente INT,nombre TEXT,apellido TEXT); CREATE TABLE pedidos(id_pedido INT,id_cliente INT,estado TEXT,total REAL,fecha_pedido TEXT)');
for($i=1;$i<=6;$i++){$db->prepare('INSERT INTO clientes VALUES(?,?,?)')->execute([$i,'Cliente'.$i,'Prueba']);for($j=0;$j<=6-$i;$j++)$db->prepare('INSERT INTO pedidos VALUES(?,?,?,?,?)')->execute([$i*10+$j,$i,'Entregado',$i*100,date('Y-m-d H:i:s')]);}
$n=0;$check=function($ok,$t)use(&$n){if(!$ok)throw new RuntimeException($t);$n++;echo "OK $t\n";};
foreach(['Los 5 top clientes del sistema?','Top 5 clientes','Los cinco mejores clientes'] as $p){$r=responder_asistente($db,$p);$check(count($r['enlaces'])===5&&str_contains($r['texto'],'1. Cliente1')&&!str_contains($r['texto'],'Cliente6'),'Respeta cinco posiciones: '.$p);}
$r=responder_asistente($db,'Top 3 clientes por importe');$check(count($r['enlaces'])===3&&str_contains($r['texto'],'1. Cliente3'),'Ranking por importe usa otro orden y desempate explícito');
$r=responder_asistente($db,'Top 50 clientes');$check(count($r['enlaces'])===6&&str_contains($r['texto'],'Solo hay 6'),'No inventa clientes para completar un ranking');
$check(!requiere_gemini_asistente('Los 5 top clientes del sistema?'),'Ranking local evita transmitir nombres a Gemini');
$r=responder_asistente($db,'Top 100 clientes');$check(count($r['enlaces'])===0&&str_contains($r['texto'],'1 a 50'),'Límite excesivo se explica sin recortar silenciosamente');
echo "$n pruebas correctas; sin APIs ni datos reales.\n";
