<?php
// La clave de Maps para navegador es independiente de la clave privada de Gemini.
function clave_google_maps(): string {
    $valor=getenv('GOOGLE_MAPS_API_KEY');
    if($valor!==false)return trim($valor);
    $archivo=__DIR__.'/../.env';
    $texto=is_file($archivo)?file_get_contents($archivo):'';
    $texto=preg_replace('/^\xEF\xBB\xBF/','',(string)$texto);
    $valores=$texto!==''?(@parse_ini_string($texto,false,INI_SCANNER_RAW) ?: []):[];
    return trim((string)($valores['GOOGLE_MAPS_API_KEY'] ?? ''));
}
