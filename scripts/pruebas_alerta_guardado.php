<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/alerta_guardado.php';
$ruta=__DIR__.'/../storage/multiagentes/prueba-guardado-'.bin2hex(random_bytes(5)).'.json';
$llamadas=[];$api=function($config,$metodo,$datos)use(&$llamadas){$llamadas[]=$datos;return ['message_id'=>1];};
$config=['token'=>'123:prueba','chat_id'=>'prueba'];
$actual=['codigo'=>'TEST','nombre'=>'Producto prueba','stock'=>'15','estado'=>'1'];
function verificar_guardado($ok,$nombre){if(!$ok)throw new RuntimeException($nombre);echo "OK $nombre\n";}
try{
    $mensaje=notificar_stock_guardado(99,['stock'=>0,'estado'=>1],$actual,$api,$config,$ruta);
    verificar_guardado(count($llamadas)===1&&str_contains($llamadas[0]['text'],'STOCK RECUPERADO')&&str_contains($llamadas[0]['text'],'Stock anterior: 0'),'0 a 15 envía recuperación sin revisión previa');
    notificar_stock_guardado(99,['stock'=>15,'estado'=>1],$actual,$api,$config,$ruta);
    verificar_guardado(count($llamadas)===1,'Guardar sin cambio no envía');
    $actual['stock']='3';notificar_stock_guardado(99,['stock'=>15,'estado'=>1],$actual,$api,$config,$ruta);
    verificar_guardado(count($llamadas)===2&&str_contains($llamadas[1]['text'],'Stock: 3'),'15 a 3 envía stock bajo');
    $actual['estado']='0';notificar_stock_guardado(99,['stock'=>3,'estado'=>1],$actual,$api,$config,$ruta);
    verificar_guardado(count($llamadas)===2,'Desactivación no envía recuperación');
    $actual['estado']='1';$actual['stock']='15';
    $fallo=notificar_stock_guardado(99,['stock'=>0,'estado'=>1],$actual,static function(){throw new RuntimeException('fallo simulado',503);},$config,$ruta);
    verificar_guardado(str_contains($fallo,'Producto guardado.')&&str_contains($fallo,'Error'),'Fallo de Telegram conserva confirmación del guardado');
}finally{if(is_file($ruta))unlink($ruta);}
