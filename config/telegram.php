<?php
function configuracion_telegram(): array {
    $ruta=__DIR__.'/../.env';$texto=is_file($ruta)?(string)file_get_contents($ruta):'';
    $valores=@parse_ini_string(preg_replace('/^\xEF\xBB\xBF/','',$texto),false,INI_SCANNER_RAW)?:[];
    $leer=static function($nombre)use($valores){$valor=getenv($nombre);return trim((string)($valor!==false?$valor:($valores[$nombre]??'')));};
    return ['token'=>$leer('TELEGRAM_BOT_TOKEN'),'chat_id'=>$leer('TELEGRAM_CHAT_ID')];
}
