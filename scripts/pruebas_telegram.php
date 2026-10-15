<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/telegram.php';
$directorio=__DIR__.'/../storage/multiagentes';if(!is_dir($directorio))mkdir($directorio,0700,true);
$ruta=$directorio.'/prueba-'.bin2hex(random_bytes(8)).'.json';file_put_contents($ruta,'{}');
$n=0;$check=function($ok,$texto)use(&$n){if(!$ok)throw new RuntimeException($texto);$n++;echo "OK: $texto\n";};
$a=['firma'=>'firma1','creada'=>'2026-10-14 10:00:00','producto'=>['nombre'=>'Producto <prueba>','codigo'=>'P1','stock'=>0,'prioridad'=>'Crítica','reposicion_sugerida'=>6]];
$r=['fecha'=>'2026-10-14 10:00:00','notificaciones'=>['activas'=>['1'=>$a]]];$config=['token'=>'123:simulado','chat_id'=>'123'];$llamadas=0;
$api=function($c,$metodo,$datos)use(&$llamadas,$check){$llamadas++;$check($metodo==='sendMessage'&&$datos['chat_id']==='123'&&!isset($datos['parse_mode']),'Envío usa destinatario configurado y texto sin interpretar HTML');return ['message_id'=>42];};
try{
    $salida=enviar_alertas_telegram($r,['token'=>'','chat_id'=>''],$api,$ruta);$check($llamadas===0&&$salida['estado']==='Sin configurar','Sin configuración no se hacen envíos');
    $salida=enviar_alertas_telegram($r,$config,$api,$ruta);$check($salida['estado']==='Enviado'&&$salida['enviadas']===1,'Se registra éxito solo después de aceptación del proveedor');
    $salida=enviar_alertas_telegram($r,$config,$api,$ruta);$check($llamadas===1&&$salida['estado']==='Sin novedades','Revisiones repetidas no repiten el mismo aviso');
    $r['notificaciones']['activas']['1']['firma']='firma2';
    $salida=enviar_alertas_telegram($r,$config,static function(){throw new RuntimeException('Respuesta no confirmada',900);},$ruta);$check($salida['estado']==='Revisar entrega','Timeout se marca incierto sin declarar éxito');
    $salida=enviar_alertas_telegram($r,$config,$api,$ruta);$check($llamadas===1&&$salida['estado']==='Revisar entrega','Entrega incierta no se reenvía automáticamente');
    $r['notificaciones']['activas']['1']['firma']='firma3';
    $salida=enviar_alertas_telegram($r,$config,static function(){throw new RuntimeException('Rechazado',429);},$ruta);$check($salida['estado']==='Error','Rechazo explícito conserva un estado de error');
    $salida=enviar_alertas_telegram($r,$config,$api,$ruta);$check($llamadas===2&&$salida['estado']==='Enviado','Tras rechazo explícito se puede intentar en otra revisión');
    $texto=texto_alertas_telegram(array_fill(0,55,$a),$r['fecha']);$check(mb_strlen($texto)<=4096&&str_contains($texto,'Se muestran 8'),'Resumen agrupa alertas y respeta longitud de Telegram');
    $recuperada=['tipo'=>'recuperado','firma'=>'recuperacion1','creada'=>'2026-10-14 12:00:00','stock_anterior'=>3,'producto'=>['nombre'=>'Recuperado','codigo'=>'R1','stock'=>20]];
    $r['notificaciones']=['activas'=>[],'recuperadas'=>['evento1'=>$recuperada]];
    $salida=enviar_alertas_telegram($r,$config,$api,$ruta);$check($salida['enviadas']===1&&$llamadas===3,'La recuperación se envía aunque ya no haya alertas de stock bajo');
    $salida=enviar_alertas_telegram($r,$config,$api,$ruta);$check($llamadas===3&&$salida['estado']==='Sin novedades','No repite el aviso de recuperación ya aceptado');
    $texto=texto_alertas_telegram([$recuperada],$r['fecha']);$check(str_contains($texto,'STOCK RECUPERADO')&&str_contains($texto,'Stock anterior: 3')&&str_contains($texto,'Stock actual: 20'),'El mensaje de recuperación comunica los dos estados');
    echo "$n comprobaciones correctas. Sin token real ni conexión externa.\n";
}finally{if(is_file($ruta))unlink($ruta);if(is_file($ruta.'.tmp'))unlink($ruta.'.tmp');}
