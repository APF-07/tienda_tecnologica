<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/registro_agentes.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);preparar_registro_agentes($db);
$n=0;$check=function($ok,$t)use(&$n){if(!$ok)throw new RuntimeException($t);$n++;echo "OK $t\n";};
$id=proveedor_gestion($db,['nombre'=>'Proveedor prueba','contacto'=>'contacto@example.test','plazo'=>2]);
$a=$db->query('SELECT * FROM alertas_operativas')->fetch();$check($a['clave']==='proveedor-alta-'.$id&&$a['estado']==='Pendiente','Alta guarda proveedor y aviso en una sola operación');
$check(str_contains($a['texto'],'Proveedor prueba')&&!str_contains($a['texto'],'contacto@example.test'),'Aviso informa nombre y plazo sin transmitir contacto');
proveedor_gestion($db,['id'=>$id,'nombre'=>'Proveedor editado','contacto'=>'','plazo'=>3,'activo'=>1]);$check((int)$db->query('SELECT COUNT(*) FROM alertas_operativas')->fetchColumn()===1,'Editar no duplica el aviso de alta');
registrar_alerta_operativa($db,'otro','otro',0,'Otro aviso');$mensajes=[];$api=function($c,$metodo,$datos)use(&$mensajes){$mensajes[]=$datos['text'];return ['message_id'=>1];};$config=['token'=>'simulado','chat_id'=>'simulado'];
enviar_alertas_operativas($db,$api,$config,1,'proveedor-alta-'.$id);enviar_alertas_operativas($db,$api,$config,1,'proveedor-alta-'.$id);
$check(count($mensajes)===1&&str_contains($mensajes[0],'Nuevo proveedor'),'Envío dirigido al alta y sin duplicados');
$check($db->query("SELECT estado FROM alertas_operativas WHERE clave='otro'")->fetchColumn()==='Pendiente','Envío del proveedor no consume otros avisos pendientes');
$db->exec("CREATE TRIGGER fallo_aviso BEFORE INSERT ON alertas_operativas BEGIN SELECT RAISE(ABORT,'fallo'); END");
try{proveedor_gestion($db,['nombre'=>'Alta fallida','plazo'=>2]);}catch(Throwable $e){}
$check((int)$db->query('SELECT COUNT(*) FROM proveedores')->fetchColumn()===1,'Si falla registrar el aviso también se revierte el alta');
echo "$n pruebas correctas. SQLite temporal; sin Telegram real.\n";
