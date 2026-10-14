<?php
$archivo = __DIR__.'/../ml/cache_todos_'.$tipo_informe.'.json';
$ml = is_file($archivo) ? json_decode(file_get_contents($archivo),true) : null;
if (!is_array($ml) || ($ml['schema_version'] ?? 0)!==2) $ml=null;
?>
<section class="ml-section" aria-labelledby="ml-titulo">
<header class="ml-heading"><div><span class="analytics-tag">MODELOS / MACHINE LEARNING</span><h2 id="ml-titulo">Predicciones con evaluación</h2><p><?php if($ml): ?>Entrenado: <?php echo e($ml['generado']); ?> · Corte: <?php echo e($ml['fecha_corte']); ?> · MySQL / MariaDB<?php else: ?>Entrena los modelos para ver resultados y métricas de evaluación.<?php endif; ?></p></div><form id="form-entrenar" action="entrenar.php" method="POST"><?php echo csrf_campo(); ?><button class="ml-train" type="submit">Entrenar y actualizar</button></form></header>
<p id="estado-entrenamiento" role="status" aria-live="polite"></p>
<?php if($ml): foreach($ml['modelos'] as $modelo): ?>
<article class="ml-model"><div class="ml-model-heading"><div><h3><?php echo e($modelo['nombre']); ?></h3><span><?php echo e($modelo['algoritmo']); ?></span></div><span class="ml-state"><?php echo $modelo['estado']==='listo'?'Entrenado':'Historial insuficiente'; ?></span></div><p class="ml-description"><?php echo e($modelo['descripcion']); ?></p>
<?php if(isset($modelo['calidad'])): $calidad=$modelo['calidad']; ?>
<div class="ml-quality"><div class="ml-quality-number"><?= e(number_format((float)$calidad['porcentaje'],1,',','.')) ?><span>%</span></div><div class="ml-quality-info"><h4><?= e($calidad['titulo']) ?></h4><p><?= (int)$calidad['aciertos'] ?> de <?= (int)$calidad['dias'] ?> días de prueba · <?= (int)$calidad['ventanas'] ?> ventanas temporales</p><div class="ml-quality-track" aria-hidden="true"><span style="width:<?= max(0,min(100,(float)$calidad['porcentaje'])) ?>%"></span></div><small><?= e($calidad['explicacion']) ?></small></div></div>
<?php endif; ?>
<div class="ml-metrics"><?php foreach($modelo['metricas'] as $nombre=>$valor): ?><div><span><?php echo e($nombre); ?></span><strong><?php echo e(is_float($valor) ? number_format($valor,2,',','.') : ($valor ?? '—')); ?><?= $valor !== null && strpos($nombre,'(%)') !== false ? '%' : '' ?></strong></div><?php endforeach; ?></div>
<div class="analytics-charts"><?php foreach($modelo['graficos'] as $grafico): $indice_ml=count($graficos_ml ?? []); $graficos_ml[]=$grafico; ?><article><h4><?php echo e($grafico['titulo']); ?></h4><div class="chart-frame"><canvas id="ml-grafico-<?php echo $indice_ml; ?>" aria-label="<?php echo e($grafico['titulo']); ?>" role="img"></canvas></div><details><summary>Ver datos del gráfico</summary><table><thead><tr><th>Concepto</th><?php foreach($grafico['series'] ?? [['nombre'=>'Valor']] as $serie): ?><th><?php echo e($serie['nombre']); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($grafico['datos']['labels'] as $i=>$label): ?><tr><td><?php echo e($label); ?></td><?php foreach($grafico['series'] ?? [['valores'=>$grafico['datos']['valores']]] as $serie): ?><td><?php echo e($serie['valores'][$i]); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></details></article><?php endforeach; ?></div>
<?php foreach($modelo['tablas'] as $tabla): ?><div class="ml-table"><h4><?php echo e($tabla['titulo']); ?></h4><?php if(!$tabla['filas']): ?><p>No hay registros para este indicador.</p><?php else: ?><div class="ml-table-scroll"><table><thead><tr><?php foreach($tabla['columnas'] as $columna): ?><th><?php echo e($columna); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($tabla['filas'] as $fila): ?><tr><?php foreach($fila as $valor): ?><td><?php echo e(is_float($valor) ? number_format($valor,2,',','.') : ($valor ?? '—')); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><?php endforeach; ?>
<details class="ml-method"><summary>Cómo se entrenó y cómo interpretar los resultados</summary><ul><?php foreach($modelo['metodologia'] as $texto): ?><li><?php echo e($texto); ?></li><?php endforeach; ?></ul></details>
</article>
<?php endforeach; endif; ?>
</section>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    if(window.Chart){Chart.defaults.color='#bdcce0';Chart.defaults.borderColor='#2a3b54';}
    const graficos=<?php echo json_encode($graficos_ml ?? [],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    if(window.Chart) graficos.forEach((g,i)=>{
        const colores=['#38bdf8','#a78bfa','#34d399','#fbbf24','#fb7185'];
        const series=g.series || [{nombre:g.titulo,valores:g.datos.valores}];
        new Chart(document.getElementById('ml-grafico-'+i),{type:g.tipo,data:{labels:g.datos.labels,datasets:series.map((s,j)=>({label:s.nombre,data:s.valores,backgroundColor:g.tipo==='doughnut'?colores:colores[j%colores.length],borderColor:colores[j%colores.length],borderWidth:2,tension:.2,borderRadius:g.tipo==='bar'?5:0}))},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:g.tipo==='doughnut'||series.length>1}},...(g.tipo==='doughnut'?{}:{scales:{y:{beginAtZero:true},x:{ticks:{maxTicksLimit:5,maxRotation:0}}}})}});
    });
    const formulario=document.getElementById('form-entrenar'),estado=document.getElementById('estado-entrenamiento');
    formulario.addEventListener('submit',async evento=>{
        evento.preventDefault();const boton=formulario.querySelector('button');boton.disabled=true;
        estado.textContent='Entrenando modelos y evaluando resultados. Esto puede tardar un momento…';
        try {const respuesta=await fetch(formulario.action,{method:'POST',body:new FormData(formulario),headers:{Accept:'application/json'}});const datos=await respuesta.json();if(!respuesta.ok||!datos.ok)throw new Error(datos.mensaje || 'No se pudo entrenar.');estado.textContent='Modelos actualizados. Cargando resultados…';location.reload();}
        catch(error){estado.textContent=error.message;boton.disabled=false;}
    });
});
</script>
