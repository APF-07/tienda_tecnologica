<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';
require_once '../../includes/dashboard_predicciones.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
try{$vista=$_GET['vista']??'ventas';if(!is_string($vista))throw new RuntimeException('Vista inválida.');$id=null;if(isset($_GET['id'])&&$_GET['id']!==''){$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id||$id<1)throw new RuntimeException('ID inválido.');}echo json_encode(['ok'=>true,'datos'=>datos_dashboard_predicciones($conexion,$vista,$id)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo cargar la predicción. Verifica la vista, el ID y los informes.']);}
