<?php
function consulta_clasificacion_admin(string $pregunta): bool {
    $t=normalizar_consulta_admin($pregunta);
    if(preg_match('/venta|vendid|predic|proyeccion|analitica|segment|\b(borra|borrar|elimina|eliminar|modifica|modificar|actualiza|actualizar|cambia|cambiar|entrena|entrenar|cancela|cancelar)\b/u',$t))return false;
    return (bool)preg_match('/\b(?:marca|categoria)\s+(?!hay\b|registrad|tiene\b|existe\b|esta\b|son\b)[\p{L}\d]/u',$t)
        || (bool)preg_match('/^(?:y\s+)?de\s+(?:la\s+)?[\p{L}\d .-]+[?]?$/u',trim($t," ¿"))
        || (bool)preg_match('/\b(celulares?|celualres|smartphones?|laptops?|componentes|computadoras?|monitores|almacenamiento|perifericos)\b/u',$t);
}
function filtrar_clasificacion_admin(PDO $db,string $pregunta): ?array {
    if(!consulta_clasificacion_admin($pregunta))return null;
    $t=str_replace('celualres','celulares',normalizar_consulta_admin($pregunta));$elegidas=[];
    foreach(['marcas'=>'id_marca','categorias'=>'id_categoria'] as $tabla=>$id){
        foreach($db->query('SELECT '.$id.' id,nombre FROM '.$tabla)->fetchAll() as $f){
            $nombre=normalizar_consulta_admin($f['nombre']);$patron=preg_quote($nombre,'/');
            if($tabla==='categorias'&&str_ends_with($nombre,'s'))$patron=preg_quote(substr($nombre,0,-1),'/').'s?';
            if(preg_match('/\b'.$patron.'\b/u',$t))$elegidas[$tabla]=$f;
        }
    }
    foreach(['marca'=>'marcas','categoria'=>'categorias'] as $singular=>$plural){
        if(preg_match('/\b'.$singular.'\s+(.+)$/u',$t)&&!isset($elegidas[$plural]))return ['texto'=>'No encontré esa '.$singular.' en los registros. Consulta la lista de '.$plural.' para elegir un nombre disponible.','enlaces'=>[['texto'=>'Ver '.$plural,'url'=>ruta('admin/'.$plural.'/listar.php')]],'fuente'=>'MySQL / MariaDB · clasificación exacta','fecha'=>date('d/m/Y H:i'),'modo'=>'local'];
    }
    if(!$elegidas)return null;
    $where=[];$params=[];$filtros=[];
    foreach($elegidas as $tabla=>$f){$id=$tabla==='marcas'?'id_marca':'id_categoria';$where[]='p.'.$id.'=?';$params[]=(int)$f['id'];$filtros[$tabla==='marcas'?'marca':'categoria']=(int)$f['id'];}
    if(str_contains($t,'inactiv')){$where[]='p.estado=0';$filtros['estado']='0';}
    elseif(preg_match('/\bactivos?\b/u',$t)||str_contains($t,'disponib')){$where[]='p.estado=1';$filtros['estado']='1';}
    if(str_contains($t,'disponib')){$where[]='p.stock>0';$filtros['stock']='disponible';}
    if(preg_match('/agotad|sin stock/u',$t)){$where[]='p.stock=0';$filtros['stock']='agotado';}
    elseif(preg_match('/stock.*baj|poco stock/u',$t)){$where[]='p.stock BETWEEN 0 AND 5';$filtros['stock']='bajo';}
    $from=' FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca WHERE '.implode(' AND ',$where);
    $q=$db->prepare('SELECT COUNT(*) total,COALESCE(SUM(p.stock),0) unidades'.$from);$q->execute($params);$total=$q->fetch();
    $q=$db->prepare('SELECT p.id_producto,p.codigo,p.nombre,p.precio,p.stock,p.estado,c.nombre categoria,m.nombre marca'.$from.' ORDER BY p.id_producto DESC LIMIT 12');$q->execute($params);$filas=$q->fetchAll();
    $nombres=implode(' + ',array_column($elegidas,'nombre'));
    $r=['texto'=>$nombres.': '.$total['total'].' productos encontrados · '.$total['unidades'].' unidades en stock.','enlaces'=>[],'fuente'=>'MySQL / MariaDB · filtros exactos de categoría y marca','fecha'=>date('d/m/Y H:i'),'modo'=>'local'];
    if(!$filas)$r['texto'].="\nNo hay productos con esta combinación. No se incluyen productos de otras categorías o marcas.";
    else{$r['texto'].="\nMuestro ".count($filas).' de '.$total['total'].':';foreach($filas as $p)$r['texto'].="\n• #".$p['id_producto'].' · '.$p['nombre'].' · '.$p['marca'].' · '.$p['categoria'].' · '.dinero($p['precio']).' · '.$p['stock'].' unidades · '.($p['estado']?'Activo':'Inactivo');}
    $r['texto'].="\nSe incluyen activos e inactivos salvo que solicites un estado concreto.";
    $r['enlaces'][]=['texto'=>'Ver todos los productos de esta marca/categoría','url'=>ruta('admin/productos/listar.php?'.http_build_query($filtros))];return $r;
}
