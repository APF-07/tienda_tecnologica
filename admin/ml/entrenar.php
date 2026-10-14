<?php
require_once '../../includes/verificar_admin.php';
exigir_post();
header('Content-Type: application/json; charset=UTF-8');
set_time_limit(240);
$lock=fopen(__DIR__.'/../../ml/entrenamiento.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { http_response_code(409);echo json_encode(['ok'=>false,'mensaje'=>'Ya hay un entrenamiento en curso. Espera a que termine.']);exit; }
session_write_close();
try {
    $python=getenv('PYTHON_BIN') ?: (PHP_OS_FAMILY==='Windows'?'python':'python3');
    $script=realpath(__DIR__.'/../../ml/procesar_todas_predicciones.py');
    $log=__DIR__.'/../../ml/entrenamiento.log';
    $proceso=proc_open([$python,$script],[0=>['pipe','r'],1=>['file',$log,'w'],2=>['file',$log,'a']],$pipes,__DIR__.'/../../ml',null,['bypass_shell'=>true]);
    if(!is_resource($proceso)) throw new RuntimeException('No se pudo iniciar Python.');
    fclose($pipes[0]);
    $codigo=proc_close($proceso);
    if($codigo!==0) throw new RuntimeException('No se completó el entrenamiento.');
    echo json_encode(['ok'=>true,'mensaje'=>'Modelos entrenados y evaluados correctamente.']);
} catch(Throwable $error) {
    error_log($error->getMessage());http_response_code(500);
    echo json_encode(['ok'=>false,'mensaje'=>'No se pudo completar el entrenamiento. Revisa la configuración de Python y el registro del entrenamiento.']);
} finally {
    flock($lock,LOCK_UN);fclose($lock);
}
