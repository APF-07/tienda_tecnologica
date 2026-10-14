<?php
// Informes calculados desde MySQL. No se generan cifras de demostración.
function serie_analitica(array $filas, string $etiqueta, string $valor): array {
    return ['labels' => array_map(fn($r) => (string)$r[$etiqueta], $filas), 'valores' => array_map(fn($r) => (float)$r[$valor], $filas)];
}
function informe_analitica(PDO $db, string $tipo): array {
    $q = fn($sql) => $db->query($sql)->fetchAll();
    if ($tipo === 'productos') {
        $metricas = $q('SELECT COUNT(*) total, COALESCE(SUM(stock),0) unidades, SUM(stock < 15) criticos FROM productos WHERE estado = 1')[0];
        return ['titulo'=>'Inventario y productos','descripcion'=>'Existencias del catálogo activo. Se consideran críticos los productos con menos de 15 unidades.',
            'kpis'=>['Productos activos'=>$metricas['total'],'Unidades en stock'=>$metricas['unidades'],'Stock crítico'=>$metricas['criticos'] ?? 0],
            'graficos'=>[
                ['titulo'=>'Productos por categoría','tipo'=>'bar','datos'=>serie_analitica($q('SELECT c.nombre etiqueta, COUNT(*) valor FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria WHERE p.estado=1 GROUP BY c.id_categoria,c.nombre'),'etiqueta','valor')],
                ['titulo'=>'Stock crítico · 10 menores existencias','tipo'=>'bar','datos'=>serie_analitica($q('SELECT nombre etiqueta, stock valor FROM productos WHERE estado=1 AND stock < 15 ORDER BY stock,id_producto LIMIT 10'),'etiqueta','valor')],
                ['titulo'=>'Productos de mayor precio (S/)','tipo'=>'bar','datos'=>serie_analitica($q('SELECT nombre etiqueta,precio valor FROM productos WHERE estado=1 ORDER BY precio DESC LIMIT 5'),'etiqueta','valor')]
            ]];
    }
    if ($tipo === 'clientes') {
        $filas = $q("SELECT c.id_cliente, COUNT(p.id_pedido) compras FROM clientes c LEFT JOIN pedidos p ON p.id_cliente=c.id_cliente AND p.estado IN ('Confirmado','En preparación','Enviado','Entregado') GROUP BY c.id_cliente");
        $segmentos = [0,0,0];
        foreach ($filas as $fila) $segmentos[$fila['compras'] == 0 ? 0 : ($fila['compras'] == 1 ? 1 : 2)]++;
        return ['titulo'=>'Clientes y recurrencia','descripcion'=>'Historial de compras confirmadas y comportamiento de clientes. La segmentación RFM se muestra en la sección Machine Learning.',
            'kpis'=>['Clientes registrados'=>count($filas),'Con compras confirmadas'=>$segmentos[1]+$segmentos[2],'Clientes recurrentes'=>$segmentos[2]],
            'graficos'=>[
                ['titulo'=>'Historial de compras por cliente','tipo'=>'doughnut','datos'=>['labels'=>['Sin compras confirmadas','Una compra','Dos o más compras'],'valores'=>$segmentos]],
                ['titulo'=>'Clientes con más compras confirmadas','tipo'=>'bar','datos'=>serie_analitica($q("SELECT CONCAT(c.nombre,' ',c.apellido) etiqueta,COUNT(*) valor FROM clientes c JOIN pedidos p ON p.id_cliente=c.id_cliente WHERE p.estado IN ('Confirmado','En preparación','Enviado','Entregado') GROUP BY c.id_cliente,c.nombre,c.apellido ORDER BY valor DESC LIMIT 5"),'etiqueta','valor')]
            ]];
    }
    if ($tipo === 'ventas') {
        $m = $q("SELECT COALESCE(SUM(total),0) ingreso,COALESCE(AVG(total),0) promedio,COUNT(*) pedidos FROM pedidos WHERE estado IN ('Confirmado','En preparación','Enviado','Entregado')")[0];
        $unidades = $db->query("SELECT COALESCE(SUM(d.cantidad),0) FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido=d.id_pedido WHERE p.estado IN ('Confirmado','En preparación','Enviado','Entregado')")->fetchColumn();
        $diarios = $q("SELECT DATE(fecha_pedido) etiqueta,SUM(total) valor FROM pedidos WHERE estado IN ('Confirmado','En preparación','Enviado','Entregado') AND fecha_pedido >= DATE_SUB(CURRENT_DATE,INTERVAL 29 DAY) GROUP BY DATE(fecha_pedido) ORDER BY etiqueta");
        $porFecha = array_column($diarios,'valor','etiqueta');
        $serie = ['labels'=>[], 'valores'=>[]];
        $hoy = new DateTimeImmutable($db->query('SELECT CURRENT_DATE')->fetchColumn());
        for ($i=29;$i>=0;$i--) { $fecha=$hoy->modify("-$i days")->format('Y-m-d'); $serie['labels'][]=$fecha; $serie['valores'][]=(float)($porFecha[$fecha] ?? 0); }
        return ['titulo'=>'Ventas confirmadas','descripcion'=>'Importes de pedidos confirmados, en preparación, enviados y entregados. Incluyen envío; no acreditan el cobro bancario. Pendientes y cancelados se excluyen.',
            'kpis'=>['Importe confirmado'=>'S/ '.number_format($m['ingreso'],2),'Ticket promedio'=>'S/ '.number_format($m['promedio'],2),'Unidades vendidas'=>$unidades],
            'graficos'=>[['titulo'=>'Importe diario · últimos 30 días (S/)','tipo'=>'line','datos'=>$serie],['titulo'=>'Pedidos por método de pago','tipo'=>'doughnut','datos'=>serie_analitica($q("SELECT metodo_pago etiqueta,COUNT(*) valor FROM pedidos WHERE estado IN ('Confirmado','En preparación','Enviado','Entregado') GROUP BY metodo_pago"),'etiqueta','valor')]]];
    }
    $estados = $q('SELECT estado etiqueta,COUNT(*) valor FROM pedidos GROUP BY estado');
    $historico = $q("SELECT DATE(fecha_pedido) fecha,COUNT(*) cantidad FROM pedidos WHERE estado != 'Cancelado' AND fecha_pedido >= DATE_SUB(CURRENT_DATE,INTERVAL 29 DAY) GROUP BY DATE(fecha_pedido)");
    $porFecha = array_column($historico,'cantidad','fecha');
    $hoy = new DateTimeImmutable($db->query('SELECT CURRENT_DATE')->fetchColumn());
    $y=[]; for ($i=29;$i>=0;$i--) $y[]=(int)($porFecha[$hoy->modify("-$i days")->format('Y-m-d')] ?? 0);
    $media=array_sum($y)/30; $cov=0; $var=0;
    for($i=0;$i<30;$i++) { $cov+=($i-14.5)*($y[$i]-$media); $var+=($i-14.5)**2; }
    $pendiente=$cov/$var;
    $pred=['labels'=>[], 'valores'=>[]];
    for($i=1;$i<=7;$i++) { $pred['labels'][]=$hoy->modify("+$i days")->format('Y-m-d'); $pred['valores'][]=max(0,(int)round($media+$pendiente*(29+$i-14.5))); }
    $total=array_sum(array_column($estados,'valor'));
    return ['titulo'=>'Pedidos y Machine Learning','descripcion'=>'Indicadores operativos de los pedidos. Los pronósticos y la evaluación de los modelos se muestran en la sección Machine Learning.',
        'kpis'=>['Pedidos registrados'=>$total,'Pedidos últimos 30 días'=>array_sum($y),'Ventana de cálculo'=>'30 días'],
        'graficos'=>[['titulo'=>'Historial de pedidos · últimos 30 días','tipo'=>'line','datos'=>['labels'=>array_map(fn($i)=>$hoy->modify('-'.(29-$i).' days')->format('Y-m-d'),range(0,29)),'valores'=>$y]],['titulo'=>'Estado de los pedidos','tipo'=>'doughnut','datos'=>serie_analitica($estados,'etiqueta','valor')],['titulo'=>'Pedidos por hora registrada','tipo'=>'bar','datos'=>serie_analitica($q("SELECT CONCAT(LPAD(HOUR(fecha_pedido),2,'0'),':00') etiqueta,COUNT(*) valor FROM pedidos GROUP BY HOUR(fecha_pedido) ORDER BY HOUR(fecha_pedido)"),'etiqueta','valor')]]];
}
