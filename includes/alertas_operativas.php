<?php
require_once __DIR__.'/telegram.php';
function preparar_alertas_operativas(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS alertas_operativas (clave VARCHAR(190) PRIMARY KEY, tipo VARCHAR(30) NOT NULL, entidad INT NOT NULL, texto TEXT NOT NULL, estado VARCHAR(20) NOT NULL DEFAULT 'Pendiente', creada VARCHAR(19) NOT NULL, intento VARCHAR(19) NULL)");
}
function registrar_alerta_operativa(PDO $db,string $clave,string $tipo,int $entidad,string $texto): void {
    try{$q=$db->prepare('INSERT INTO alertas_operativas(clave,tipo,entidad,texto,creada) VALUES(?,?,?,?,?)');$q->execute([$clave,$tipo,$entidad,$texto,date('Y-m-d H:i:s')]);}
    catch(PDOException $e){if(!in_array((string)$e->getCode(),['23000','23505'],true))throw $e;}
}
function registrar_stock_venta(PDO $db,int $pedido,array $productos): void {
    foreach($productos as $p){$stock=(int)$p['stock'];if($stock>5)continue;
        registrar_alerta_operativa($db,'venta-'.$pedido.'-'.$p['id_producto'],'stock_venta',(int)$p['id_producto'],"🛒 TechStore · Stock bajo después de una compra\nPedido #$pedido\n".mb_substr($p['nombre'],0,120).' ['.$p['codigo']."]\nStock restante: $stock unidades.\n".($stock===0?'Producto agotado.':'Revisar reposición.')."\nEl pedido fue registrado; esto no acredita pago recibido.");
    }
}
function detectar_pedidos_demorados(PDO $db,int $horas,?DateTimeImmutable $ahora=null,?int $minutos=null,?string $desde=null): int {
    if($horas<1||$horas>8760)throw new RuntimeException('Umbral de demora inválido.');
    if($minutos!==null&&($minutos<1||$minutos>525600))throw new RuntimeException('Umbral de minutos inválido.');
    $plazo=$minutos!==null?"$minutos minutos":"$horas horas";
    $limite=($ahora??new DateTimeImmutable())->modify($minutos!==null?'-'.$minutos.' minutes':'-'.$horas.' hours')->format('Y-m-d H:i:s');
    $q=$db->prepare("SELECT p.id_pedido,p.fecha_pedido FROM pedidos p WHERE p.estado='Pendiente' AND p.fecha_pedido<=? AND p.fecha_pedido>=? AND NOT EXISTS (SELECT 1 FROM alertas_operativas a WHERE a.tipo='pedido_demorado' AND a.entidad=p.id_pedido) ORDER BY p.fecha_pedido LIMIT 50");$q->execute([$limite,$desde??'1970-01-01 00:00:00']);$total=0;
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $p){registrar_alerta_operativa($db,'demora-'.$p['id_pedido'],'pedido_demorado',(int)$p['id_pedido'],"⏳ TechStore · Pedido pendiente de revisión\nPedido #".$p['id_pedido']."\nCreado: ".$p['fecha_pedido']." (hora Perú)\nContinúa Pendiente después de $plazo. Revisa el pedido en Administración. No se cambió su estado.");$total++;}
    return $total;
}
function enviar_alertas_operativas(PDO $db,?callable $api=null,?array $config=null,int $maximo=5): array {
    $config=$config??configuracion_telegram();$api=$api??'telegram_api';
    if($config['token']===''||$config['chat_id']==='')return ['enviadas'=>0,'estado'=>'Sin configurar'];
    $limite=date('Y-m-d H:i:s',time()-300);
    $maximo=max(1,min(5,$maximo));
    $q=$db->prepare("SELECT * FROM alertas_operativas WHERE estado='Pendiente' AND (intento IS NULL OR intento<=?) ORDER BY creada,clave LIMIT $maximo");$q->execute([$limite]);$enviadas=0;
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $a){
        // Un pedido resuelto antes del envío no debe generar una alerta obsoleta.
        if($a['tipo']==='pedido_demorado'){$pedido=$db->prepare('SELECT estado FROM pedidos WHERE id_pedido=?');$pedido->execute([$a['entidad']]);if($pedido->fetchColumn()!=='Pendiente'){$cancelar=$db->prepare("UPDATE alertas_operativas SET estado='Descartada' WHERE clave=? AND estado='Pendiente'");$cancelar->execute([$a['clave']]);continue;}}
        $tomar=$db->prepare("UPDATE alertas_operativas SET estado='En curso',intento=? WHERE clave=? AND estado='Pendiente'");$tomar->execute([date('Y-m-d H:i:s'),$a['clave']]);if($tomar->rowCount()!==1)continue;
        try{$api($config,'sendMessage',['chat_id'=>$config['chat_id'],'text'=>$a['texto']]);}
        catch(Throwable $e){$estado=$e->getCode()===900?'Incierto':'Pendiente';$fallo=$db->prepare('UPDATE alertas_operativas SET estado=? WHERE clave=?');$fallo->execute([$estado,$a['clave']]);return ['enviadas'=>$enviadas,'estado'=>$estado==='Incierto'?'Revisar entrega':'Error'];}
        $fin=$db->prepare("UPDATE alertas_operativas SET estado='Enviado' WHERE clave=?");$fin->execute([$a['clave']]);$enviadas++;
    }
    return ['enviadas'=>$enviadas,'estado'=>$enviadas?'Enviado':'Sin novedades'];
}
function revisar_alertas_operativas(PDO $db): array {
    preparar_alertas_operativas($db);
    $db->exec('CREATE TABLE IF NOT EXISTS alertas_configuracion (clave VARCHAR(100) PRIMARY KEY, valor VARCHAR(190) NOT NULL)');
    try{$inicio=$db->prepare('INSERT INTO alertas_configuracion(clave,valor) VALUES(?,?)');$inicio->execute(['demoras_desde',date('Y-m-d H:i:s')]);}catch(PDOException $e){if(!in_array((string)$e->getCode(),['23000','23505'],true))throw $e;}
    $desde=(string)$db->query("SELECT valor FROM alertas_configuracion WHERE clave='demoras_desde'")->fetchColumn();
    $minutos=getenv('PEDIDOS_DEMORA_MINUTOS');
    $horas=filter_var(getenv('PEDIDOS_DEMORA_HORAS'),FILTER_VALIDATE_INT);
    if($minutos===false&&$horas!==false&&$horas>0)detectar_pedidos_demorados($db,$horas,null,null,$desde);
    else { $minutos=$minutos===false?5:filter_var($minutos,FILTER_VALIDATE_INT);if($minutos===false)throw new RuntimeException('PEDIDOS_DEMORA_MINUTOS debe ser un entero.');detectar_pedidos_demorados($db,1,null,$minutos,$desde); }
    return enviar_alertas_operativas($db);
}
