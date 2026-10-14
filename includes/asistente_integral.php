<?php
require_once __DIR__.'/consultas_asistente.php';

function tipo_consulta_integral(string $pregunta): ?string {
    $t=normalizar_consulta_admin($pregunta);
    if(preg_match('/\b(borra|borrar|elimina|eliminar|modifica|modificar|actualiza|actualizar|cambia|cambiar|entrena|entrenar|cancela|cancelar)\b/u',$t))return null;
    if(preg_match('/venta.*(?:categoria|marca)|(?:categoria|marca).*vendid/u',$t))return 'ventas_agrupadas';
    if(preg_match('/(?:por|cada)\s+categoria/u',$t))return 'categorias_integrales';
    if(preg_match('/(?:por|cada)\s+marca/u',$t))return 'marcas_integrales';
    if(preg_match('/(?:analitica|proyeccion|segmentacion|segmentos|agrupacion|clusters|k-means|modelos de productos|modelos de clientes)|prediccion.*(?:producto|cliente)/u',$t))return 'informes_integrales';
    if(str_contains($t,'categoria')&&!preg_match('/producto|stock|inventario/u',$t))return 'categorias_integrales';
    if(str_contains($t,'marca')&&!preg_match('/producto|stock|inventario/u',$t))return 'marcas_integrales';
    if(preg_match('/\bpedido\s*(?:#|n[.º°o]*\s*)?\s*\d+\b/u',$t))return 'pedido_detalle';
    if(preg_match('/metodos? de pago|pagos por metodo|pago.*(?:yape|plin|tarjeta|contraentrega|transferencia)/u',$t))return 'pagos_integrales';
    if(preg_match('/\bpedidos\b/u',$t)&&preg_match('/entregad|cancelad|confirmad|enviad|preparacion/u',$t))return 'pedidos_filtrados';
    if(preg_match('/\bcliente\s*(?:#|n[.º°o]*\s*)?\s*\d+\b/u',$t)||preg_match('/(?:busca|buscar|historial de|correo de|telefono de)\s+(?:el\s+)?cliente\b/u',$t))return 'cliente_detalle';
    if(preg_match('/lista.*clientes|clientes.*(?:recientes|registrados|sin compras)|ultimos clientes/u',$t))return 'clientes_integrales';
    if(preg_match('/\b(productos|catalogo)\b/u',$t)&&!preg_match('/vendid|venta|stock|inventario|predic|ml|porcentaje|agotad|existencias/u',$t)&&termino_producto_admin($pregunta)==='')return 'catalogo_integral';
    if(preg_match('/\bproducto\s*(?:#|n[.º°o]*\s*)?\s*\d+\b/u',$t))return 'producto_detalle';
    return null;
}

function respuesta_integral(PDO $db,string $pregunta,?string $directorio=null): ?array {
    $tipo=tipo_consulta_integral($pregunta);if(!$tipo)return null;
    $t=normalizar_consulta_admin($pregunta);
    $r=['texto'=>'','enlaces'=>[],'fuente'=>'MySQL / MariaDB · consulta actual','fecha'=>date('d/m/Y H:i'),'modo'=>'local'];
    $link=static fn($texto,$url)=>['texto'=>$texto,'url'=>ruta($url)];
    if(in_array($tipo,['categorias_integrales','marcas_integrales'],true)){
        $tabla=$tipo==='categorias_integrales'?'categorias':'marcas';$id=$tabla==='categorias'?'id_categoria':'id_marca';
        $total=(int)$db->query('SELECT COUNT(*) FROM '.$tabla)->fetchColumn();
        $filas=$db->query('SELECT a.'.$id.' id,a.nombre,a.estado,COUNT(p.id_producto) productos,COALESCE(SUM(CASE WHEN p.estado=1 THEN 1 ELSE 0 END),0) activos,COALESCE(SUM(CASE WHEN p.estado=1 THEN p.stock ELSE 0 END),0) unidades FROM '.$tabla.' a LEFT JOIN productos p ON p.'.$id.'=a.'.$id.' GROUP BY a.'.$id.',a.nombre,a.estado ORDER BY a.nombre LIMIT 30')->fetchAll();
        $r['texto']='Hay '.$total.' '.($tabla==='categorias'?'categorías':'marcas').' registradas. Muestro '.count($filas).' de '.$total.':';
        foreach($filas as $f)$r['texto'].="\n• ".$f['nombre'].' · '.((int)$f['estado']===1?'Activa':'Inactiva').' · '.$f['productos'].' productos ('.$f['activos'].' activos) · '.$f['unidades'].' unidades en productos activos';
        $r['texto'].="\nLos totales incluyen líneas activas e inactivas. Las existencias corresponden solo a productos activos.";
        $r['enlaces'][]=$link('Ver todas las '.$tabla,'admin/'.$tabla.'/listar.php');
    }elseif($tipo==='catalogo_integral'){
        $f=$db->query('SELECT COUNT(*) total,COALESCE(SUM(CASE WHEN estado=1 THEN 1 ELSE 0 END),0) activos,COALESCE(SUM(CASE WHEN estado=1 THEN stock ELSE 0 END),0) unidades FROM productos')->fetch();
        $r['texto']='Catálogo: '.$f['total'].' productos, '.$f['activos'].' activos y '.((int)$f['total']-(int)$f['activos']).' inactivos. Existencias de productos activos: '.$f['unidades'].' unidades.';
        $estado=str_contains($t,'inactiv')?'0':'1';
        $filas=$db->query('SELECT id_producto,codigo,nombre,precio,stock FROM productos WHERE estado='.$estado.' ORDER BY id_producto DESC LIMIT 10')->fetchAll();
        $r['texto'].="\n\nÚltimos ".count($filas).' productos '.($estado==='1'?'activos':'inactivos').':';
        foreach($filas as $p)$r['texto'].="\n• #".$p['id_producto'].' · '.$p['nombre'].' · '.$p['codigo'].' · '.dinero($p['precio']).' · '.$p['stock'].' unidades';
        $r['enlaces'][]=$link('Ver catálogo completo','admin/productos/listar.php?estado='.$estado);
    }elseif($tipo==='producto_detalle'){
        preg_match('/\bproducto\s*(?:#|n[.º°o]*\s*)?\s*(\d+)\b/u',$t,$m);$id=(int)$m[1];
        $q=$db->prepare('SELECT p.id_producto,p.codigo,p.nombre,p.descripcion,p.precio,p.stock,p.estado,c.nombre categoria,m.nombre marca FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca WHERE p.id_producto=?');$q->execute([$id]);$p=$q->fetch();
        $r['texto']=$p?'Producto #'.$id.': '.$p['nombre']."\nCódigo: ".$p['codigo']."\nCategoría: ".$p['categoria']." · Marca: ".$p['marca']."\nPrecio: ".dinero($p['precio']).' · Stock: '.$p['stock']."\nEstado: ".($p['estado']?'Activo':'Inactivo')."\nDescripción: ".mb_substr($p['descripcion']??'',0,1800):'No existe el producto #'.$id.'.';
        if($p)$r['enlaces'][]=$link('Abrir ficha del producto','admin/productos/editar.php?id='.$id);
    }elseif($tipo==='pedido_detalle'){
        preg_match('/\bpedido\s*(?:#|n[.º°o]*\s*)?\s*(\d+)\b/u',$t,$m);$id=(int)$m[1];
        $q=$db->prepare('SELECT id_pedido,id_cliente,estado,total,fecha_pedido,metodo_pago FROM pedidos WHERE id_pedido=?');$q->execute([$id]);$p=$q->fetch();
        $r['texto']=$p?'Pedido #'.$id.' · '.$p['estado']."\nFecha: ".$p['fecha_pedido']."\nCliente #".$p['id_cliente']."\nTotal: ".dinero($p['total'])."\nMétodo de pago: ".$p['metodo_pago']:'No existe el pedido #'.$id.'.';
        if($p){$q=$db->prepare('SELECT pr.nombre,d.cantidad,d.precio_unitario,d.subtotal FROM detalle_pedidos d JOIN productos pr ON pr.id_producto=d.id_producto WHERE d.id_pedido=? ORDER BY d.id_producto LIMIT 20');$q->execute([$id]);$lineas=$q->fetchAll();foreach($lineas as $f)$r['texto'].="\n• ".$f['nombre'].' · '.$f['cantidad'].' × '.dinero($f['precio_unitario']).' = '.dinero($f['subtotal']);$r['texto'].=$lineas?"\nSe muestran hasta 20 líneas.":"\nEste pedido no tiene líneas de productos registradas.";$r['texto'].=' El método registrado no acredita que el pago esté recibido.';$r['enlaces'][]=$link('Ver pedido completo','admin/pedidos/detalle.php?id='.$id);}
        $r['privado']=true;
    }elseif($tipo==='cliente_detalle'||$tipo==='clientes_integrales'){
        $params=[];$where='';
        if($tipo==='cliente_detalle'){
            if(preg_match('/\bcliente\s*(?:#|n[.º°o]*\s*)?\s*(\d+)\b/u',$t,$m)){$where=' WHERE c.id_cliente=?';$params[]=(int)$m[1];}
            elseif(preg_match('/\bcliente\s+(.+)$/u',$t,$m)){
                $termino=trim($m[1]," ?¿.!\t\n");$partes=preg_split('/\s+/u',$termino);$cond=[];
                foreach(array_slice($partes,0,5) as $parte){$cond[]="(LOWER(c.nombre) LIKE ? ESCAPE '!' OR LOWER(c.apellido) LIKE ? ESCAPE '!')";$like='%'.strtr($parte,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$params[]=$like;$params[]=$like;}$where=' WHERE '.implode(' AND ',$cond);
            }
        }
        if(str_contains($t,'sin compras'))$where.=($where?' AND ':' WHERE ')."NOT EXISTS(SELECT 1 FROM pedidos pc WHERE pc.id_cliente=c.id_cliente AND pc.estado IN ('Confirmado','En preparación','Enviado','Entregado'))";
        $q=$db->prepare('SELECT COUNT(*) FROM clientes c'.$where);$q->execute($params);$total=(int)$q->fetchColumn();
        $q=$db->prepare("SELECT c.id_cliente,c.nombre,c.apellido,c.correo,c.telefono,(SELECT COUNT(*) FROM pedidos p WHERE p.id_cliente=c.id_cliente) pedidos,(SELECT COALESCE(SUM(p.total),0) FROM pedidos p WHERE p.id_cliente=c.id_cliente AND p.estado IN ('Confirmado','En preparación','Enviado','Entregado')) importe FROM clientes c".$where.' ORDER BY c.id_cliente DESC LIMIT 10');$q->execute($params);
        $filas=$q->fetchAll();$r['texto']='Clientes encontrados: '.$total.'. Muestro '.count($filas).' de '.$total.':';
        foreach($filas as $f){$r['texto'].="\n• Cliente #".$f['id_cliente'].' · '.trim($f['nombre'].' '.$f['apellido']).' · '.$f['pedidos'].' pedidos totales · '.dinero($f['importe']).' en pedidos confirmados';if(preg_match('/correo|telefono|contacto/u',$t))$r['texto'].=' · Correo: '.$f['correo'].' · Teléfono: '.$f['telefono'];$r['enlaces'][]=$link('Ver cliente #'.$f['id_cliente'],'admin/clientes/detalle.php?id='.(int)$f['id_cliente']);}
        $r['texto'].="\nLos importes excluyen pendientes y cancelados, incluyen envío y no equivalen a pagos recibidos. Las fichas incluyen el historial completo.";
        $r['privado']=true;
    }elseif($tipo==='pedidos_filtrados'){
        $estado=match(true){str_contains($t,'entregad')=>'Entregado',str_contains($t,'cancelad')=>'Cancelado',str_contains($t,'preparacion')=>'En preparación',str_contains($t,'enviad')=>'Enviado',default=>'Confirmado'};
        $periodo=periodo_ventas_admin($pregunta);$explicito=preg_match('/\bhoy\b|\bayer\b|este mes|esta semana|\b(?:7|30)\s*dias\b/u',$t);
        $filtro=$explicito?' AND fecha_pedido>=? AND fecha_pedido<?':'';$params=$explicito?[$estado,$periodo['inicio'],$periodo['fin']]:[$estado];
        $q=$db->prepare('SELECT COUNT(*) FROM pedidos WHERE estado=?'.$filtro);$q->execute($params);$total=(int)$q->fetchColumn();
        $q=$db->prepare('SELECT id_pedido,fecha_pedido,total FROM pedidos WHERE estado=?'.$filtro.' ORDER BY fecha_pedido DESC,id_pedido DESC LIMIT 10');$q->execute($params);
        $r['texto']='Hay '.$total.' pedidos con estado «'.$estado.'» · '.($explicito?$periodo['etiqueta']:'todo el historial').'. Últimos 10 como máximo:';
        foreach($q->fetchAll() as $p)$r['texto'].="\n• #".$p['id_pedido'].' · '.$p['fecha_pedido'].' · '.dinero($p['total']);
        $r['enlaces'][]=$link('Ver todos los pedidos filtrados','admin/pedidos/listar.php?estado='.rawurlencode($estado));
    }elseif($tipo==='pagos_integrales'){
        $r['texto']='Métodos de pago registrados en todos los pedidos (incluye todos los estados):';
        foreach($db->query('SELECT metodo_pago,COUNT(*) cantidad,COALESCE(SUM(total),0) importe FROM pedidos GROUP BY metodo_pago ORDER BY cantidad DESC')->fetchAll() as $f)$r['texto'].="\n• ".$f['metodo_pago'].' · '.$f['cantidad'].' pedidos · '.dinero($f['importe']);
        $r['texto'].="\nEstos importes describen pedidos, no pagos conciliados o recibidos.";$r['enlaces'][]=$link('Revisar pedidos','admin/pedidos/listar.php');
    }elseif($tipo==='ventas_agrupadas'){
        $marca=str_contains($t,'marca');$tabla=$marca?'marcas':'categorias';$id=$marca?'id_marca':'id_categoria';$periodo=periodo_ventas_admin($pregunta);
        $q=$db->prepare("SELECT a.nombre,SUM(d.cantidad) unidades,SUM(d.subtotal) importe FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido=d.id_pedido JOIN productos pr ON pr.id_producto=d.id_producto JOIN ".$tabla.' a ON a.'.$id.'=pr.'.$id." WHERE p.estado IN ('Confirmado','En preparación','Enviado','Entregado') AND p.fecha_pedido>=? AND p.fecha_pedido<? GROUP BY a.".$id.',a.nombre ORDER BY importe DESC LIMIT 30');$q->execute([$periodo['inicio'],$periodo['fin']]);$filas=$q->fetchAll();
        $r['texto']='Ventas por '.($marca?'marca':'categoría').' · '.$periodo['etiqueta'].':';foreach($filas as $f)$r['texto'].="\n• ".$f['nombre'].' · '.$f['unidades'].' unidades · '.dinero($f['importe']);if(!$filas)$r['texto'].="\nSin ventas confirmadas en este periodo.";
        $r['texto'].="\nImportes históricos de las líneas de pedido, sin envío; excluye pendientes y cancelados. Se usa la clasificación actual del producto. Se muestran hasta 30 grupos.";
        $r['enlaces'][]=$link('Abrir analítica de ventas','admin/ml/ventas_ia.php');
    }elseif($tipo==='informes_integrales'){
        $tipos=preg_match('/producto|inventario|stock/u',$t)?['productos']:(str_contains($t,'cliente')?['clientes']:(str_contains($t,'venta')?['ventas']:(str_contains($t,'pedido')?['pedidos']:['productos','pedidos','clientes','ventas'])));
        $r['texto']='Analítica y proyección · informes ML guardados:';
        foreach($tipos as $tipoInforme){$informe=informe_asistente($tipoInforme,$directorio);$r['enlaces'][]=$link('Ver '.$tipoInforme.' y ML','admin/ml/'.$tipoInforme.'_ia.php#ml-titulo');if(!$informe){$r['texto'].="\n\n".ucfirst($tipoInforme).': todavía no hay informe disponible.';continue;}$r['texto'].="\n\n".ucfirst($tipoInforme).' · Corte: '.($informe['fecha_corte']??'No indicado').' · Generado: '.($informe['generado']??'No indicado');foreach($informe['modelos']??[] as $modelo){$r['texto'].="\n".($modelo['nombre']??'Modelo').' · '.($modelo['estado']??'Sin estado');foreach(array_slice($modelo['metricas']??[],0,12,true) as $clave=>$valor)if(is_scalar($valor))$r['texto'].="\n• ".$clave.': '.$valor;foreach($modelo['graficos']??[] as $g)if(str_starts_with($g['titulo']??'','Próximos 7 días')){ $r['texto'].="\nPronóstico guardado:";foreach(array_slice($g['datos']['labels']??[],0,7) as $i=>$fecha)$r['texto'].="\n• ".$fecha.': '.($tipoInforme==='ventas'?dinero($g['datos']['valores'][$i]):($g['datos']['valores'][$i]??'—'));}}}
        $r['texto'].="\n\nLos informes no se reentrenan desde el chat. Un porcentaje de días dentro de ±20% no es una probabilidad futura; Silhouette no es precisión.";$r['fuente']='Informes ML guardados · fechas indicadas por informe';
    }
    return $r;
}
