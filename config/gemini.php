<?php
// Configuración del servidor. Nunca devuelve la clave a JavaScript.
function configuracion_gemini(): array {
    $archivo=__DIR__.'/../.env';
    $contenido=is_file($archivo)?file_get_contents($archivo):'';
    $contenido=preg_replace('/^\xEF\xBB\xBF/','',(string)$contenido);
    $local=$contenido!==''?(@parse_ini_string($contenido,false,INI_SCANNER_RAW) ?: []):[];
    $leer=static function(string $nombre,string $defecto='') use($local):string {
        $valor=getenv($nombre);
        return trim((string)($valor!==false?$valor:($local[$nombre] ?? $defecto)));
    };
    $modelo=$leer('GEMINI_MODEL','gemini-3.5-flash-lite');
    if(!preg_match('/^gemini-[a-z0-9.-]+$/D',$modelo))$modelo='gemini-3.5-flash-lite';
    return ['clave'=>$leer('GEMINI_API_KEY'),'modelo'=>$modelo,
        'habilitado'=>filter_var($leer('GEMINI_ENABLED','true'),FILTER_VALIDATE_BOOLEAN)];
}
