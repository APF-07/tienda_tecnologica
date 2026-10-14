<?php
function clave_catalogo(string $texto): string {
    return strtolower(strtr(trim($texto),['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u']));
}
function categoria_por_nombre(string $nombre): ?string {
    $n=clave_catalogo($nombre);
    foreach([
        'laptops'=>'/\b(laptop|portatil|notebook)\b/',
        'celulares'=>'/\b(smartphone|celular|telefono|iphone)\b/',
        'computadoras'=>'/\b(pc|computadora|desktop)\b/',
        'monitores'=>'/\bmonitor\b/',
        'almacenamiento'=>'/\b(ssd|disco|pendrive|usb)\b/',
        'componentes'=>'/\b(ram|placa madre|motherboard|procesador|gpu|tarjeta grafica|fuente)\b/',
        'perifericos'=>'/\b(teclado|mouse|auriculares|audifonos|parlante|microfono|router|webcam)\b/'
    ] as $categoria=>$patron) if(preg_match($patron,$n)) return $categoria;
    return null;
}
function politica_marcas(): array {
    // Reglas del catálogo de esta tienda; se pueden ampliar al incorporar nuevas líneas.
    return [
        'laptops'=>['asus','lenovo','hp','dell','msi','samsung','apple','acer'],
        'computadoras'=>['asus','lenovo','hp','dell','msi','apple','acer'],
        'monitores'=>['asus','lenovo','hp','dell','msi','samsung','lg','acer'],
        'componentes'=>['asus','msi','kingston','samsung','intel','amd','corsair','gigabyte','nvidia'],
        'almacenamiento'=>['kingston','samsung','sandisk','western digital','seagate','crucial'],
        'perifericos'=>['logitech','asus','msi','hp','dell','lenovo','samsung','micronics','razer','corsair'],
        'celulares'=>['samsung','apple','xiaomi','motorola','honor','huawei','nokia','oneplus']
    ];
}
function marca_admitida(string $categoria,string $marca): bool {
    $politica=politica_marcas();$clave=clave_catalogo($categoria);
    return !isset($politica[$clave]) || in_array(clave_catalogo($marca),$politica[$clave],true);
}
function politica_subtipos(): array {
    return ['placa madre'=>['asus','msi','gigabyte'], 'motherboard'=>['asus','msi','gigabyte'],
        'memoria ram'=>['kingston','samsung','corsair','crucial'], 'procesador'=>['intel','amd'],
        'tarjeta grafica'=>['asus','msi','gigabyte','nvidia','amd'], 'gpu'=>['asus','msi','gigabyte','nvidia','amd']];
}
function marca_admitida_producto(string $nombre,string $categoria,string $marca): bool {
    if(!marca_admitida($categoria,$marca)) return false;
    if(clave_catalogo($categoria)==='componentes') foreach(politica_subtipos() as $tipo=>$marcas) {
        if(str_contains(clave_catalogo($nombre),$tipo)) return in_array(clave_catalogo($marca),$marcas,true);
    }
    return true;
}
function validar_clasificacion_producto(string $nombre,string $categoria,string $marca): void {
    $esperada=categoria_por_nombre($nombre);
    if($esperada && $esperada!==clave_catalogo($categoria)) throw new RuntimeException('El nombre del producto corresponde a otra categoría. Revisa la categoría seleccionada.');
    if(!marca_admitida_producto($nombre,$categoria,$marca)) throw new RuntimeException('Esta marca no pertenece a la línea de este producto en el catálogo de la tienda.');
}
