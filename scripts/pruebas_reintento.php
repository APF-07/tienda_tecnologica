<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/gemini.php';
function verificar_retry($condicion,$nombre){if(!$condicion)throw new RuntimeException($nombre);echo "OK: $nombre\n";}
$llamadas=0;$esperas=0;$enviar=static function($c,$p)use(&$llamadas){$llamadas++;if($llamadas===1)throw new RuntimeException('503',503);return 'Respuesta generada';};
$pausa=static function()use(&$esperas){$esperas++;};
$r=consultar_gemini([],[],$enviar,$pausa);
verificar_retry($r==='Respuesta generada'&&$llamadas===2&&$esperas===1,'Un 503 seguido de éxito se recupera con un reintento');
$llamadas=0;$enviar=static function($c,$p)use(&$llamadas){$llamadas++;throw new RuntimeException('503',503);};
try{consultar_gemini([],[],$enviar,$pausa);throw new LogicException('Se ocultó el error');}catch(RuntimeException $e){verificar_retry($llamadas===2&&$e->getCode()===503,'Dos fallos finalizan sin bucle ni respuesta inventada');}
$llamadas=0;try{consultar_gemini(['permiso_reintento'=>static fn()=>false],[],$enviar,$pausa);throw new LogicException('Se ignoró cuota');}catch(RuntimeException $e){verificar_retry($llamadas===1,'Reintento respeta la cuota local');}
$llamadas=0;$enviar=static function($c,$p)use(&$llamadas){$llamadas++;throw new RuntimeException('Cuota',429);};
try{consultar_gemini([],[],$enviar,$pausa);throw new LogicException('Se ignoró error');}catch(RuntimeException $e){verificar_retry($llamadas===1,'No se reintenta un error de cuota');}
foreach([500,502,504] as $codigo){
    $llamadas=0;
    $enviar=static function($c,$p)use(&$llamadas,$codigo){$llamadas++;if($llamadas===1)return interpretar_gemini($codigo,[]);return 'Recuperado';};
    verificar_retry(consultar_gemini([],[],$enviar,$pausa)==='Recuperado'&&$llamadas===2,'Fallo HTTP '.$codigo.' permite un solo reintento');
}
echo "7 pruebas correctas. Sin conexión externa.\n";
