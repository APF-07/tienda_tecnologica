<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once '../../includes/seguridad.php';
if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
    http_response_code(401); echo json_encode(['error'=>'Tu sesión terminó. Vuelve a iniciar sesión.']); exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { http_response_code(405); header('Allow: POST'); echo json_encode(['error'=>'Usa el formulario del asistente.']);exit; }
if (($_POST['accion'] ?? '')==='limpiar') {
    unset($_SESSION['asistente_historial']);echo json_encode(['ok'=>true]);exit;
}
$pregunta=$_POST['pregunta'] ?? null;
if (!is_string($pregunta) || trim($pregunta)==='' || longitud_texto($pregunta)>500) {
    http_response_code(422);echo json_encode(['error'=>'Escribe una pregunta de hasta 500 caracteres.']);exit;
}
require_once '../../config/conexion.php';
require_once '../../includes/gemini.php';
try {
    $config=configuracion_gemini();
    $ventana=$_SESSION['asistente_cuota'] ?? ['inicio'=>time(),'cantidad'=>0];
    if(time()-$ventana['inicio']>=60)$ventana=['inicio'=>time(),'cantidad'=>0];
    if($config['habilitado']&&$config['clave']!==''&&requiere_gemini_asistente($pregunta)){
        if($ventana['cantidad']>=5){
            $config['habilitado']=false;
            $limitado=true;
        }else{
            $ventana['cantidad']++;
            $_SESSION['asistente_cuota']=$ventana;
        }
    }
    $config['permiso_reintento']=static function():bool {
        $cuota=$_SESSION['asistente_cuota'] ?? ['inicio'=>time(),'cantidad'=>0];
        if(time()-$cuota['inicio']>=60)$cuota=['inicio'=>time(),'cantidad'=>0];
        if($cuota['cantidad']>=5)return false;
        $cuota['cantidad']++;$_SESSION['asistente_cuota']=$cuota;return true;
    };
    $respuesta=responder_con_gemini($conexion,trim($pregunta),$_SESSION['asistente_historial'] ?? [],null,$config);
    if(!empty($limitado))$respuesta['aviso']='Límite local de 5 consultas a Gemini por minuto alcanzado. Esta respuesta usa consultas locales.';
    // Los nombres obtenidos por esta consulta local tampoco pasan al historial enviado a Google.
    if(intencion_asistente($pregunta)!=='comprador_frecuente'&&empty($respuesta['privado'])&&empty($respuesta['aviso'])){
        $_SESSION['asistente_historial'][]=['rol'=>'user','texto'=>trim($pregunta)];
        $_SESSION['asistente_historial'][]=['rol'=>'model','texto'=>$respuesta['texto']];
    }
    $_SESSION['asistente_historial']=array_slice($_SESSION['asistente_historial'] ?? [],-6);
    session_write_close();
    echo json_encode($respuesta,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}
catch(Throwable $error) { error_log('Asistente: '.$error->getMessage());http_response_code(500);echo json_encode(['error'=>'No pude consultar los datos. Intenta nuevamente.']); }
