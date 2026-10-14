<?php
require_once __DIR__.'/../config/app.php';
function listar_admin(PDO $db,string $tipo,array $filtros,bool $exportar=false): array {
    $q=is_string($filtros['q'] ?? null)?trim($filtros['q']):'';
    $estado=is_string($filtros['estado'] ?? null)?$filtros['estado']:'';
    $orden=is_string($filtros['orden'] ?? null)?$filtros['orden']:'';
    $params=[];$where=[];
    $stock='';
    if($tipo==='productos' && is_string($filtros['stock'] ?? null) && in_array($filtros['stock'],['bajo','critico','agotado'],true)) {
        $stock=$filtros['stock'];
        $where[]=['bajo'=>'p.stock BETWEEN 0 AND 5','critico'=>'p.stock BETWEEN 0 AND 14','agotado'=>'p.stock=0'][$stock];
    }
    if($tipo==='productos') {
        $from='productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca';
        $select='p.*,c.nombre AS categoria,m.nombre AS marca';
        $campos=['p.nombre','p.codigo','c.nombre','m.nombre'];
        $estados=['0','1'];
        $ordenes=['recientes'=>'p.id_producto DESC','nombre'=>'p.nombre,p.id_producto','stock'=>'p.stock,p.id_producto','precio'=>'p.precio,p.id_producto'];
    } elseif($tipo==='pedidos') {
        $from='pedidos p JOIN clientes c ON c.id_cliente=p.id_cliente';
        $select='p.*,COALESCE(p.cliente_nombre,c.nombre) AS cliente_nombre,COALESCE(p.cliente_apellido,c.apellido) AS cliente_apellido,COALESCE(p.cliente_correo,c.correo) AS correo,COALESCE(p.cliente_telefono,c.telefono) AS telefono';
        $campos=['CAST(p.id_pedido AS CHAR)','COALESCE(p.cliente_nombre,c.nombre)','COALESCE(p.cliente_apellido,c.apellido)','COALESCE(p.cliente_correo,c.correo)'];
        $estados=['Pendiente','Confirmado','En preparación','Enviado','Entregado','Cancelado'];
        $ordenes=['recientes'=>'p.id_pedido DESC','antiguos'=>'p.id_pedido','total'=>'p.total DESC,p.id_pedido DESC'];
    } elseif($tipo==='clientes') {
        $from='clientes p';$select='p.*';$campos=['p.nombre','p.apellido','p.correo','p.telefono'];$estados=[];
        $ordenes=['recientes'=>'p.id_cliente DESC','nombre'=>'p.nombre,p.apellido,p.id_cliente'];
    } else throw new InvalidArgumentException('Listado desconocido.');
    if($q!=='') { $where[]='('.implode(' OR ',array_map(fn($campo)=>$campo.' LIKE ?',$campos)).')'; foreach($campos as $_) $params[]='%'.$q.'%'; }
    if(in_array($estado,$estados,true)) { $where[]='p.estado=?';$params[]=$estado; } else $estado='';
    $condicion=$where?' WHERE '.implode(' AND ',$where):'';
    $stmt=$db->prepare('SELECT COUNT(*) FROM '.$from.$condicion);$stmt->execute($params);$total=(int)$stmt->fetchColumn();
    $paginas=max(1,(int)ceil($total/25));$pagina=max(1,min($paginas,(int)($filtros['pagina'] ?? 1)));
    $sql='SELECT '.$select.' FROM '.$from.$condicion.' ORDER BY '.($ordenes[$orden] ?? reset($ordenes));
    if(!$exportar) $sql.=' LIMIT 25 OFFSET '.(($pagina-1)*25);
    $stmt=$db->prepare($sql);$stmt->execute($params);
    return ['filas'=>$stmt->fetchAll(),'total'=>$total,'paginas'=>$paginas,'pagina'=>$pagina,'q'=>$q,'estado'=>$estado,'orden'=>$orden,'stock'=>$stock];
}
function render_filtros_admin(string $tipo,array $lista): void {
    $estados=$tipo==='productos'?['1'=>'Activos','0'=>'Inactivos']:($tipo==='pedidos'?array_combine(['Pendiente','Confirmado','En preparación','Enviado','Entregado','Cancelado'],['Pendiente','Confirmado','En preparación','Enviado','Entregado','Cancelado']):[]);
    $ordenes=$tipo==='productos'?['recientes'=>'Más recientes','nombre'=>'Nombre','stock'=>'Menor stock','precio'=>'Menor precio']:($tipo==='pedidos'?['recientes'=>'Más recientes','antiguos'=>'Más antiguos','total'=>'Mayor importe']:['recientes'=>'Más recientes','nombre'=>'Nombre']);
    $ayuda=$tipo==='productos'?'Nombre, código, categoría o marca':($tipo==='pedidos'?'N.º de pedido, cliente o correo':'Nombre, correo o teléfono');
    echo '<form class="admin-filters" method="GET"><div><label for="filtro-q">Buscar</label><input type="search" name="q" id="filtro-q" value="'.e($lista['q']).'" placeholder="'.e($ayuda).'"></div>';
    if($estados) { echo '<div><label for="filtro-estado">Estado</label><select name="estado" id="filtro-estado"><option value="">Todos</option>'; foreach($estados as $valor=>$nombre) echo '<option value="'.e($valor).'"'.((string)$valor===$lista['estado']?' selected':'').'>'.e($nombre).'</option>'; echo '</select></div>'; }
    if($tipo==='productos') {
        echo '<div><label for="filtro-stock">Stock</label><select name="stock" id="filtro-stock"><option value="">Todos</option>';
        foreach(['bajo'=>'Bajo · 0 a 5','critico'=>'Crítico · menos de 15','agotado'=>'Agotado · 0'] as $valor=>$nombre)echo '<option value="'.$valor.'"'.($valor===$lista['stock']?' selected':'').'>'.$nombre.'</option>';
        echo '</select></div>';
    }
    echo '<div><label for="filtro-orden">Ordenar</label><select name="orden" id="filtro-orden">';foreach($ordenes as $valor=>$nombre) echo '<option value="'.$valor.'"'.($valor===$lista['orden']?' selected':'').'>'.$nombre.'</option>';echo '</select></div><button type="submit" class="btn-primary">Aplicar</button><a href="listar.php" class="filter-reset">Limpiar</a></form>';
}
