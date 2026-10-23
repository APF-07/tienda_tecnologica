<?php
require_once __DIR__.'/controles_negocio.php';
$informeRec=informe_controles_negocio();$idsRec=[];
foreach($informeRec['controles']['recomendaciones']['filas']??[] as $filaRec){if($filaRec['id']===(int)$id_producto){$idsRec=array_slice($filaRec['recomendaciones'],0,5);break;}}
if($idsRec){
    $marcasRec=implode(',',array_fill(0,count($idsRec),'?'));
    $qRec=$conexion->prepare("SELECT p.id_producto,p.nombre,p.precio FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca WHERE p.id_producto IN ($marcasRec) AND p.estado=1 AND p.stock>0 AND c.estado=1 AND m.estado=1");$qRec->execute($idsRec);$productosRec=$qRec->fetchAll();
    if($productosRec): ?>
<section class="related-ml" aria-label="Productos relacionados"><h2>También puedes explorar</h2><p>Sugerencias por características y compras anteriores.</p><div class="related-ml-grid"><?php foreach($productosRec as $productoRec): ?><a href="producto.php?id=<?= (int)$productoRec['id_producto'] ?>"><strong><?= e($productoRec['nombre']) ?></strong><span><?= e(dinero($productoRec['precio'])) ?></span><small>Ver producto ↗</small></a><?php endforeach; ?></div></section>
<?php endif; }
