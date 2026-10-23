<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/conexion.php';
require __DIR__.'/../includes/multiagentes.php';
try{
    require_once __DIR__.'/../includes/control_ml.php';
    try{$ml=entrenar_control_ml($conexion,true);echo 'ML automático: '.$ml['estado']."\n";}catch(Throwable $e){error_log($e->getMessage());echo "ML automático: requiere revisión. Los agentes continúan con la vigencia indicada.\n";}
    $r=ejecutar_multiagentes($conexion,in_array('--enviar-telegram',$argv,true));
    echo 'Revisión completada: '.$r['inventario']['total'].' alertas activas, '.$r['notificaciones']['nuevas']." nuevas o cambiadas.\n";
    echo 'Stock recuperado en esta revisión: '.($r['notificaciones']['nuevas_recuperadas']??0)." productos.\n";
    if(isset($r['telegram']))echo 'Telegram: '.$r['telegram']['estado'].' · '.$r['telegram']['detalle']."\n";
    if(isset($r['telegram'])&&in_array($r['telegram']['estado'],['Error','Sin configurar','Revisar entrega'],true))exit(2);
}catch(Throwable $e){fwrite(STDERR,"La revisión no se completó. Comprueba MySQL y la configuración local.\n");exit(1);}
