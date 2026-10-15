<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/telegram.php';
try{
    $config=configuracion_telegram();$bot=telegram_api($config,'getMe');echo "Token verificado con Telegram. Bot: @".($bot['username']??'sin usuario')."\n";
    if(in_array('--vincular',$argv,true)){
        if($config['chat_id']!==''){echo "Ya existe un chat configurado. No se reemplazó.\n";exit;}
        $updates=telegram_api($config,'getUpdates',['timeout'=>0,'limit'=>100]);$chats=[];
        foreach($updates as $update){$m=$update['message']??null;$texto=mb_strtolower(trim($m['text']??''));if(($m['chat']['type']??'')==='private'&&empty($m['from']['is_bot'])&&in_array($texto,['hola techstore','vincular techstore'],true))$chats[(string)$m['chat']['id']]=true;}
        if(count($chats)!==1)throw new RuntimeException(count($chats)?'Hay varios chats privados; configura TELEGRAM_CHAT_ID del destinatario correcto manualmente.':'No hay un mensaje privado reciente. Escribe Hola TechStore a tu bot y vuelve a intentar.');
        $id=(string)array_key_first($chats);if(!preg_match('/^\d+$/D',$id))throw new RuntimeException('Chat privado no válido.');
        $ruta=__DIR__.'/../.env';$texto=(string)file_get_contents($ruta);
        if(preg_match('/^\s*TELEGRAM_CHAT_ID\s*=/m',$texto))$texto=preg_replace('/^\s*TELEGRAM_CHAT_ID\s*=.*$/m','TELEGRAM_CHAT_ID='.$id,$texto);else $texto=rtrim($texto)."\nTELEGRAM_CHAT_ID=".$id."\n";
        if(file_put_contents($ruta,$texto,LOCK_EX)===false)throw new RuntimeException('No se pudo guardar el chat.');echo "Chat privado único vinculado en .env. No se enviaron mensajes.\n";
    }
    if(in_array('--prueba',$argv,true)){
        if($config['chat_id']==='')throw new RuntimeException('Falta vincular el chat antes de la prueba.');
        telegram_api($config,'sendMessage',['chat_id'=>$config['chat_id'],'text'=>'✅ TechStore: conexión de Telegram verificada. Este es un mensaje de prueba. Las alertas de inventario se enviarán a este chat.']);echo "Telegram aceptó el mensaje de prueba.\n";
    }
}catch(Throwable $e){fwrite(STDERR,$e instanceof RuntimeException?$e->getMessage()."\n":"No se pudo completar la configuración.\n");exit(1);}
