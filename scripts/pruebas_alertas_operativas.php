<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/alertas_operativas.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);preparar_alertas_operativas($db);
$db->exec('CREATE TABLE pedidos(id_pedido INT,estado TEXT,fecha_pedido TEXT)');
$n=0;function verificar_operativa($ok,$texto){global $n;if(!$ok)throw new RuntimeException($texto);$n++;echo "OK $texto\n";}
$productos=[['id_producto'=>1,'codigo'=>'TEST','nombre'=>'Equipo','stock'=>3],['id_producto'=>2,'codigo'=>'OTRO','nombre'=>'Otro','stock'=>8]];
$db->beginTransaction();registrar_stock_venta($db,10,$productos);$db->rollBack();
verificar_operativa((int)$db->query('SELECT COUNT(*) FROM alertas_operativas')->fetchColumn()===0,'Rollback de compra no conserva avisos');
registrar_stock_venta($db,10,$productos);registrar_stock_venta($db,10,$productos);
verificar_operativa((int)$db->query('SELECT COUNT(*) FROM alertas_operativas')->fetchColumn()===1,'Una alerta por pedido y producto bajo stock');
$config=['token'=>'123:simulado','chat_id'=>'simulado'];$mensajes=[];$api=function($c,$m,$d)use(&$mensajes){$mensajes[]=$d['text'];return ['message_id'=>1];};
enviar_alertas_operativas($db,$api,$config);enviar_alertas_operativas($db,$api,$config);
verificar_operativa(count($mensajes)===1&&str_contains($mensajes[0],'Stock restante: 3'),'Envío de stock tras compra y sin duplicados');
$db->exec("INSERT INTO pedidos VALUES(1,'Pendiente','2026-10-13 09:00:00'),(2,'Pendiente','2026-10-15 08:00:00'),(3,'Entregado','2026-10-10 00:00:00')");
detectar_pedidos_demorados($db,24,new DateTimeImmutable('2026-10-15 09:00:00'));detectar_pedidos_demorados($db,24,new DateTimeImmutable('2026-10-15 09:00:00'));
verificar_operativa((int)$db->query("SELECT COUNT(*) FROM alertas_operativas WHERE tipo='pedido_demorado'")->fetchColumn()===1,'Solo pendientes antiguos; detección repetida no duplica');
$db->exec("UPDATE pedidos SET estado='Confirmado' WHERE id_pedido=1");enviar_alertas_operativas($db,$api,$config);
verificar_operativa(count($mensajes)===1&&$db->query("SELECT estado FROM alertas_operativas WHERE clave='demora-1'")->fetchColumn()==='Descartada','No envía demora si el pedido ya fue atendido');
registrar_stock_venta($db,11,$productos);
$r=enviar_alertas_operativas($db,static function(){throw new RuntimeException('simulado',503);},$config);
verificar_operativa($r['estado']==='Error'&&$db->query("SELECT estado FROM alertas_operativas WHERE clave='venta-11-1'")->fetchColumn()==='Pendiente','Rechazo conserva aviso pendiente en base');
$db->exec("UPDATE alertas_operativas SET intento=NULL WHERE clave='venta-11-1'");
$r=enviar_alertas_operativas($db,static function(){throw new RuntimeException('timeout',900);},$config);enviar_alertas_operativas($db,$api,$config);
verificar_operativa($r['estado']==='Revisar entrega'&&count($mensajes)===1,'Timeout incierto no se reenvía ciegamente');
$db->exec("INSERT INTO pedidos VALUES(4,'Pendiente','2026-10-15 08:54:00'),(5,'Pendiente','2026-10-15 08:56:00')");
detectar_pedidos_demorados($db,1,new DateTimeImmutable('2026-10-15 09:00:00'),5,'2026-10-15 08:50:00');
verificar_operativa((int)$db->query("SELECT COUNT(*) FROM alertas_operativas WHERE clave='demora-4'")->fetchColumn()===1&&(int)$db->query("SELECT COUNT(*) FROM alertas_operativas WHERE clave='demora-5'")->fetchColumn()===0,'Demora de cinco minutos respeta el límite y excluye historial anterior');
echo "$n pruebas correctas. Sin MySQL real ni Telegram real.\n";
