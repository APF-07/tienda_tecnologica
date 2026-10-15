<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/destino.php';
require __DIR__.'/../includes/validacion.php';
$pruebas=0;
function comprobar_destino(bool $ok,string $nombre):void{global $pruebas;if(!$ok)throw new RuntimeException($nombre);$pruebas++;echo "OK $nombre\n";}
$d=destino_pedido(['direccion'=>'DNI: 12345678 | Envío: Dentro de la ciudad (Gratis) | Dir: Av. Lima 20, Pasco','entrega_lat'=>'-10.6800000','entrega_lng'=>'-76.2500000','entrega_place_id'=>'ChIJ_ejemplo']);
parse_str(parse_url($d['ruta'],PHP_URL_QUERY),$q);
comprobar_destino($q['destination']==='-10.6800000,-76.2500000'&&$q['destination_place_id']==='ChIJ_ejemplo','Ruta conserva coordenadas y lugar seleccionado');
comprobar_destino(!isset($q['origin'])&&$q['api']==='1','Ruta permite usar el origen real del repartidor');
$d=destino_pedido(['direccion'=>'DNI: 12345678 | Envío: Fuera de la ciudad (+S/ 15) | Dir: Calle & Parque 45, Lima']);
comprobar_destino(!$d['preciso']&&$d['direccion']==='Calle & Parque 45, Lima','Pedidos anteriores excluyen DNI y método de envío del destino');
parse_str(parse_url($d['mapa'],PHP_URL_QUERY),$q);
comprobar_destino($q['q']==='Calle & Parque 45, Lima','Caracteres especiales no alteran el destino');
$datos=datos_compra(['nombre'=>'Ana','apellido'=>'Perez','correo'=>'ana@example.com','telefono'=>'999999999','dni'=>'12345678','direccion'=>'Lima','tipo_envio'=>'dentro','metodo_pago'=>'Contraentrega','entrega_lat'=>'999','entrega_lng'=>'-76']);
try{validar_compra($datos);$rechaza=false;}catch(RuntimeException $e){$rechaza=true;}
comprobar_destino($rechaza,'Rechaza coordenadas fuera de rango');
$datos['entrega_lat']='-10.68';$datos['entrega_lng']='-76.25';validar_compra($datos);
comprobar_destino(true,'Acepta coordenadas válidas');
echo "$pruebas pruebas completadas.\n";
