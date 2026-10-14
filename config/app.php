<?php
date_default_timezone_set('America/Lima');
const MONEDA_SIMBOLO = 'S/';
const MONEDA_CODIGO = 'PEN';
define('MODO_DEMO', filter_var(getenv('APP_DEMO') !== false ? getenv('APP_DEMO') : 'true', FILTER_VALIDATE_BOOLEAN));
function e($valor): string { return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8'); }
function precio_centimos($precio): int {
    $texto = (string)$precio;
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $texto)) throw new RuntimeException('El precio debe ser un importe válido con hasta dos decimales.');
    $partes = explode('.', $texto);
    return ((int)$partes[0] * 100) + (int)str_pad($partes[1] ?? '', 2, '0');
}
function decimal_centimos(int $centimos): string { return intdiv($centimos,100) . '.' . str_pad((string)($centimos % 100),2,'0',STR_PAD_LEFT); }
function dinero($importe): string { return MONEDA_SIMBOLO . ' ' . number_format((float)$importe,2); }
function ruta(string $archivo): string {
    $script = str_replace('\\','/',$_SERVER['SCRIPT_NAME'] ?? '/tienda_tecnologica/index.php');
    $base = str_contains($script,'/admin/') ? explode('/admin/',$script)[0] : dirname($script);
    return rtrim($base,'/.') . '/' . ltrim($archivo,'/');
}

function longitud_texto(string $texto): int { return preg_match_all('/./us', $texto) ?: 0; }
