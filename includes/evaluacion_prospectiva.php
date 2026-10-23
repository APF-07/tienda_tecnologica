<?php
require_once __DIR__.'/proyeccion_agente.php';
function preparar_evaluacion_prospectiva(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS ml_pronosticos (clave VARCHAR(64) PRIMARY KEY, tipo VARCHAR(20) NOT NULL, generado VARCHAR(40) NOT NULL, objetivo VARCHAR(10) NOT NULL, estimado DOUBLE NOT NULL, referencia DOUBLE NOT NULL, observado DOUBLE NULL, evaluado VARCHAR(19) NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS ml_pronosticos_auditoria (clave VARCHAR(64) PRIMARY KEY, registrado VARCHAR(19) NOT NULL, corte VARCHAR(10) NOT NULL)');
}
function evaluar_pronosticos_guardados(PDO $db,?string $directorio=null,?DateTimeImmutable $reloj=null): array {
    preparar_evaluacion_prospectiva($db);$ahora=$reloj??new DateTimeImmutable();$hoy=$ahora->format('Y-m-d');
    foreach(resumen_proyeccion_agente($directorio,$hoy) as $tipo=>$p){
        if($p['estado']!=='Vigente'||!is_string($p['generado'])||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$p['generado'])||$p['generado']>$ahora->format('Y-m-d H:i:s')||$p['fecha_corte']>=$hoy)continue;
        foreach($p['dias'] as $i=>$dia){
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$dia)||$dia<=$hoy||!is_numeric($p['valores'][$i])||!is_finite((float)$p['valores'][$i])||$p['valores'][$i]<0)continue;
            $fechaValida=DateTimeImmutable::createFromFormat('!Y-m-d',$dia);if(!$fechaValida||$fechaValida->format('Y-m-d')!==$dia)continue;
            $pasado=(new DateTimeImmutable($dia))->modify('-7 days');while($pasado->format('Y-m-d')>=$hoy)$pasado=$pasado->modify('-7 days');$desde=$pasado->format('Y-m-d');$hasta=$pasado->modify('+1 day')->format('Y-m-d');
            $estados=$tipo==='ventas'?"'Confirmado','En preparación','Enviado','Entregado'":"'Pendiente','Confirmado','En preparación','Enviado','Entregado'";
            $agregado=$tipo==='ventas'?'COALESCE(SUM(total),0)':'COUNT(*)';
            $q=$db->prepare("SELECT $agregado FROM pedidos WHERE estado IN ($estados) AND fecha_pedido>=? AND fecha_pedido<?");$q->execute([$desde,$hasta]);$referencia=(float)$q->fetchColumn();
            $clave=hash('sha256',$tipo.'|'.$p['generado'].'|'.$dia);
            $propia=!$db->inTransaction();if($propia)$db->beginTransaction();
            try{$db->prepare('INSERT INTO ml_pronosticos(clave,tipo,generado,objetivo,estimado,referencia) VALUES(?,?,?,?,?,?)')->execute([$clave,$tipo,$p['generado'],$dia,$p['valores'][$i],$referencia]);
                $db->prepare('INSERT INTO ml_pronosticos_auditoria(clave,registrado,corte) VALUES(?,?,?)')->execute([$clave,$ahora->format('Y-m-d H:i:s'),$p['fecha_corte']]);if($propia)$db->commit();
            }catch(PDOException $e){if($propia&&$db->inTransaction())$db->rollBack();if(!in_array((string)$e->getCode(),['23000','23505'],true))throw $e;}
        }
    }
    $q=$db->prepare('SELECT * FROM ml_pronosticos WHERE objetivo<? AND evaluado IS NULL ORDER BY objetivo LIMIT 100');$q->execute([$hoy]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $p){
        $estados=$p['tipo']==='ventas'?"'Confirmado','En preparación','Enviado','Entregado'":"'Pendiente','Confirmado','En preparación','Enviado','Entregado'";
        $agregado=$p['tipo']==='ventas'?'COALESCE(SUM(total),0)':'COUNT(*)';
        $real=$db->prepare("SELECT $agregado FROM pedidos WHERE estado IN ($estados) AND fecha_pedido>=? AND fecha_pedido<?");$real->execute([$p['objetivo'],(new DateTimeImmutable($p['objetivo']))->modify('+1 day')->format('Y-m-d')]);
        $db->prepare('UPDATE ml_pronosticos SET observado=?,evaluado=? WHERE clave=? AND evaluado IS NULL')->execute([(float)$real->fetchColumn(),$ahora->format('Y-m-d H:i:s'),$p['clave']]);
    }
    return $db->query('SELECT tipo,COUNT(*) pronosticos,COUNT(DISTINCT objetivo) dias,AVG(ABS(estimado-observado)) mae,AVG(ABS(referencia-observado)) mae_referencia FROM ml_pronosticos WHERE evaluado IS NOT NULL GROUP BY tipo')->fetchAll(PDO::FETCH_ASSOC);
}
