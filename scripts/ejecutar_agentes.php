<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/conexion.php';
require __DIR__.'/../includes/multiagentes.php';
try{
    $r=ejecutar_multiagentes($conexion,in_array('--enviar-telegram',$argv,true));
    echo 'Revisión completada: '.$r['inventario']['total'].' alertas activas, '.$r['notificaciones']['nuevas']." nuevas o cambiadas.\n";
    echo 'Stock recuperado en esta revisión: '.($r['notificaciones']['nuevas_recuperadas']??0)." productos.\n";
    if(isset($r['telegram']))echo 'Telegram: '.$r['telegram']['estado'].' · '.$r['telegram']['detalle']."\n";
    if(in_array('--enviar-telegram',$argv,true)){require_once __DIR__.'/../includes/alertas_operativas.php';$operativas=revisar_alertas_operativas($conexion);echo 'Alertas de compras y demoras: '.$operativas['estado'].' · '.$operativas['enviadas']." enviadas.\n";if(in_array($operativas['estado'],['Error','Revisar entrega','Sin configurar'],true))exit(2);}
    if(isset($r['telegram'])&&in_array($r['telegram']['estado'],['Error','Sin configurar','Revisar entrega'],true))exit(2);
}catch(Throwable $e){fwrite(STDERR,"La revisión no se completó. Comprueba MySQL y la configuración local.\n");exit(1);}
