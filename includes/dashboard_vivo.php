<?php
require_once __DIR__.'/multiagentes.php';
function datos_dashboard_vivo(PDO $db): array {
    preparar_registro_agentes($db);
    $hoy=new DateTimeImmutable('today');$inicio=$hoy->format('Y-m-d H:i:s');$fin=$hoy->modify('+1 day')->format('Y-m-d H:i:s');
    $q=$db->prepare("SELECT COUNT(*) pedidos,COALESCE(SUM(CASE WHEN estado IN ('Confirmado','En preparación','Enviado','Entregado') THEN total ELSE 0 END),0) ventas FROM pedidos WHERE fecha_pedido>=? AND fecha_pedido<?");$q->execute([$inicio,$fin]);$dia=$q->fetch(PDO::FETCH_ASSOC);
    $stock=$db->query('SELECT COALESCE(SUM(stock=0),0) agotados,COALESCE(SUM(stock BETWEEN 0 AND 5),0) bajos FROM productos WHERE estado=1')->fetch(PDO::FETCH_ASSOC);
    $envios=$db->query('SELECT estado,COUNT(*) cantidad FROM alertas_operativas GROUP BY estado')->fetchAll(PDO::FETCH_ASSOC);
    $estado=leer_estado_agentes($db,'ultima');
    $ejecuciones=$db->query('SELECT id,inicio,fin,estado,error FROM agentes_ejecuciones ORDER BY inicio DESC,id DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);
    $ultimas=$db->query('SELECT tipo,entidad,estado,creada,intento FROM alertas_operativas ORDER BY creada DESC,clave DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
    $ultimosPedidos=$db->query('SELECT id_pedido,fecha_pedido,estado,total FROM pedidos ORDER BY fecha_pedido DESC,id_pedido DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    return ['fecha'=>date('c'),'dia'=>$dia,'stock'=>$stock,'envios'=>$envios,'ultima'=>$estado,'ejecuciones'=>$ejecuciones,'avisos'=>$ultimas,'pedidos_recientes'=>$ultimosPedidos,'proyeccion'=>resumen_proyeccion_agente(),'demoras'=>(new AgenteSeguimiento())->ejecutar($db,['fecha'=>date('Y-m-d H:i:s')])];
}
