<?php
require_once __DIR__.'/../config/app.php';
function sincronizar_carrito(PDO $db,array &$carrito): array {
    if(!$carrito) return [];
    $ids=array_map('intval',array_keys($carrito));
    $stmt=$db->prepare('SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca WHERE p.id_producto IN ('.implode(',',array_fill(0,count($ids),'?')).') AND p.estado=1 AND c.estado=1 AND m.estado=1');
    $stmt->execute($ids); $productos=array_column($stmt->fetchAll(),null,'id_producto');
    $avisos=[];
    foreach($carrito as $id=>&$item) {
        $producto=$productos[$id] ?? null;
        if(!$producto || $producto['stock']<=0) { unset($carrito[$id]); $avisos[]='Se retiró un producto que ya no está disponible.'; continue; }
        if($item['cantidad']>$producto['stock']) { $item['cantidad']=(int)$producto['stock']; $avisos[]='Se ajustó una cantidad al stock disponible.'; }
        if(precio_centimos($item['precio'])!==precio_centimos($producto['precio'])) $avisos[]='Se actualizó el precio de un producto. Revisa el total.';
        $item['precio']=$producto['precio']; $item['nombre']=$producto['nombre']; $item['imagen']=$producto['imagen']; $item['categoria']=$producto['categoria']; $item['stock']=(int)$producto['stock'];
    }
    unset($item);
    return array_unique($avisos);
}
function agregar_carrito(PDO $db,array &$carrito,int $id,int $cantidad): void {
    if($id<1 || $cantidad<1) throw new RuntimeException('Selecciona un producto y una cantidad válida.');
    $stmt=$db->prepare('SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias c ON c.id_categoria=p.id_categoria JOIN marcas m ON m.id_marca=p.id_marca WHERE p.id_producto=? AND p.estado=1 AND c.estado=1 AND m.estado=1');
    $stmt->execute([$id]);$p=$stmt->fetch();
    if(!$p) throw new RuntimeException('El producto ya no está disponible.');
    $nueva=($carrito[$id]['cantidad'] ?? 0)+$cantidad;
    if($nueva>$p['stock']) throw new RuntimeException('La cantidad solicitada supera el stock disponible.');
    $carrito[$id]=['id_producto'=>$id,'nombre'=>$p['nombre'],'precio'=>$p['precio'],'imagen'=>$p['imagen'],'categoria'=>$p['categoria'],'cantidad'=>$nueva,'stock'=>(int)$p['stock']];
}
function actualizar_carrito(array &$carrito,array $cantidades): void {
    $copia=$carrito;
    foreach($cantidades as $id=>$valor) {
        if(!isset($copia[$id])) continue;
        $cantidad=filter_var($valor,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2147483647]]);
        if($cantidad===false) throw new RuntimeException('Las cantidades deben ser números enteros mayores o iguales que cero.');
        if($cantidad===0) { unset($copia[$id]); continue; }
        if($cantidad>$copia[$id]['stock']) throw new RuntimeException('Una cantidad supera el stock disponible.');
        $copia[$id]['cantidad']=$cantidad;
    }
    $carrito=$copia;
}
