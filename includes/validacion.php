<?php
require_once __DIR__.'/../config/app.php';
function datos_compra(array $entrada): array {
    $datos=[];
    foreach(['nombre','apellido','correo','telefono','dni','direccion','tipo_envio','metodo_pago'] as $campo) $datos[$campo]=is_string($entrada[$campo] ?? null)?trim($entrada[$campo]):'';
    foreach(['entrega_lat','entrega_lng','entrega_place_id'] as $campo)$datos[$campo]=is_string($entrada[$campo]??null)?trim($entrada[$campo]):'';
    return $datos;
}
function validar_compra(array $datos): void {
    $lat=$datos['entrega_lat']??'';$lng=$datos['entrega_lng']??'';$place=$datos['entrega_place_id']??'';
    if(($lat!==''||$lng!==''||$place!=='')&&(!is_numeric($lat)||!is_numeric($lng)||abs((float)$lat)>90||abs((float)$lng)>180||strlen($place)>255||($place!==''&&!preg_match('/^[A-Za-z0-9_-]+$/D',$place))))throw new RuntimeException('Vuelve a seleccionar la dirección de entrega en las sugerencias del mapa.');
    if(!$datos['nombre'] || !$datos['apellido'] || !$datos['direccion'] || !filter_var($datos['correo'],FILTER_VALIDATE_EMAIL) ||
        !preg_match('/^[0-9+ ()-]{7,20}$/D',$datos['telefono']) || !preg_match('/^[0-9]{8}$/D',$datos['dni']) ||
        longitud_texto($datos['nombre'])>100 || longitud_texto($datos['apellido'])>100 || strlen($datos['correo'])>100 || strlen($datos['direccion'])>2000 ||
        !in_array($datos['tipo_envio'],['dentro','fuera'],true) || !in_array($datos['metodo_pago'],['Yape/Plin','Transferencia BCP','Contraentrega'],true)) throw new RuntimeException('Revisa tu nombre, correo, teléfono, DNI de 8 dígitos, dirección y métodos de envío y pago.');
    if(!MODO_DEMO && $datos['metodo_pago']==='Transferencia BCP' && !getenv('BCP_CUENTA')) throw new RuntimeException('Las transferencias no están habilitadas. Selecciona otro método de pago.');
}
