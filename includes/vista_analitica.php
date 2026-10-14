<?php
require_once __DIR__ . '/verificar_admin.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/analitica.php';
$informe = informe_analitica($conexion, $tipo_informe);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$iconos_nav = [
    'ventas'=>'<path d="M4 3v18h17M8 16l4-5 4 2 5-7"/>',
    'productos'=>'<path d="m12 3 9 5v8l-9 5-9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
    'clientes'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5.65"/>',
    'pedidos'=>'<rect x="4" y="5" width="16" height="16" rx="3"/><path d="M9 5V3h6v2M8 11h8M8 16h5"/>'
];
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo $e($informe['titulo']); ?> · TechStore</title><link rel="stylesheet" href="../../css/admin.css"><link rel="stylesheet" href="../../css/analitica.css?v=20261013-calidad2"><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script><link rel="stylesheet" href="../../css/experiencia.css?v=20261014"><script src="../../js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="../../css/admin-experiencia.css?v=1"></head>
<body class="analytics-page"><main class="analytics-shell">
<nav class="analytics-nav" aria-label="Analítica"><a class="analytics-back" href="../dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12H4m6-6-6 6 6 6"/></svg><span>Administración</span></a><div class="analytics-tabs"><?php foreach(['pedidos'=>'Pedidos','clientes'=>'Clientes','productos'=>'Productos','ventas'=>'Ventas'] as $clave=>$nombre): ?><a class="analytics-tab analytics-tab-<?= $clave ?>" href="<?= $clave ?>_ia.php" <?= $clave===$tipo_informe ? 'aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconos_nav[$clave] ?></svg><span><?= $nombre ?></span></a><?php endforeach; ?></div></nav>
<header class="analytics-heading"><span class="analytics-tag">TECHSTORE / ANALÍTICA</span><h1><?php echo $e($informe['titulo']); ?></h1><p><?php echo $e($informe['descripcion']); ?></p><small>Consulta actual de MySQL · <?php echo $e($conexion->query('SELECT NOW()')->fetchColumn()); ?> (hora del servidor)</small></header>
<section class="analytics-kpis" aria-label="Indicadores"><?php foreach($informe['kpis'] as $nombre=>$valor): ?><article><p><?php echo $e($nombre); ?></p><strong><?php echo $e($valor); ?></strong></article><?php endforeach; ?></section>
<section class="analytics-charts"><?php foreach($informe['graficos'] as $i=>$grafico): ?><article><h2><?php echo $e($grafico['titulo']); ?></h2><?php if (!$grafico['datos']['labels']): ?><p>No hay datos para este indicador.</p><?php else: ?><div class="chart-frame"><canvas id="grafico-<?php echo $i; ?>" aria-label="<?php echo $e($grafico['titulo']); ?>" role="img"></canvas></div><?php endif; ?><details><summary>Ver datos</summary><table><thead><tr><th>Concepto</th><th>Valor</th></tr></thead><tbody><?php foreach($grafico['datos']['labels'] as $j=>$label): ?><tr><td><?php echo $e($label); ?></td><td><?php echo $e($grafico['datos']['valores'][$j]); ?></td></tr><?php endforeach; ?></tbody></table></details></article><?php endforeach; ?></section>
<?php require __DIR__.'/vista_ml.php'; ?>
</main><script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;
    Chart.defaults.color = '#bdcce0';
    Chart.defaults.borderColor = '#2a3b54';
    const graficos = <?php echo json_encode($informe['graficos'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    graficos.forEach((g,i) => { const canvas=document.getElementById('grafico-'+i); if(!canvas) return;
        new Chart(canvas,{type:g.tipo,data:{labels:g.datos.labels,datasets:[{label:g.titulo,data:g.datos.valores,backgroundColor:g.tipo==='doughnut'?(g.titulo==='Estado de los pedidos'?g.datos.labels.map(l=>({'Pendiente':'#fbbf24','Confirmado':'#38bdf8','En preparación':'#a78bfa','Enviado':'#2dd4bf','Entregado':'#34d399','Cancelado':'#fb7185'}[l] || '#94a3b8')):['#38bdf8','#818cf8','#34d399','#fbbf24','#fb7185','#94a3b8']):'#38bdf8',borderColor:'#38bdf8',borderWidth:2,borderRadius:g.tipo==='bar'?5:0,tension:.25}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:g.tipo==='doughnut'}},...(g.tipo==='doughnut'?{}:{scales:{y:{beginAtZero:true},x:{ticks:{maxTicksLimit:5,maxRotation:0}}}})}});
    });
});
</script></body></html>
