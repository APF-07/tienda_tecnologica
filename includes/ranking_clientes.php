<?php
require_once __DIR__.'/consultas_asistente.php';
function responder_ranking_clientes(PDO $db,string $pregunta): array {
    $t=normalizar_consulta_admin($pregunta);$limite=5;
    if(preg_match('/\b(?:top|ranking(?: de)?)\s*(\d+)\b|\b(\d+)\s*(?:top\s+)?(?:mejores\s+|principales\s+)?clientes\b/u',$t,$m))$limite=(int)($m[1]!==''?$m[1]:$m[2]);
    elseif(preg_match('/\b(uno|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez)\b/u',$t,$m))$limite=array_search($m[1],['','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve','diez'],true);
    $r=['texto'=>'','enlaces'=>[],'fuente'=>'MySQL / MariaDB · ranking de clientes','fecha'=>date('d/m/Y H:i'),'modo'=>'local','privado'=>true];
    if($limite<1||$limite>50){$r['texto']='Puedo mostrar un ranking de 1 a 50 clientes. Indica cuántos deseas consultar.';return $r;}
    $importe=(bool)preg_match('/gast|importe|monto|dinero|factur/u',$t);
    $orden=$importe?'importe DESC,compras DESC,c.id_cliente':'compras DESC,importe DESC,c.id_cliente';
    $params=[];$filtro='';$etiqueta='todo el historial';
    if(preg_match('/hoy|ayer|semana|mes|dias|desde|entre/u',$t)){
        $periodo=periodo_ventas_admin($pregunta);$filtro=' AND p.fecha_pedido>=? AND p.fecha_pedido<?';$params=[$periodo['inicio'],$periodo['fin']];$etiqueta=$periodo['etiqueta'];
    }
    $q=$db->prepare("SELECT c.id_cliente,c.nombre,c.apellido,COUNT(*) compras,SUM(p.total) importe FROM clientes c JOIN pedidos p ON p.id_cliente=c.id_cliente WHERE p.estado IN ('Confirmado','En preparación','Enviado','Entregado')".$filtro." GROUP BY c.id_cliente,c.nombre,c.apellido ORDER BY $orden LIMIT $limite");$q->execute($params);$filas=$q->fetchAll(PDO::FETCH_ASSOC);
    $r['texto']='Top '.$limite.' clientes por '.($importe?'importe confirmado':'cantidad de compras confirmadas').' · '.$etiqueta.'.';
    if(!$importe)$r['texto'].=' Criterio predeterminado: frecuencia de compra.';
    if(!$filas)$r['texto'].="\nNo hay clientes con compras confirmadas en ese periodo.";
    else{
        foreach($filas as $i=>$f){$r['texto'].="\n".($i+1).'. '.trim($f['nombre'].' '.$f['apellido']).' · Cliente #'.$f['id_cliente'].' · '.$f['compras'].' compras · '.dinero($f['importe']);$r['enlaces'][]=['texto'=>'Ver cliente #'.$f['id_cliente'],'url'=>ruta('admin/clientes/detalle.php?id='.(int)$f['id_cliente'])];}
        if(count($filas)<$limite)$r['texto'].="\nSolo hay ".count($filas).' clientes con compras confirmadas para este periodo.';
    }
    $r['texto'].="\nSe excluyen pendientes y cancelados. Los importes incluyen envío y no acreditan pagos recibidos. Empates: se ordenan por ".($importe?'cantidad de compras':'importe').', y luego por identificador.';
    return $r;
}
