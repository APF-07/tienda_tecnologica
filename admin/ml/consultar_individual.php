<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';
require_once '../../includes/predicciones_individuales.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
try{$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);$tipo=$_GET['tipo']??'';if(!$id||!is_string($tipo))throw new RuntimeException('Escribe un ID válido.');$r=consultar_prediccion_individual($conexion,$tipo,$id);echo json_encode(['ok'=>true,'resultado'=>$r],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
catch(RuntimeException $e){http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo consultar. Verifica el tipo y el ID.']);}
