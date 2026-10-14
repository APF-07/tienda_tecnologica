<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__.'/includes/seguridad.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['error'=>'Usa el chat de la tienda.']);exit;}
$pregunta=$_POST['pregunta']??null;
if(!is_string($pregunta)||trim($pregunta)===''||longitud_texto($pregunta)>500){http_response_code(422);echo json_encode(['error'=>'Escribe una consulta de hasta 500 caracteres.']);exit;}
$cuota=$_SESSION['chat_cliente_cuota']??['inicio'=>time(),'cantidad'=>0,'api'=>0];
if(time()-$cuota['inicio']>=60)$cuota=['inicio'=>time(),'cantidad'=>0,'api'=>0];
if($cuota['cantidad']>=20){http_response_code(429);echo json_encode(['error'=>'Has enviado varias consultas. Espera un minuto e inténtalo otra vez.']);exit;}
$cuota['cantidad']++;$_SESSION['chat_cliente_cuota']=$cuota;
require_once __DIR__.'/config/conexion.php';
require_once __DIR__.'/includes/chat_cliente.php';
require_once __DIR__.'/config/gemini.php';
try{
    $config=configuracion_gemini();
    $config['permiso_reintento']=static function():bool{
        if(($_SESSION['chat_cliente_cuota']['api']??0)>=5)return false;
        $_SESSION['chat_cliente_cuota']['api']++;return true;
    };
    // El transporte limita todas las llamadas reales, incluido el reintento.
    $transporte=static function($config,$cuerpo){
        if(!$config['permiso_reintento']())throw new RuntimeException('Límite temporal');
        return consultar_gemini($config,$cuerpo);
    };
    $r=responder_cliente($conexion,trim($pregunta),$_SESSION['chat_cliente_historial']??[],$config,$transporte);
    $_SESSION['chat_cliente_historial'][]=['rol'=>'user','texto'=>trim($pregunta)];
    $_SESSION['chat_cliente_historial'][]=['rol'=>'model','texto'=>$r['texto']];
    $_SESSION['chat_cliente_historial']=array_slice($_SESSION['chat_cliente_historial'],-4);
    session_write_close();echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $error){http_response_code(500);echo json_encode(['error'=>'No pudimos consultar la tienda. Intenta nuevamente.']);}
