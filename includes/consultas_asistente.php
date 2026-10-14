<?php
require_once __DIR__.'/../config/app.php';
function normalizar_consulta_admin(string $texto): string {
    return mb_strtolower(strtr($texto,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u']));
}
function termino_producto_admin(string $pregunta): string {
    $texto=trim($pregunta," \t\n\r\0\x0B¿?¡!.");
    $patrones=[
        '/^(?:busca|buscar|muestra|mostrar|consulta)\s+(?:los\s+)?productos?\s+(?:de\s+)?(.+)$/iu',
        '/\bstock\s+(?:actual\s+)?(?:de|del|para)\s+(.+)$/iu',
        '/\bstock\s+tiene\s+(?:(?:el|la|los|las)\s+)?(.+)$/iu'
    ];
    foreach($patrones as $patron)if(preg_match($patron,$texto,$m)){
        $termino=preg_replace('/^(?:(?:los|las|el|la)\s+)?(?:productos?\s+)?(?:(?:de\s+)?(?:la\s+)?(?:marca|categoria)\s+)?/iu','',trim($m[1]));
        return mb_substr(trim($termino," \t\n\r\0\x0B¿?¡!.\"'"),0,100);
    }
    return '';
}
function periodo_ventas_admin(string $pregunta,?DateTimeImmutable $hoy=null): array {
    $hoy=($hoy ?? new DateTimeImmutable('today'))->setTime(0,0);
    $texto=normalizar_consulta_admin($pregunta);$fin=$hoy->modify('+1 day');
    if(str_contains($texto,'ayer')){$inicio=$hoy->modify('-1 day');$fin=$hoy;$etiqueta='ayer';}
    elseif(str_contains($texto,'hoy')){$inicio=$hoy;$etiqueta='hoy';}
    elseif(str_contains($texto,'este mes')||str_contains($texto,'del mes')){$inicio=$hoy->modify('first day of this month');$etiqueta='este mes';}
    elseif(str_contains($texto,'esta semana')){$inicio=$hoy->modify('monday this week');$etiqueta='esta semana';}
    elseif(preg_match('/\b7\s*dias\b/u',$texto)){$inicio=$hoy->modify('-6 days');$etiqueta='últimos 7 días';}
    else{$inicio=$hoy->modify('-29 days');$etiqueta='últimos 30 días';}
    return ['inicio'=>$inicio->format('Y-m-d H:i:s'),'fin'=>$fin->format('Y-m-d H:i:s'),'etiqueta'=>$etiqueta];
}
function consultar_productos_admin(PDO $db,string $termino): array {
    $like='%'.strtr($termino,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
    $from=" FROM productos p JOIN marcas m ON m.id_marca=p.id_marca JOIN categorias c ON c.id_categoria=p.id_categoria WHERE p.estado=1 AND (p.nombre LIKE ? ESCAPE '!' OR p.codigo LIKE ? ESCAPE '!' OR m.nombre LIKE ? ESCAPE '!' OR c.nombre LIKE ? ESCAPE '!')";
    $params=[$like,$like,$like,$like];
    $q=$db->prepare('SELECT COUNT(*)'.$from);$q->execute($params);$total=(int)$q->fetchColumn();
    $q=$db->prepare('SELECT p.id_producto,p.codigo,p.nombre,p.precio,p.stock,m.nombre marca,c.nombre categoria'.$from.' ORDER BY p.stock,p.id_producto LIMIT 8');$q->execute($params);
    return ['busqueda'=>$termino,'total_coincidencias'=>$total,'productos'=>$q->fetchAll()];
}
function consultar_ventas_admin(PDO $db,string $pregunta,?DateTimeImmutable $hoy=null): array {
    $periodo=periodo_ventas_admin($pregunta,$hoy);
    $q=$db->prepare("SELECT COUNT(*) pedidos,COALESCE(SUM(total),0) importe,COALESCE(AVG(total),0) ticket_promedio FROM pedidos WHERE estado IN ('Confirmado','En preparación','Enviado','Entregado') AND fecha_pedido>=? AND fecha_pedido<?");
    $q->execute([$periodo['inicio'],$periodo['fin']]);return $periodo+$q->fetch();
}
function consultar_mas_vendido_admin(PDO $db,string $pregunta,?DateTimeImmutable $hoy=null): array {
    $texto=normalizar_consulta_admin($pregunta);
    $periodo=periodo_ventas_admin($pregunta,$hoy);
    $explicito=preg_match('/\bhoy\b|\bayer\b|esta semana|este mes|del mes|\b(?:7|30)\s*dias\b/u',$texto);
    if(!$explicito){$periodo['inicio']=null;$periodo['etiqueta']='todo el historial hasta '.(new DateTimeImmutable($periodo['fin']))->modify('-1 day')->format('d/m/Y');}
    $filtro=$periodo['inicio']!==null?' AND p.fecha_pedido>=?':'';
    $params=$periodo['inicio']!==null?[$periodo['inicio'],$periodo['fin']]:[$periodo['fin']];
    $sql="SELECT pr.id_producto,pr.codigo,pr.nombre,SUM(d.cantidad) unidades,COUNT(DISTINCT p.id_pedido) pedidos,SUM(d.subtotal) importe FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido=d.id_pedido JOIN productos pr ON pr.id_producto=d.id_producto WHERE p.estado IN ('Confirmado','En preparación','Enviado','Entregado')".$filtro." AND p.fecha_pedido<? GROUP BY pr.id_producto,pr.codigo,pr.nombre HAVING SUM(d.cantidad)>0 ORDER BY unidades DESC,pr.id_producto LIMIT 6";
    $q=$db->prepare($sql);$q->execute($params);$filas=$q->fetchAll();
    $max=$filas?(int)$filas[0]['unidades']:0;
    $ganadores=array_values(array_filter($filas,static fn($f)=>(int)$f['unidades']===$max));
    return ['periodo'=>$periodo,'productos'=>array_slice($ganadores,0,5),'mas_empates'=>count($ganadores)>5];
}
