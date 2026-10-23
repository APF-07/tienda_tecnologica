<?php
require_once '../includes/verificar_admin.php';
require_once '../config/conexion.php';
require_once '../includes/dashboard_vivo.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);exit;}
session_write_close();
try{echo json_encode(['ok'=>true,'datos'=>datos_dashboard_vivo($conexion)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
catch(Throwable $e){error_log('Dashboard vivo: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'mensaje'=>'No se pudo consultar el panel. Se conservan los últimos datos visibles.']);}
