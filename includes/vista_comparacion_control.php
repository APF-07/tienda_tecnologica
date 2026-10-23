<?php
/** Comparación retrospectiva: conserva las métricas publicadas sin reentrenar. */
foreach ($informes as $tipoComparado => $informeComparado):
    $modeloComparado = $informeComparado['modelos'][0] ?? [];
    $validaciones = []; $pruebas = [];
    foreach ($modeloComparado['tablas'] ?? [] as $tablaComparada) {
        if (($tablaComparada['titulo'] ?? '') === 'Selección con validación temporal por ventanas') {
            foreach ($tablaComparada['filas'] ?? [] as $fila) $validaciones[$fila[0]] = $fila;
        }
        if (($tablaComparada['titulo'] ?? '') === 'Evaluación final por ventanas de hasta 7 días') {
            foreach ($tablaComparada['filas'] ?? [] as $fila) $pruebas[$fila[0]] = $fila;
        }
    }
    $errores = [];
    foreach ($guia as $nombreModelo => $_) {
        $valor = $validaciones[$nombreModelo][1] ?? null;
        if (is_numeric($valor) && is_finite((float)$valor)) $errores[$nombreModelo] = (float)$valor;
    }
    $maximo = $errores ? max(1, max($errores)) : 1;
    $mejor = $errores ? array_search(min($errores), $errores, true) : null;
    $unidadComparada = $tipoComparado === 'ventas' ? 'S/' : 'pedidos';
    $numeroComparado = static function ($valor): string {
        return is_numeric($valor) && is_finite((float)$valor) ? number_format((float)$valor, 2, '.', ',') : 'Sin evaluación';
    };
?>
<section class="control-panel control-comparison" aria-labelledby="comparacion-<?= e($tipoComparado) ?>">
    <div class="comparison-heading"><div><span class="control-kicker">COMPARATIVA · <?= e(strtoupper($tipoComparado)) ?></span><h2 id="comparacion-<?= e($tipoComparado) ?>">Cinco modelos, una misma evaluación</h2></div><a class="btn-action" href="<?= e($tipoComparado) ?>_ia.php">Informe completo ↗</a></div>
    <p>Publicado: <?= e($informeComparado['generado'] ?? 'Sin entrenamiento') ?> · Datos hasta <?= e($informeComparado['fecha_corte'] ?? '—') ?>. Comparación histórica del mismo informe; cambiar el selector no recalcula estos resultados.</p>
    <div class="comparison-summary"><strong>Menor MAE de validación entre los cinco ML: <?= e($mejor ?? 'Sin evaluación') ?></strong><span>Modelo publicado: <?= e($modeloComparado['control']['utilizado'] ?? 'Sin informe') ?></span></div>
    <div class="comparison-bars" role="group" aria-label="MAE de validación en <?= e($unidadComparada) ?>, menor es mejor">
    <?php foreach ($guia as $nombreModelo => $_): $mae = $errores[$nombreModelo] ?? null; ?>
        <div class="comparison-bar-row"><span><?= e($nombreModelo) ?></span><div class="comparison-track"><div class="comparison-fill <?= $nombreModelo === $mejor ? 'is-best' : '' ?>" style="width:<?= $mae === null ? 0 : round(100 * $mae / $maximo, 2) ?>%"></div></div><strong><?= e($numeroComparado($mae)) ?></strong></div>
    <?php endforeach; ?>
    </div><p class="comparison-caption">MAE de validación (<?= e($unidadComparada) ?>): una barra más corta representa menos error. Se utiliza para elegir; la prueba final sirve para comprobar la generalización.</p>
    <div class="comparison-cards">
    <?php $indiceModelo = 0; foreach ($guia as $nombreModelo => $fichaModelo): $pruebaModelo = $pruebas[$nombreModelo] ?? [];
        $maeReferencia = $pruebas['Referencia semanal'][1] ?? null;
        $mejoraReferencia = is_numeric($maeReferencia) && $maeReferencia > 0 && isset($pruebaModelo[1]) ? 100 * ($maeReferencia - $pruebaModelo[1]) / $maeReferencia : null;
        $diagnosticoModelo = $modeloComparado['diagnosticos'][$nombreModelo] ?? [];
        $estadoModelo = !isset($pruebaModelo[1]) || !is_numeric($maeReferencia) ? 'Sin evaluación' : ($pruebaModelo[1] >= $maeReferencia ? 'No supera la referencia' : (($pruebaModelo[5] ?? 100) >= 50 ? 'Mejora con error alto' : 'Mejora histórica'));
    ?>
        <article class="comparison-card <?= $nombreModelo === $mejor ? 'comparison-recommended' : '' ?>"><div class="comparison-card-top"><span class="control-model-number"><?= str_pad((string)++$indiceModelo, 2, '0', STR_PAD_LEFT) ?></span><?php if ($nombreModelo === $mejor): ?><span class="comparison-badge">Menor error de validación</span><?php endif; ?></div><h3><?= e($nombreModelo) ?></h3>
        <div class="comparison-verdict"><strong><?= e($estadoModelo) ?></strong><span><?= $mejoraReferencia === null ? 'Mejora porcentual no calculable' : e($numeroComparado($mejoraReferencia)).'% frente a referencia semanal' ?></span></div>
        <dl><div><dt>MAE · validación (<?= e($unidadComparada) ?>)</dt><dd><?= e($numeroComparado($errores[$nombreModelo] ?? null)) ?></dd></div><div><dt>MAE · prueba (<?= e($unidadComparada) ?>)</dt><dd><?= e($numeroComparado($pruebaModelo[1] ?? null)) ?></dd></div><div><dt>RMSE · prueba (<?= e($unidadComparada) ?>)</dt><dd><?= e($numeroComparado($pruebaModelo[2] ?? null)) ?></dd></div><div><dt>WAPE · prueba (%)</dt><dd><?= e($numeroComparado($pruebaModelo[5] ?? null)) ?></dd></div></dl>
        <p>Días de prueba dentro de ±20%: <strong><?= e($numeroComparado($pruebaModelo[4] ?? null)) ?><?= isset($pruebaModelo[4]) ? '%' : '' ?></strong></p><meter min="0" max="100" value="<?= max(0, min(100, (float)($pruebaModelo[4] ?? 0))) ?>" aria-label="Días de prueba dentro de veinte por ciento para <?= e($nombreModelo) ?>"></meter><p><?= e($fichaModelo['tienda']) ?></p><details><summary>Diagnóstico para decidir</summary><p><?= isset($diagnosticoModelo['direccion']) ? e($diagnosticoModelo['direccion']).' · sesgo: '.e($numeroComparado($diagnosticoModelo['sesgo_porcentaje'] ?? null)).'%. El sesgo puede compensar errores opuestos.' : 'Entrena de nuevo para publicar el diagnóstico de sobreestimación y subestimación.' ?></p><p><?= $estadoModelo === 'Mejora histórica' ? 'Úsalo como apoyo al plan de ventas y despachos, y comprueba después los valores reales.' : 'Revisa los resultados reales antes de comprometer presupuesto o capacidad; esta evaluación no respalda una decisión automática.' ?></p></details></article>
    <?php endforeach; ?>
    </div><p class="comparison-caption">MAE y RMSE: menor es mejor. WAPE es error porcentual y puede superar 100%; no equivale a precisión. El margen ±20% describe días históricos, no una probabilidad futura. Una referencia estadística puede superar a los cinco ML.</p>
</section>
<?php endforeach; ?>
