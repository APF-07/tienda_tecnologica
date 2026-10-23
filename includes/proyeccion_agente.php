<?php
function resumen_proyeccion_agente(?string $directorio=null,?string $fecha=null): array {
    $hoy=substr($fecha??date('Y-m-d'),0,10);$salida=[];
    foreach(['pedidos','ventas'] as $tipo){
        $ruta=($directorio??__DIR__.'/../ml').'/cache_todos_'.$tipo.'.json';
        $informe=is_file($ruta)?json_decode((string)file_get_contents($ruta),true):null;
        $dato=['tipo'=>$tipo,'estado'=>'Sin informe','generado'=>null,'fecha_corte'=>null,'dias'=>[],'valores'=>[],'metricas'=>[],'calidad'=>null];
        if(is_array($informe)&&($informe['schema_version']??0)===2){
            $dato['generado']=$informe['generado']??null;$dato['fecha_corte']=$informe['fecha_corte']??null;$dato['estado']='Sin pronóstico';
            foreach($informe['modelos']??[] as $modelo)foreach($modelo['graficos']??[] as $g){
                if(!str_starts_with($g['titulo']??'','Próximos 7 días'))continue;
                $dias=$g['datos']['labels']??[];$valores=$g['datos']['valores']??[];
                if(count($dias)!==count($valores)||!count($dias))continue;
                $dato['dias']=$dias;$dato['valores']=$valores;$dato['metricas']=$modelo['metricas']??[];$dato['calidad']=$modelo['calidad']??null;
                $dato['estado']=min($dias)>=$hoy&&$dato['fecha_corte']!==null&&$dato['fecha_corte']>=(new DateTimeImmutable($hoy))->modify('-2 days')->format('Y-m-d')?'Vigente':'Desactualizado';
            }
        }
        $salida[$tipo]=$dato;
    }
    return $salida;
}
function resumen_modelos_individuales(?string $directorio=null): array {
    $salida=[];
    foreach(['clientes','productos','pedidos'] as $area){$ruta=($directorio??__DIR__.'/../ml').'/cache_todos_'.$area.'.json';$informe=is_file($ruta)?json_decode((string)file_get_contents($ruta),true):null;
        foreach($informe['modelos']??[] as $m)if(isset($m['tipo_modelo']))$salida[$m['tipo_modelo']]=['nombre'=>$m['nombre'],'estado'=>$m['estado'],'fecha_corte'=>$informe['fecha_corte']??null,'generado'=>$informe['generado']??null,'resultados'=>count($m['predicciones']??[]),'metricas'=>$m['metricas']??[]];
    }
    return $salida;
}
