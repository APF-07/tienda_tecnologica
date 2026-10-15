<?php
function destino_pedido(array $pedido): array {
    $direccion=(string)($pedido['direccion']??$pedido['cliente_direccion']??'');
    if(preg_match('/(?:^|\|)\s*Dir:\s*(.*)$/iu',$direccion,$m))$direccion=trim($m[1]);
    else $direccion=trim(preg_replace('/^DNI:\s*\d+\s*\|\s*/i','',$direccion));
    $lat=$pedido['entrega_lat']??null;$lng=$pedido['entrega_lng']??null;
    $coordenadas=is_numeric($lat)&&is_numeric($lng)&&abs((float)$lat)<=90&&abs((float)$lng)<=180;
    $destino=$coordenadas?$lat.','.$lng:$direccion;
    $parametros=['api'=>1,'destination'=>$destino,'travelmode'=>'driving'];
    if(!empty($pedido['entrega_place_id']))$parametros['destination_place_id']=$pedido['entrega_place_id'];
    return ['direccion'=>$direccion,'preciso'=>$coordenadas,'ruta'=>'https://www.google.com/maps/dir/?'.http_build_query($parametros,'','&',PHP_QUERY_RFC3986),'mapa'=>'https://maps.google.com/maps?'.http_build_query(['q'=>$destino,'z'=>17,'output'=>'embed'],'','&',PHP_QUERY_RFC3986)];
}
