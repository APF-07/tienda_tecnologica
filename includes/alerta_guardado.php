<?php
require_once __DIR__.'/telegram.php';
// Se llama únicamente después de confirmar la transacción del producto.
function notificar_stock_guardado(int $id,array $anterior,array $actual,?callable $api=null,?array $config=null,?string $archivo=null): string {
    $antes=(int)$anterior['stock'];$ahora=(int)$actual['stock'];
    if((int)$actual['estado']!==1||($antes===$ahora&&(int)$anterior['estado']===1))return '';
    $recuperado=(int)$anterior['estado']===1&&$antes<=5&&$ahora>5;
    if(!$recuperado&&$ahora>5)return '';
    try{
        $dir=__DIR__.'/../storage/multiagentes';
        if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar el registro.');
        $lock=fopen($dir.'/ejecucion.lock','c');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return 'Producto guardado. Telegram pendiente: hay otra revisión en curso; revisa Agentes y alertas.';}
        try{
            $fecha=date('Y-m-d H:i:s');
            $producto=['id_producto'=>$id,'codigo'=>$actual['codigo'],'nombre'=>$actual['nombre'],'stock'=>$ahora,'prioridad'=>$ahora===0?'Crítica':'Revisar','reposicion_sugerida'=>max(0,6-$ahora)];
            $evento=['firma'=>$recuperado?bin2hex(random_bytes(16)):hash('sha256',json_encode([$ahora,$producto['prioridad']])),'creada'=>$fecha,'actualizada'=>$fecha,'producto'=>$producto];
            if($recuperado)$evento+=['tipo'=>'recuperado','stock_anterior'=>$antes];
            $evento['mensaje']=($recuperado?'Stock recuperado':'Stock bajo').' · '.$actual['nombre'].' · '.$ahora.' unidades.';
            $evento['estado']='Local';
            $claveRecuperacion=$evento['firma'];
            $r=['fecha'=>$fecha,'notificaciones'=>['activas'=>$recuperado?[]:[$id=>$evento],'recuperadas'=>$recuperado?[$claveRecuperacion=>$evento]:[]]];
            $envio=enviar_alertas_telegram($r,$config??configuracion_telegram(),$api,$archivo);
            // Sincronizar con la revisión periódica para no repetir la recuperación.
            if($archivo===null&&$envio['estado']==='Enviado'&&is_file($dir.'/estado.json')){
                $estado=json_decode((string)file_get_contents($dir.'/estado.json'),true,512,JSON_THROW_ON_ERROR);
                if($recuperado){unset($estado['notificaciones']['activas'][(string)$id]);$estado['notificaciones']['recuperadas'][$claveRecuperacion]=$evento;}
                else $estado['notificaciones']['activas'][(string)$id]=$evento;
                guardar_registro_telegram($dir.'/estado.json',$estado);
            }
            return $envio['estado']==='Enviado'?'Producto guardado. Telegram aceptó el aviso de '.($recuperado?'stock recuperado.':'stock bajo.'):'Producto guardado. Telegram: '.$envio['estado'].'. '.$envio['detalle'];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }catch(Throwable $e){
        error_log('No se pudo confirmar la notificación de stock guardado.');
        return 'Producto guardado. No se pudo confirmar el envío a Telegram; revisa Agentes y alertas.';
    }
}
