<?php
require_once __DIR__.'/../config/app.php';
function preparar_gestion_avanzada(PDO $db): void {
    static $listas; $listas??=new SplObjectStorage();if(isset($listas[$db]))return;
    $id=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT AUTO_INCREMENT PRIMARY KEY';
    $db->exec("CREATE TABLE IF NOT EXISTS movimientos_stock (id $id,id_producto INT NOT NULL,tipo VARCHAR(30) NOT NULL,anterior INT NOT NULL,cantidad INT NOT NULL,nuevo INT NOT NULL,referencia VARCHAR(80) NOT NULL,usuario INT NOT NULL,motivo VARCHAR(500) NOT NULL,fecha VARCHAR(19) NOT NULL)");
    $db->exec('CREATE TABLE IF NOT EXISTS proveedores (id VARCHAR(40) PRIMARY KEY,nombre VARCHAR(150) NOT NULL,contacto VARCHAR(190) NOT NULL,plazo INT NOT NULL,activo INT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS compras_reposicion (id VARCHAR(40) PRIMARY KEY,proveedor VARCHAR(40) NOT NULL,id_producto INT NOT NULL,cantidad INT NOT NULL,recibida INT NOT NULL,costo BIGINT NOT NULL,plazo INT NOT NULL,estado VARCHAR(20) NOT NULL,creada VARCHAR(19) NOT NULL,usuario INT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS recepciones_compra (id VARCHAR(40) PRIMARY KEY,compra VARCHAR(40) NOT NULL,cantidad INT NOT NULL,fecha VARCHAR(19) NOT NULL,usuario INT NOT NULL,referencia VARCHAR(150) NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS devoluciones (id VARCHAR(40) PRIMARY KEY,id_pedido INT NOT NULL,id_producto INT NOT NULL,cantidad INT NOT NULL,maximo BIGINT NOT NULL,estado VARCHAR(20) NOT NULL,reintegrada INT NOT NULL,motivo VARCHAR(500) NOT NULL,creada VARCHAR(19) NOT NULL,usuario INT NOT NULL,recibida VARCHAR(19) NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS reembolsos (devolucion VARCHAR(40) PRIMARY KEY,importe BIGINT NOT NULL,referencia VARCHAR(150) NOT NULL,firma VARCHAR(64) UNIQUE NOT NULL,fecha VARCHAR(19) NOT NULL,usuario INT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS gestion_config (clave VARCHAR(60) PRIMARY KEY,valor VARCHAR(100) NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS gestion_eventos (id VARCHAR(40) PRIMARY KEY,tipo VARCHAR(30) NOT NULL,referencia VARCHAR(40) NOT NULL,motivo VARCHAR(500) NOT NULL,usuario INT NOT NULL,fecha VARCHAR(19) NOT NULL)');
    $listas[$db]=true;
}
function bloqueo_gestion(PDO $db): string {return $db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
function condiciones_reposicion(PDO $db): array {
    $condiciones=[];
    foreach($db->query("SELECT c.id_producto,v.nombre,v.plazo FROM compras_reposicion c JOIN proveedores v ON v.id=c.proveedor WHERE v.activo=1 AND c.estado<>'Cancelada' ORDER BY c.creada DESC,c.id DESC")->fetchAll(PDO::FETCH_ASSOC) as $c)if(!isset($condiciones[$c['id_producto']]))$condiciones[$c['id_producto']]=['proveedor'=>$c['nombre'],'plazo'=>(int)$c['plazo'],'pendientes'=>0];
    foreach($db->query("SELECT id_producto,SUM(cantidad-recibida) pendientes FROM compras_reposicion WHERE estado IN ('Solicitada','Parcial') GROUP BY id_producto")->fetchAll(PDO::FETCH_ASSOC) as $c){$condiciones[$c['id_producto']]??=['proveedor'=>null,'plazo'=>null,'pendientes'=>0];$condiciones[$c['id_producto']]['pendientes']=(int)$c['pendientes'];}
    return $condiciones;
}
function entero_gestion($valor,int $min=1,int $max=1000000): int {
    if(!is_scalar($valor)||!preg_match('/^\d+$/D',(string)$valor)||$valor<$min||$valor>$max)throw new InvalidArgumentException('Cantidad o plazo no válido.');return (int)$valor;
}
function texto_gestion($valor,int $min=1,int $max=500): string {
    $texto=is_string($valor)?trim($valor):'';if(mb_strlen($texto)<$min||mb_strlen($texto)>$max)throw new InvalidArgumentException('Completa el texto requerido ('.$min.' a '.$max.' caracteres).');return $texto;
}
function movimiento_gestion(PDO $db,array $p,?int $antes,string $tipo,string $referencia='',string $motivo='Cambio observado en el inventario',?int $usuario=null): void {
    // Las tablas se preparan antes de la transacción que modifica stock.
    $q=$db->prepare('SELECT nuevo FROM movimientos_stock WHERE id_producto=? ORDER BY id DESC LIMIT 1'.bloqueo_gestion($db));$q->execute([$p['id_producto']]);$ultimo=$q->fetchColumn();
    $insert=$db->prepare('INSERT INTO movimientos_stock(id_producto,tipo,anterior,cantidad,nuevo,referencia,usuario,motivo,fecha) VALUES(?,?,?,?,?,?,?,?,?)');$fecha=date('Y-m-d H:i:s');$actor=$usuario??(int)($_SESSION['id_usuario']??0);
    if($ultimo===false){$ultimo=$antes??(int)$p['stock'];$insert->execute([$p['id_producto'],'apertura',0,(int)$ultimo,(int)$ultimo,'',0,'Saldo de apertura observado; no reconstruye movimientos anteriores',$fecha]);}
    if($antes!==null&&(int)$ultimo!==$antes){$insert->execute([$p['id_producto'],'conciliacion',(int)$ultimo,$antes-(int)$ultimo,$antes,'',0,'Diferencia detectada fuera de los movimientos registrados',$fecha]);$ultimo=$antes;}
    $nuevo=(int)$p['stock'];if($nuevo===(int)$ultimo)return;
    $insert->execute([$p['id_producto'],$tipo,(int)$ultimo,$nuevo-(int)$ultimo,$nuevo,mb_substr($referencia,0,80),$actor,mb_substr($motivo,0,500),$fecha]);
}
function proveedor_gestion(PDO $db,array $d): string {
    $nombre=texto_gestion($d['nombre']??'',2,150);$contacto=texto_gestion($d['contacto']??'',0,190);$plazo=entero_gestion($d['plazo']??'',0,365);
    $id=(string)($d['id']??'');if($id!==''){$q=$db->prepare('UPDATE proveedores SET nombre=?,contacto=?,plazo=?,activo=? WHERE id=?');$q->execute([$nombre,$contacto,$plazo,isset($d['activo'])?1:0,$id]);return $id;}
    require_once __DIR__.'/alertas_operativas.php';
    $id=bin2hex(random_bytes(16));$db->beginTransaction();
    try{
        $db->prepare('INSERT INTO proveedores VALUES(?,?,?,?,1)')->execute([$id,$nombre,$contacto,$plazo]);
        registrar_alerta_operativa($db,'proveedor-alta-'.$id,'proveedor_alta',0,"🏢 TechStore · Nuevo proveedor\n".$nombre."\nPlazo de entrega: ".$plazo." días.\nRegistrado: ".date('d/m/Y H:i')." (Perú).\nDisponible para órdenes internas de reposición; no se ha realizado ninguna compra.");
        $db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function crear_reposicion(PDO $db,array $d,int $usuario): string {
    $cantidad=entero_gestion($d['cantidad']??'');$costo=precio_centimos((string)($d['costo']??''));if($costo<=0)throw new InvalidArgumentException('El costo debe ser mayor que cero.');
    $db->beginTransaction();try{
        $q=$db->prepare('SELECT plazo FROM proveedores WHERE id=? AND activo=1'.bloqueo_gestion($db));$q->execute([(string)($d['proveedor']??'')]);$plazo=$q->fetchColumn();if($plazo===false)throw new InvalidArgumentException('Selecciona un proveedor activo.');
        $q=$db->prepare('SELECT id_producto FROM productos WHERE id_producto=? AND estado=1'.bloqueo_gestion($db));$q->execute([entero_gestion($d['producto']??'',1,2147483647)]);$producto=$q->fetchColumn();if(!$producto)throw new InvalidArgumentException('Selecciona un producto activo.');
        $id=bin2hex(random_bytes(16));$db->prepare("INSERT INTO compras_reposicion VALUES(?,?,?,?,0,?,?,'Solicitada',?,?)")->execute([$id,$d['proveedor'],$producto,$cantidad,$costo,$plazo,date('Y-m-d H:i:s'),$usuario]);$db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function recibir_reposicion(PDO $db,string $id,int $cantidad,string $referencia,int $usuario): void {
    $cantidad=entero_gestion($cantidad);$referencia=texto_gestion($referencia,3,150);$db->beginTransaction();try{
        $q=$db->prepare('SELECT * FROM compras_reposicion WHERE id=?'.bloqueo_gestion($db));$q->execute([$id]);$c=$q->fetch(PDO::FETCH_ASSOC);if(!$c||!in_array($c['estado'],['Solicitada','Parcial'],true)||$cantidad>$c['cantidad']-$c['recibida'])throw new InvalidArgumentException('La cantidad supera lo pendiente o la compra está cerrada.');
        $q=$db->prepare('SELECT COUNT(*) FROM recepciones_compra WHERE compra=? AND referencia=?');$q->execute([$id,$referencia]);if((int)$q->fetchColumn())throw new InvalidArgumentException('Esa referencia de recepción ya fue registrada para esta compra.');
        $q=$db->prepare('SELECT id_producto,codigo,nombre,stock,estado FROM productos WHERE id_producto=?'.bloqueo_gestion($db));$q->execute([$c['id_producto']]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p||$p['stock']+$cantidad>2147483647)throw new InvalidArgumentException('No se puede recibir ese producto.');
        $antes=(int)$p['stock'];$p['stock']=$antes+$cantidad;$db->prepare('UPDATE productos SET stock=? WHERE id_producto=?')->execute([$p['stock'],$p['id_producto']]);
        registrar_transicion_stock($db,$p,$antes,'reposicion',$id,'Recepción: '.$referencia,$usuario);
        $recibida=(int)$c['recibida']+$cantidad;$db->prepare('UPDATE compras_reposicion SET recibida=?,estado=? WHERE id=?')->execute([$recibida,$recibida===(int)$c['cantidad']?'Recibida':'Parcial',$id]);
        $db->prepare('INSERT INTO recepciones_compra VALUES(?,?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),$id,$cantidad,date('Y-m-d H:i:s'),$usuario,$referencia]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function cancelar_reposicion(PDO $db,string $id,string $motivo,int $usuario): void {
    $motivo=texto_gestion($motivo,5);$db->beginTransaction();try{
        $q=$db->prepare("UPDATE compras_reposicion SET estado='Cancelada' WHERE id=? AND estado IN ('Solicitada','Parcial')");$q->execute([$id]);if($q->rowCount()!==1)throw new InvalidArgumentException('Compra inexistente o ya cerrada.');
        $db->prepare('INSERT INTO gestion_eventos VALUES(?,?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),'compra_cancelada',$id,$motivo,$usuario,date('Y-m-d H:i:s')]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function ajustar_stock_gestion(PDO $db,int $producto,int $stock,string $motivo,int $usuario): void {
    entero_gestion($stock,0,2147483647);$motivo=texto_gestion($motivo,5,500);$db->beginTransaction();try{
        $q=$db->prepare('SELECT id_producto,codigo,nombre,stock,estado FROM productos WHERE id_producto=?'.bloqueo_gestion($db));$q->execute([$producto]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p)throw new InvalidArgumentException('Producto inexistente.');
        $antes=(int)$p['stock'];$p['stock']=$stock;$db->prepare('UPDATE productos SET stock=? WHERE id_producto=?')->execute([$stock,$producto]);registrar_transicion_stock($db,$p,$antes,'ajuste','',$motivo,$usuario);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function solicitar_devolucion(PDO $db,array $d,int $usuario): string {
    $cantidad=entero_gestion($d['cantidad']??'');$motivo=texto_gestion($d['motivo']??'',5);$pedido=entero_gestion($d['pedido']??'',1,2147483647);$producto=entero_gestion($d['producto']??'',1,2147483647);$db->beginTransaction();try{
        $q=$db->prepare('SELECT estado FROM pedidos WHERE id_pedido=?'.bloqueo_gestion($db));$q->execute([$pedido]);if($q->fetchColumn()!=='Entregado')throw new InvalidArgumentException('Solo se admiten devoluciones de pedidos entregados.');
        $q=$db->prepare('SELECT cantidad,precio_unitario FROM detalle_pedidos WHERE id_pedido=? AND id_producto=?');$q->execute([$pedido,$producto]);$filas=$q->fetchAll(PDO::FETCH_ASSOC);$vendidas=0;$precios=[];foreach($filas as $f){$vendidas+=(int)$f['cantidad'];$precios[]=precio_centimos($f['precio_unitario']);}
        $q=$db->prepare("SELECT COALESCE(SUM(cantidad),0) FROM devoluciones WHERE id_pedido=? AND id_producto=? AND estado<>'Rechazada'");$q->execute([$pedido,$producto]);if(!$vendidas||$cantidad>$vendidas-(int)$q->fetchColumn())throw new InvalidArgumentException('La cantidad supera las unidades vendidas disponibles para devolución.');
        $id=bin2hex(random_bytes(16));$db->prepare("INSERT INTO devoluciones VALUES(?,?,?,?,?,'Solicitada',0,?,?,?,NULL)")->execute([$id,$pedido,$producto,$cantidad,min($precios)*$cantidad,$motivo,date('Y-m-d H:i:s'),$usuario]);$db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function resolver_devolucion(PDO $db,string $id,bool $aceptar,bool $reintegrar,int $usuario,string $nota='Decisión de recepción registrada'): void {
    $nota=texto_gestion($nota,5,500);
    $db->beginTransaction();try{
        $q=$db->prepare('SELECT * FROM devoluciones WHERE id=?'.bloqueo_gestion($db));$q->execute([$id]);$d=$q->fetch(PDO::FETCH_ASSOC);if(!$d||$d['estado']!=='Solicitada')throw new InvalidArgumentException('Esta devolución ya fue procesada.');
        if($aceptar&&$reintegrar){$q=$db->prepare('SELECT id_producto,codigo,nombre,stock,estado FROM productos WHERE id_producto=?'.bloqueo_gestion($db));$q->execute([$d['id_producto']]);$p=$q->fetch(PDO::FETCH_ASSOC);if(!$p||$p['stock']+$d['cantidad']>2147483647)throw new InvalidArgumentException('No se puede reintegrar stock.');$antes=(int)$p['stock'];$p['stock']=$antes+(int)$d['cantidad'];$db->prepare('UPDATE productos SET stock=? WHERE id_producto=?')->execute([$p['stock'],$p['id_producto']]);registrar_transicion_stock($db,$p,$antes,'devolucion',$id,'Devolución recibida y apta para reintegrar',$usuario);}
        $db->prepare('UPDATE devoluciones SET estado=?,reintegrada=?,recibida=? WHERE id=?')->execute([$aceptar?'Recibida':'Rechazada',$aceptar&&$reintegrar?1:0,date('Y-m-d H:i:s'),$id]);
        $db->prepare('INSERT INTO gestion_eventos VALUES(?,?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),$aceptar?'devolucion_recibida':'devolucion_rechazada',$id,$nota,$usuario,date('Y-m-d H:i:s')]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function registrar_reembolso(PDO $db,string $id,int $importe,string $referencia,int $usuario): void {
    $referencia=texto_gestion($referencia,5,150);if($importe<=0)throw new InvalidArgumentException('Importe no válido.');$db->beginTransaction();try{
        $q=$db->prepare('SELECT estado,maximo FROM devoluciones WHERE id=?'.bloqueo_gestion($db));$q->execute([$id]);$d=$q->fetch(PDO::FETCH_ASSOC);if(!$d||$d['estado']!=='Recibida'||$importe>(int)$d['maximo'])throw new InvalidArgumentException('Primero recibe la devolución y respeta su importe máximo.');
        $db->prepare('INSERT INTO reembolsos VALUES(?,?,?,?,?,?)')->execute([$id,$importe,$referencia,hash('sha256',mb_strtolower($referencia)),date('Y-m-d H:i:s'),$usuario]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function configuracion_gestion(PDO $db,string $clave,?string $valor=null): ?string {
    if($valor!==null){$sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO gestion_config VALUES(?,?) ON CONFLICT(clave) DO UPDATE SET valor=excluded.valor':'INSERT INTO gestion_config VALUES(?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)';$db->prepare($sql)->execute([$clave,$valor]);return $valor;}
    $q=$db->prepare('SELECT valor FROM gestion_config WHERE clave=?');$q->execute([$clave]);$v=$q->fetchColumn();return $v===false?null:(string)$v;
}
function encolar_resumen_diario(PDO $db,?DateTimeImmutable $reloj=null): bool {
    preparar_gestion_avanzada($db);$ahora=$reloj??new DateTimeImmutable();$hoy=$ahora->format('Y-m-d');if(configuracion_gestion($db,'resumen_activo')!=='1'||$hoy<(configuracion_gestion($db,'resumen_desde')??'9999-12-31')||(int)$ahora->format('G')<(int)(configuracion_gestion($db,'resumen_hora')??9))return false;
    $desde=$ahora->modify('-1 day')->format('Y-m-d');$q=$db->prepare("SELECT COUNT(*) cantidad,COALESCE(SUM(CASE WHEN estado IN ('Confirmado','En preparación','Enviado','Entregado') THEN total ELSE 0 END),0) ventas FROM pedidos WHERE fecha_pedido>=? AND fecha_pedido<?");$q->execute([$desde,$hoy]);$dia=$q->fetch(PDO::FETCH_ASSOC);
    $stock=$db->query('SELECT COUNT(*) FROM productos WHERE estado=1 AND stock<=5')->fetchColumn();$pendientes=$db->query("SELECT COUNT(*) FROM pedidos WHERE estado='Pendiente'")->fetchColumn();$q=$db->prepare('SELECT COALESCE(SUM(importe),0) FROM reembolsos WHERE fecha>=? AND fecha<?');$q->execute([$desde,$hoy]);$reembolsos=(int)$q->fetchColumn();
    $reporte='';if(function_exists('leer_estado_agentes')){$r=leer_estado_agentes($db,'ultima');if(isset($r['auditor'])&&substr($r['fecha']??'',0,10)===$hoy){if(!$r['auditor']['autoriza_envio'])return false;$reporte="\n\n".($r['reporte']??'');}}
    registrar_alerta_operativa($db,'resumen-dia-'.$desde,'resumen_diario',0,"📊 TechStore · Resumen del ".$desde."\nPedidos creados: ".$dia['cantidad']."\nImporte confirmado (incluye envío): S/ ".number_format((float)$dia['ventas'],2)."\nReembolsos registrados: S/ ".number_format($reembolsos/100,2)."\nEstado al revisar: ".$stock.' productos con stock bajo y '.$pendientes." pedidos pendientes.\nImportes confirmados no acreditan cobros; reembolsos son registros de operaciones externas.".$reporte);return true;
}
