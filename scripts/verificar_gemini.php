<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/gemini.php';
$config=configuracion_gemini();
foreach($argv as $argumento){
    if(str_starts_with($argumento,'--modelo=')){
        $modelo=substr($argumento,9);
        if(!preg_match('/^gemini-[a-z0-9.-]+$/D',$modelo))throw new InvalidArgumentException('Modelo inválido.');
        $config['modelo']=$modelo;
    }
}
if(!$config['habilitado']||$config['clave']===''){fwrite(STDERR,"Falta configurar Gemini.\n");exit(1);}
try{
    if(in_array('--diagnostico',$argv,true)){
        $mensajes=[['role'=>'user','parts'=>[['text'=>'Responde OK.']]]];
        if(in_array('--contexto',$argv,true)){
            require __DIR__.'/../config/conexion.php';
            $mensajes=mensajes_gemini([],'¿Qué productos se agotarán mañana?',contexto_gemini($conexion));
        }
        $curl=curl_init('https://generativelanguage.googleapis.com/v1beta/models/'.$config['modelo'].':generateContent');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$config['clave']],
            CURLOPT_POSTFIELDS=>json_encode(peticion_gemini($mensajes))]);
        $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        echo 'HTTP '.$status."\n";
        $mensaje=json_decode((string)$body,true)['error']['message'] ?? '';
        $mensaje=str_replace($config['clave'],'[clave omitida]',(string)$mensaje);
        $mensaje=preg_replace('/[A-Za-z0-9_.\-]{35,}/','[identificador omitido]',$mensaje);
        echo mb_substr($mensaje,0,1000)."\n";
        exit;
    }
    if(in_array('--modelos',$argv,true)){
        $curl=curl_init('https://generativelanguage.googleapis.com/v1beta/models');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>['x-goog-api-key: '.$config['clave']]]);
        $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if($status!==200)throw new RuntimeException('No se pudo consultar la lista de modelos. HTTP '.$status);
        foreach(json_decode($body,true)['models'] ?? [] as $modelo){
            if(in_array('generateContent',$modelo['supportedGenerationMethods'] ?? [],true))echo $modelo['name']."\n";
        }
        exit;
    }
    consultar_gemini($config,peticion_gemini([['role'=>'user','parts'=>[['text'=>'Responde solamente: Conexión correcta.']]]]));
    echo "Gemini respondió correctamente. Modelo: ".$config['modelo']."\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");exit(1);}
