<?php
require_once __DIR__.'/../config/telegram.php';
function telegram_api(array $config,string $metodo,array $datos=[]): array {
    if(!preg_match('/^\d+:[A-Za-z0-9_-]+$/D',$config['token']??''))throw new RuntimeException('Falta un token válido de Telegram.');
    if(!in_array($metodo,['getMe','getUpdates','sendMessage'],true))throw new RuntimeException('Método de Telegram no permitido.');
    $curl=curl_init('https://api.telegram.org/bot'.$config['token'].'/'.$metodo);
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>18,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($datos?:new stdClass(),JSON_THROW_ON_ERROR)]);
    $body=curl_exec($curl);$codigo=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);$error=curl_errno($curl);curl_close($curl);
    if($error||$body===false)throw new RuntimeException('No se pudo confirmar la respuesta de Telegram. No se reenviará automáticamente para evitar duplicados.',900);
    $respuesta=json_decode($body,true);
    if($codigo!==200||empty($respuesta['ok']))throw new RuntimeException(match($codigo){401=>'Telegram rechazó el token.',403=>'El bot no puede escribir en ese chat. Abre el bot y pulsa Iniciar.',409=>'El bot tiene otro receptor de actualizaciones o webhook. No se modificó su configuración.',429=>'Telegram pidió esperar antes de enviar más mensajes.',default=>'Telegram rechazó la solicitud (HTTP '.$codigo.').'},$codigo);
    return $respuesta['result'];
}
function texto_alertas_telegram(array $alertas,string $fecha): string {
    $texto="🔔 TechStore · Novedades de inventario\nRevisión: ".$fecha."\n".count($alertas)." avisos nuevos o pendientes de envío.\n";
    $recuperadas=count(array_filter($alertas,static fn($a)=>($a['tipo']??'')==='recuperado'));
    $texto.='✅ Recuperaciones: '.$recuperadas.' · ⚠️ Stock bajo: '.(count($alertas)-$recuperadas)."\n";
    uasort($alertas,static fn($a,$b)=>(int)(($b['tipo']??'')==='recuperado')<=>(int)(($a['tipo']??'')==='recuperado'));
    foreach(array_slice($alertas,0,8,true) as $alerta){$p=$alerta['producto'];
        if(($alerta['tipo']??'')==='recuperado'){$texto.="\n✅ STOCK RECUPERADO\n".mb_substr($p['nombre'],0,95).' ['.$p['codigo'].']'."\nStock anterior: ".$alerta['stock_anterior']." unidades\nStock actual: ".$p['stock']." unidades\nYa superó el umbral de stock bajo (5 unidades).\n";}
        else $texto.="\n".($p['stock']==0?'🔴':'🟡').' '.mb_substr($p['nombre'],0,95).' ['.$p['codigo'].']'."\nStock: ".$p['stock'].' · Prioridad: '.$p['prioridad']."\nReposición sugerida: ".$p['reposicion_sugerida']." unidades.\n";
    }
    if(count($alertas)>8)$texto.="\nSe muestran 8. Consulta el resto en Administración → Agentes y alertas.\n";
    return mb_substr($texto."\nSugerencias basadas en reglas y ventas históricas; requieren revisión humana. No se realizaron compras ni cambios de stock.",0,3900);
}
function guardar_registro_telegram(string $ruta,array $datos): void {
    $temporal=$ruta.'.tmp';if(file_put_contents($temporal,json_encode($datos,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),LOCK_EX)===false||!rename($temporal,$ruta))throw new RuntimeException('No se pudo guardar el control de envíos.');
}
function enviar_alertas_telegram(array $resultado,array $config,?callable $api=null,?string $archivo=null): array {
    if(($config['token']??'')===''||($config['chat_id']??'')==='')return ['estado'=>'Sin configurar','enviadas'=>0,'detalle'=>'Falta configurar el token o vincular tu chat.'];
    $api=$api??'telegram_api';$archivo=$archivo??__DIR__.'/../storage/multiagentes/envios.json';
    $registro=is_file($archivo)?json_decode((string)file_get_contents($archivo),true):[];
    if(!is_array($registro))throw new RuntimeException('No se pudo leer el control de envíos; se detuvo el envío.');
    $pendientes=[];$inciertas=0;
    $eventos=$resultado['notificaciones']['activas'];
    foreach($resultado['notificaciones']['recuperadas']??[] as $id=>$a)$eventos['recuperado-'.$id]=$a;
    foreach($eventos as $id=>$a){$clave=hash('sha256',$config['chat_id'].'|'.$id.'|'.$a['creada'].'|'.$a['firma']);$previo=$registro[$clave]['estado']??null;if(in_array($previo,['Enviado','En curso','Incierto'],true)){if($previo!=='Enviado')$inciertas++;continue;}$pendientes[$clave]=$a;}
    if(!$pendientes)return ['estado'=>$inciertas?'Revisar entrega':'Sin novedades','enviadas'=>0,'detalle'=>$inciertas?'Hay envíos sin confirmación. Revísalos antes de repetir.':'No hay alertas pendientes; no se repitieron mensajes.'];
    foreach($pendientes as $clave=>$a)$registro[$clave]=['estado'=>'En curso','fecha'=>date('Y-m-d H:i:s')];
    guardar_registro_telegram($archivo,$registro);
    try{
        $respuesta=$api($config,'sendMessage',['chat_id'=>$config['chat_id'],'text'=>texto_alertas_telegram($pendientes,$resultado['fecha'])]);
    }catch(RuntimeException $e){foreach($pendientes as $clave=>$a)$registro[$clave]=['estado'=>$e->getCode()===900?'Incierto':'Error','fecha'=>date('Y-m-d H:i:s')];guardar_registro_telegram($archivo,$registro);return ['estado'=>$e->getCode()===900?'Revisar entrega':'Error','enviadas'=>0,'detalle'=>$e->getMessage()];}
    // Si falla la escritura tras aceptar Telegram, conservar «En curso» en disco;
    // no convertir una entrega aceptada en un error que se reintente.
    foreach($pendientes as $clave=>$a)$registro[$clave]=['estado'=>'Enviado','fecha'=>date('Y-m-d H:i:s'),'message_id'=>$respuesta['message_id']??null];
    guardar_registro_telegram($archivo,$registro);
    return ['estado'=>'Enviado','enviadas'=>count($pendientes),'detalle'=>'Telegram aceptó un mensaje resumen con '.count($pendientes).' avisos de inventario.'];
}
