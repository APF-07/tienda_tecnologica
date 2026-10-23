<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../includes/control_ml.php';
require __DIR__.'/../includes/multiagentes.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$n=0;$check=function($ok,$texto)use(&$n){if(!$ok)throw new RuntimeException($texto);$n++;echo "OK $texto\n";};
$check(configuracion_control_ml($db)['automatico']===true,'Entrenamiento automático disponible por defecto');
$check(count(modelos_control_ml())===6&&count(guia_modelos_control_ml())===5,'Cinco modelos ML y Automático como modo separado');
$check(array_keys(guia_modelos_control_ml())===array_values(array_diff(array_keys(modelos_control_ml()),['automatico'])),'Cada modelo seleccionable tiene explicación de su uso');
guardar_control_ml($db,['ventas'=>'Ridge','pedidos'=>'Extra Trees']);
$check(configuracion_control_ml($db)['ventas']==='Ridge'&&!requiere_entrenamiento_ml($db),'Modo desactivado no programa entrenamiento');
$error=false;try{guardar_control_ml($db,['ventas'=>'modelo inventado']);}catch(RuntimeException $e){$error=true;}$check($error,'Modelo ajeno a la lista es rechazado');
foreach(['Random Forest log','Referencia semanal','Mediana histórica','Mediana por día de semana'] as $retirado){$error=false;try{guardar_control_ml($db,['ventas'=>$retirado]);}catch(RuntimeException $e){$error=true;}$check($error,'No ofrece selección manual de '.$retirado);}
escribir_control_ml($db,'configuracion',['automatico'=>true,'ventas'=>'Random Forest log','pedidos'=>'Referencia semanal']);$anterior=configuracion_control_ml($db);
$check($anterior['ventas']==='automatico'&&$anterior['pedidos']==='automatico'&&count($anterior['opciones_anteriores'])===2,'Configuración antigua se adapta sin bloquear el entrenamiento y conserva el aviso');
guardar_control_ml($db,['automatico'=>1,'ventas'=>'automatico','pedidos'=>'automatico']);
$reloj=new DateTimeImmutable('2026-10-19 12:00:00');$check(requiere_entrenamiento_ml($db,$reloj),'Sin entrenamiento publicado requiere actualización');
escribir_control_ml($db,'intento',$reloj->getTimestamp());$check(!requiere_entrenamiento_ml($db,$reloj->modify('+59 minutes')),'Espera mínima evita tormenta de reintentos');
$check(requiere_entrenamiento_ml($db,$reloj->modify('+61 minutes')),'Puede reintentar al superar una hora');
$db->exec('CREATE TABLE productos(id_producto INT,estado INT,stock INT)');$db->exec('INSERT INTO productos VALUES(1,1,3)');
$mensaje=['inventario'=>['emisor'=>'inventario','total'=>1,'productos'=>[['id_producto'=>1]]],'seguimiento'=>['emisor'=>'seguimiento'],'proyeccion'=>['emisor'=>'proyeccion','informes'=>['ventas'=>['estado'=>'Vigente']]],'analisis'=>['emisor'=>'analisis','alertas'=>[['id_producto'=>1,'reposicion_sugerida'=>3]]],'notificaciones'=>['emisor'=>'notificaciones']];
$a=(new AgenteAuditor())->ejecutar($db,$mensaje);$check($a['autoriza_envio']&&$a['estado']==='Aprobado','Auditor acepta entradas consistentes');
$mensaje['proyeccion']['informes']['ventas']['estado']='Desactualizado';$a=(new AgenteAuditor())->ejecutar($db,$mensaje);$check($a['autoriza_envio']&&$a['estado']==='Con observaciones','ML vencido se advierte sin suprimir alertas válidas de inventario');
$mensaje['proyeccion']['informes']['ventas']=['estado'=>'Vigente','metricas'=>['Error ponderado WAPE (%)'=>66.2]];$a=(new AgenteAuditor())->ejecutar($db,$mensaje);$check($a['estado']==='Con observaciones'&&str_contains($a['observaciones'][0],'Error histórico alto'),'Un modelo vigente con error alto también exige revisión');
$mensaje['inventario']['total']=2;$check(!(new AgenteAuditor())->ejecutar($db,$mensaje)['autoriza_envio'],'Contadores incoherentes bloquean envío del coordinador');
$mensaje['inventario']['total']=1;$db->exec('UPDATE productos SET stock=-1');$check(!(new AgenteAuditor())->ejecutar($db,$mensaje)['autoriza_envio'],'Stock negativo bloquea publicación');
echo "$n comprobaciones correctas; sin APIs ni mensajes.\n";
