<?php
require_once __DIR__.'/../config/app.php';
function preparar_control_ml(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS ml_control (clave VARCHAR(40) PRIMARY KEY, valor TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS ml_entrenamientos (id VARCHAR(40) PRIMARY KEY, inicio VARCHAR(19) NOT NULL, fin VARCHAR(19), origen VARCHAR(20) NOT NULL, estado VARCHAR(30) NOT NULL, detalle TEXT NOT NULL)');
}
function modelos_control_ml(): array {
    return ['automatico'=>'Automático · elegir por evaluación','Ridge'=>'Ridge','Huber robusto'=>'Huber robusto','Random Forest'=>'Random Forest','Extra Trees'=>'Extra Trees','Gradient Boosting'=>'Gradient Boosting'];
}
function guia_modelos_control_ml(): array {
    return [
        'Ridge'=>['idea'=>'Aprende una relación lineal y limita coeficientes excesivos para reducir el sobreajuste.','tienda'=>'Estima ventas o cantidad de pedidos a partir del comportamiento de días anteriores. Es una opción sencilla para contrastar modelos más complejos.'],
        'Huber robusto'=>['idea'=>'Ajusta una tendencia dando menos influencia a errores extremos, sin eliminar las compras registradas.','tienda'=>'Puede ayudar cuando una compra excepcionalmente grande distorsiona el pronóstico de ventas. Su utilidad se comprueba con las métricas.'],
        'Random Forest'=>['idea'=>'Combina muchos árboles de decisión para aprender relaciones no lineales entre las variables.','tienda'=>'Pronostica ventas y pedidos usando rezagos, media previa, día de la semana y tendencia; ayuda a planificar presupuesto y despachos.'],
        'Extra Trees'=>['idea'=>'Combina árboles con divisiones más aleatorias que Random Forest para diversificar las estimaciones.','tienda'=>'Es una alternativa para contrastar patrones de ventas y pedidos y comprobar si reduce el error frente a los otros modelos.'],
        'Gradient Boosting'=>['idea'=>'Construye árboles sucesivos que intentan corregir los errores del conjunto anterior.','tienda'=>'Puede capturar patrones de demanda que otros modelos no representan bien. Requiere evaluación temporal para evitar sobreajuste.']
    ];
}
function leer_control_ml(PDO $db,string $clave,$defecto=null) {
    preparar_control_ml($db);$q=$db->prepare('SELECT valor FROM ml_control WHERE clave=?');$q->execute([$clave]);$v=$q->fetchColumn();return $v===false?$defecto:json_decode($v,true,512,JSON_THROW_ON_ERROR);
}
function escribir_control_ml(PDO $db,string $clave,$valor): void {
    preparar_control_ml($db);$sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO ml_control(clave,valor) VALUES(?,?) ON CONFLICT(clave) DO UPDATE SET valor=excluded.valor':'INSERT INTO ml_control(clave,valor) VALUES(?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)';
    $db->prepare($sql)->execute([$clave,json_encode($valor,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}
function configuracion_control_ml(PDO $db): array {
    $c=leer_control_ml($db,'configuracion',['automatico'=>true,'ventas'=>'automatico','pedidos'=>'automatico']);
    foreach(['ventas','pedidos'] as $tipo)if(!isset(modelos_control_ml()[$c[$tipo]??'automatico'])){$c['opciones_anteriores'][$tipo]=$c[$tipo];$c[$tipo]='automatico';}
    return $c;
}
function firma_control_ml(array $c): string {
    return hash('sha256',json_encode($c).'|'.hash_file('sha256',__DIR__.'/../ml/modelos.py').'|'.hash_file('sha256',__DIR__.'/../ml/predicciones_individuales.py').'|'.hash_file('sha256',__DIR__.'/../ml/controles_negocio.py'));
}
function guardar_control_ml(PDO $db,array $entrada): array {
    $c=['automatico'=>!empty($entrada['automatico'])];
    foreach(['ventas','pedidos'] as $tipo){$nombre=$entrada[$tipo]??'automatico';if(!is_string($nombre)||!array_key_exists($nombre,modelos_control_ml()))throw new RuntimeException('Selecciona un modelo permitido.');$c[$tipo]=$nombre;}
    escribir_control_ml($db,'configuracion',$c);return $c;
}
function requiere_entrenamiento_ml(PDO $db,?DateTimeImmutable $ahora=null): bool {
    $ahora=$ahora??new DateTimeImmutable();$c=configuracion_control_ml($db);if(!$c['automatico'])return false;
    if($ahora->getTimestamp()-(int)leer_control_ml($db,'intento',0)<3600)return false;
    $ultima=leer_control_ml($db,'ultima',[]);$firma=firma_control_ml($c);$corte=$ahora->modify('-1 day')->format('Y-m-d');
    if(($ultima['corte']??'')!==$corte||($ultima['configuracion']??'')!==$firma)return true;
    foreach(['ventas','pedidos'] as $tipo){$ruta=__DIR__.'/../ml/cache_todos_'.$tipo.'.json';$r=is_file($ruta)?json_decode((string)file_get_contents($ruta),true):null;if(($r['fecha_corte']??'')!==$corte)return true;}
    return false;
}
function ejecutar_proceso_ml(array $comando,string $log,array $env,int $limite=300): void {
    $p=proc_open($comando,[0=>['pipe','r'],1=>['file',$log,'w'],2=>['file',$log,'a']],$pipes,__DIR__.'/../ml',$env,['bypass_shell'=>true]);
    if(!is_resource($p))throw new RuntimeException('No se pudo iniciar Python.');fclose($pipes[0]);$fin=microtime(true)+$limite;
    do{$estado=proc_get_status($p);if(!$estado['running'])break;usleep(250000);}while(microtime(true)<$fin);
    if($estado['running']){proc_terminate($p);proc_close($p);throw new RuntimeException('El entrenamiento superó el tiempo límite. Revisa el registro antes de reintentar.');}
    $codigo=$estado['exitcode'];$cierre=proc_close($p);if(($codigo>=0?$codigo:$cierre)!==0)throw new RuntimeException('Python no completó el entrenamiento; revisa el registro ML del servidor.');
}
function entrenar_control_ml(PDO $db,bool $automatico=false): array {
    preparar_control_ml($db);$lock=fopen(__DIR__.'/../ml/entrenamiento.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock))fclose($lock);throw new RuntimeException('Ya hay un entrenamiento en curso.');}
    try {
        if($automatico&&!requiere_entrenamiento_ml($db))return ['estado'=>'Sin cambios','mensaje'=>'No corresponde entrenar ahora.'];
        $c=configuracion_control_ml($db);$firma=firma_control_ml($c);$inicio=date('Y-m-d H:i:s');$id=bin2hex(random_bytes(16));escribir_control_ml($db,'intento',time());
        $db->prepare('INSERT INTO ml_entrenamientos(id,inicio,origen,estado,detalle) VALUES(?,?,?,?,?)')->execute([$id,$inicio,$automatico?'Automático':'Manual','En curso',json_encode($c)]);
        $env=getenv();$env['ML_MODELO_VENTAS']=$c['ventas'];$env['ML_MODELO_PEDIDOS']=$c['pedidos'];
        $python=getenv('PYTHON_BIN')?:(PHP_OS_FAMILY==='Windows'?'python':'python3');$log=__DIR__.'/../ml/entrenamiento.log';
        ejecutar_proceso_ml([$python,__DIR__.'/../ml/procesar_todas_predicciones.py'],$log,$env);
        $cortes=[];foreach(['ventas','pedidos'] as $tipo){$r=json_decode((string)file_get_contents(__DIR__.'/../ml/cache_todos_'.$tipo.'.json'),true,512,JSON_THROW_ON_ERROR);$cortes[]=$r['fecha_corte'];}
        if(count(array_unique($cortes))!==1)throw new RuntimeException('Los informes no comparten fecha de corte.');
        escribir_control_ml($db,'ultima',['corte'=>$cortes[0],'configuracion'=>$firma,'fin'=>date('Y-m-d H:i:s')]);
        $db->prepare('UPDATE ml_entrenamientos SET fin=?,estado=?,detalle=? WHERE id=?')->execute([date('Y-m-d H:i:s'),'Completado','Informes publicados; corte '.$cortes[0],$id]);
        return ['estado'=>'Completado','mensaje'=>'Modelos comparados y pronósticos actualizados con MySQL.'];
    }catch(Throwable $e){if(isset($id))$db->prepare('UPDATE ml_entrenamientos SET fin=?,estado=?,detalle=? WHERE id=?')->execute([date('Y-m-d H:i:s'),'Error','Entrenamiento fallido. Revisa el registro ML del servidor.',$id]);throw $e;}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}
