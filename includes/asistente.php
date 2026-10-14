<?php
require_once __DIR__.'/../config/app.php';
require_once __DIR__.'/consultas_asistente.php';
require_once __DIR__.'/asistente_integral.php';
function intencion_asistente(string $pregunta): string {
    $texto = strtolower(strtr($pregunta,['Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Ñ'=>'n','ñ'=>'n']));
    if (preg_match('/\b(borra|borrar|elimina|eliminar|modifica|modificar|actualiza|actualizar|cambia|cambiar|entrena|entrenar|cancela|cancelar)\b/u',$texto)) return 'accion';
    if (preg_match('/\b(cliente|clientes|comprador|compradores)\b/u',$texto) && preg_match('/frecuen|mas.*compr|compr.*mas|mayor.*compr|top.*client|mejor.*client/u',$texto)) return 'comprador_frecuente';
    if (str_contains($texto,'porcentaje') || str_contains($texto,'56,6') || str_contains($texto,'56.6') || str_contains($texto,'precision')) return 'porcentaje';
    if (str_contains($texto,'cancelacion') || str_contains($texto,'riesgo')) return 'cancelacion';
    if (str_contains($texto,'predic') || str_contains($texto,'pronost') || str_contains($texto,'ml') || str_contains($texto,'machine learning')) {
        if (str_contains($texto,'venta')) return 'ml_ventas';
        if (str_contains($texto,'pedido')) return 'ml_pedidos';
        return 'ml_ayuda';
    }
    if (preg_match('/\b(producto|productos|articulo|articulos)\b/u',$texto) && preg_match('/mas.*vend|vend.*mas|mas.*ventas|mayor.*venta|\btop\b/u',$texto))return 'producto_mas_vendido';
    if (str_contains($texto,'venta') || str_contains($texto,'ingreso') || preg_match('/\b(vendi|vendimos|vendido|vendidos|vendemos)\b/u',$texto) || str_contains($texto,'facturacion')) return 'ventas_actuales';
    if (preg_match('/agotad|sin existencias|(?:stock|stokc|inventario).*(?:baj|critic|poc)|(?:baj|poc).*(?:stock|stokc|existencias)/u',$texto))return 'stock';
    if (termino_producto_admin($pregunta)!=='')return 'buscar_productos';
    if (str_contains($texto,'stock') || str_contains($texto,'inventario') || str_contains($texto,'reponer')) return 'stock';
    if (str_contains($texto,'pedido') && (str_contains($texto,'ultimo') || str_contains($texto,'reciente'))) return 'recientes';
    if (str_contains($texto,'pendiente')) return 'pendientes';
    if (str_contains($texto,'pedido')) return 'pedidos';
    if (str_contains($texto,'cliente')) return 'clientes';
    if (str_contains($texto,'resumen') || str_contains($texto,'tienda') || str_contains($texto,'hola') || str_contains($texto,'ayuda')) return 'resumen';
    return 'desconocida';
}
function informe_asistente(string $tipo, ?string $directorio = null): ?array {
    $archivo = ($directorio ?? __DIR__.'/../ml').'/cache_todos_'.$tipo.'.json';
    $informe = is_file($archivo) ? json_decode(file_get_contents($archivo),true) : null;
    return is_array($informe) && ($informe['schema_version'] ?? 0) === 2 ? $informe : null;
}
function requiere_gemini_asistente(string $pregunta): bool {
    if(tipo_consulta_integral($pregunta)!==null)return false;
    return !in_array(intencion_asistente($pregunta),['accion','comprador_frecuente','producto_mas_vendido','ventas_actuales','buscar_productos','stock','pendientes','recientes','pedidos','clientes'],true);
}
function responder_asistente(PDO $db, string $pregunta, ?string $directorio = null): array {
    $integral=respuesta_integral($db,$pregunta,$directorio);if($integral!==null)return $integral;
    $intencion = intencion_asistente($pregunta);
    $respuesta = ['texto'=>'','enlaces'=>[],'fuente'=>'Ayuda del asistente','fecha'=>date('d/m/Y H:i')];
    $enlace = static fn($texto,$url)=>['texto'=>$texto,'url'=>ruta($url)];
    if ($intencion === 'accion') {
        $respuesta['texto']='Este asistente solo consulta información. Para cambiar registros o entrenar modelos, utiliza los formularios del administrador.';
    } elseif ($intencion === 'comprador_frecuente') {
        $max=(int)$db->query("SELECT COALESCE(MAX(compras),0) FROM (SELECT COUNT(*) compras FROM pedidos WHERE estado IN ('Confirmado','En preparación','Enviado','Entregado') GROUP BY id_cliente) frecuencias")->fetchColumn();
        if(!$max){
            $respuesta['texto']='Todavía no hay compras confirmadas para identificar al comprador más frecuente.';
        }else{
            $stmt=$db->prepare("SELECT c.id_cliente,c.nombre,c.apellido,COUNT(*) compras,SUM(p.total) importe FROM clientes c JOIN pedidos p ON p.id_cliente=c.id_cliente WHERE p.estado IN ('Confirmado','En preparación','Enviado','Entregado') GROUP BY c.id_cliente,c.nombre,c.apellido HAVING COUNT(*)=? ORDER BY c.id_cliente LIMIT 6");
            $stmt->bindValue(1,$max,PDO::PARAM_INT);$stmt->execute();$filas=$stmt->fetchAll();
            $respuesta['texto']=count($filas)>1 ? 'Hay un empate en la mayor frecuencia: '.$max.' compras confirmadas por cliente.' : 'El comprador más frecuente tiene '.$max.' compras confirmadas.';
            foreach(array_slice($filas,0,5) as $fila){
                $respuesta['texto'].="\n• ".trim($fila['nombre'].' '.$fila['apellido']).' · Cliente #'.$fila['id_cliente'].' · '.dinero($fila['importe']).' de importe confirmado';
                $respuesta['enlaces'][]=$enlace('Ver cliente #'.$fila['id_cliente'],'admin/clientes/detalle.php?id='.(int)$fila['id_cliente']);
            }
            if(count($filas)>5)$respuesta['texto'].="\nSe muestran 5 de los clientes empatados.";
            $respuesta['texto'].="\n\nSe cuentan pedidos confirmados, en preparación, enviados y entregados de todo el historial. No se cuentan pendientes ni cancelados. Los importes incluyen envío.";
        }
        $respuesta['fuente']='MySQL / MariaDB · frecuencia de compras confirmadas';
    } elseif ($intencion === 'producto_mas_vendido') {
        $datos=consultar_mas_vendido_admin($db,$pregunta);$respuesta['datos']=$datos;
        if(!$datos['productos'])$respuesta['texto']='No hay unidades vendidas en pedidos confirmados para '.$datos['periodo']['etiqueta'].'.';
        else{
            $respuesta['texto']=count($datos['productos'])>1 ? 'Hay un empate entre los productos más vendidos por unidades ('.$datos['periodo']['etiqueta'].'):' : 'El producto más vendido por unidades ('.$datos['periodo']['etiqueta'].') es:';
            foreach($datos['productos'] as $producto){
                $respuesta['texto'].="\n• ".$producto['nombre'].' · Código '.$producto['codigo'].' · '.$producto['unidades'].' unidades en '.$producto['pedidos'].' pedidos · '.dinero($producto['importe']).' en productos';
                $respuesta['enlaces'][]=$enlace('Ver producto '.$producto['codigo'],'admin/productos/editar.php?id='.(int)$producto['id_producto']);
            }
            if($datos['mas_empates'])$respuesta['texto'].="\nSe muestran 5 productos; hay más empatados con la misma cantidad.";
        }
        $respuesta['texto'].="\n\nSe cuentan unidades de pedidos confirmados, en preparación, enviados y entregados; se excluyen pendientes y cancelados. El importe usa los precios del pedido y no incluye envío.";
        $respuesta['fuente']='MySQL / MariaDB · unidades vendidas por producto';
    } elseif ($intencion === 'buscar_productos') {
        $datos=consultar_productos_admin($db,termino_producto_admin($pregunta));$respuesta['datos']=$datos;
        $respuesta['texto']=$datos['total_coincidencias'] ? 'Encontré '.$datos['total_coincidencias'].' productos activos para «'.$datos['busqueda'].'». Se muestran hasta 8, empezando por los de menor stock:' : 'No hay productos activos que coincidan con «'.$datos['busqueda'].'».';
        foreach($datos['productos'] as $producto)$respuesta['texto'].="\n• ".$producto['nombre'].' · '.$producto['stock'].' unidades · '.dinero($producto['precio']);
        $respuesta['fuente']='MySQL / MariaDB · búsqueda de productos activos';
        $respuesta['enlaces'][]=$enlace('Ver productos encontrados','admin/productos/listar.php?estado=1&q='.rawurlencode($datos['busqueda']));
    } elseif ($intencion === 'ventas_actuales') {
        $datos=consultar_ventas_admin($db,$pregunta);$respuesta['datos']=$datos;
        $respuesta['texto']='Ventas de '.$datos['etiqueta'].': '.dinero($datos['importe']).' en '.$datos['pedidos'].' pedidos confirmados. Ticket promedio: '.dinero($datos['ticket_promedio']).'.';
        $respuesta['texto'].="\nPeriodo: ".substr($datos['inicio'],0,10).' hasta '.date('Y-m-d',strtotime($datos['fin'].' -1 day')).'. Incluye envío; excluye pendientes y cancelados. El importe confirmado no acredita que el pago se haya recibido.';
        $respuesta['fuente']='MySQL / MariaDB · ventas por fecha de creación del pedido';
        $respuesta['enlaces'][]=$enlace('Consultar ventas y ML','admin/ml/ventas_ia.php');
    } elseif ($intencion === 'porcentaje') {
        $respuesta['texto']="El porcentaje de pedidos y ventas indica cuántos días de prueba quedaron dentro de ±20% del valor observado. Por ejemplo, 30 de 53 días equivalen a 56,6%. No es una probabilidad de acertar el futuro.\n\nEn cancelaciones se muestran otras medidas: alertas acertadas y cancelaciones detectadas. Silhouette de K-Means no es un porcentaje de precisión.";
        $respuesta['enlaces'][]=$enlace('Ver evaluación ML','admin/ml/pedidos_ia.php#ml-titulo');
    } elseif (in_array($intencion,['ml_pedidos','ml_ventas','cancelacion'],true)) {
        $tipo=$intencion==='ml_ventas'?'ventas':'pedidos';
        $informe=informe_asistente($tipo,$directorio);
        $modelo=$informe['modelos'][$intencion==='cancelacion'?1:0] ?? null;
        $respuesta['enlaces'][]=$enlace('Ver informe completo','admin/ml/'.$tipo.'_ia.php#ml-titulo');
        if (!$modelo || ($modelo['estado'] ?? '')!=='listo') {
            $respuesta['texto']='Todavía no hay un modelo entrenado con datos suficientes. Abre el informe y usa «Entrenar y actualizar» para generar sus resultados.';
        } else {
            $respuesta['fuente']='Informe ML guardado · corte '.$informe['fecha_corte'];
            $respuesta['fecha']=$informe['generado'];
            $m=$modelo['metricas'];
            if ($intencion==='cancelacion') {
                $respuesta['texto']='El modelo evalúa el riesgo usando pedidos entregados y cancelados. En la prueba, acertó el '.($m['Alertas de cancelación acertadas (%)'] ?? '—').'% de sus alertas y detectó el '.($m['Cancelaciones detectadas (%)'] ?? '—')."% de las cancelaciones.\n\nLa puntuación de un pedido pendiente es orientativa. No cancela pedidos automáticamente.";
            } else {
                $unidad=$tipo==='ventas'?'S/':'pedidos';
                $respuesta['texto']='El modelo seleccionado es '.$m['Modelo recomendado'].'. Su error absoluto promedio de prueba es '.$m['MAE de prueba'].' '.$unidad.'.';
                if(isset($modelo['calidad'])) $respuesta['texto'].="\n".$modelo['calidad']['porcentaje'].'% de los días quedó dentro del margen de ±20% ('.$modelo['calidad']['aciertos'].' de '.$modelo['calidad']['dias'].' días). Ese porcentaje mide resultados históricos, no certeza futura.';
                $g=$modelo['graficos'][0]['datos'] ?? [];
                $respuesta['texto'].="\n\nPronóstico guardado para los próximos 7 días:";
                foreach($g['labels'] ?? [] as $i=>$fecha) $respuesta['texto'].="\n".$fecha.': '.($tipo==='ventas'?dinero($g['valores'][$i]):$g['valores'][$i].' pedidos');
                $respuesta['texto'].="\n\nSi ingresaste nuevos pedidos, vuelve a entrenar para actualizar el informe.";
            }
        }
    } elseif ($intencion==='ml_ayuda') {
        $respuesta['texto']='Puedes preguntarme «Explícame la predicción de pedidos», «Explícame la predicción de ventas» o «¿Cómo se evalúa el riesgo de cancelación?». Los modelos agrupan clientes y productos, pronostican pedidos y ventas, y puntúan riesgo; este chat explica sus resultados guardados.';
    } elseif ($intencion==='stock') {
        $texto=normalizar_consulta_admin($pregunta);
        $filtro=preg_match('/agotad|sin (stock|existencias)/u',$texto)?'agotado':(preg_match('/critic|menos de 15/u',$texto)?'critico':'bajo');
        $condicion=['bajo'=>'stock BETWEEN 0 AND 5','critico'=>'stock BETWEEN 0 AND 14','agotado'=>'stock=0'][$filtro];
        $rango=['bajo'=>'stock bajo (de 0 a 5 unidades)','critico'=>'stock crítico (menos de 15 unidades)','agotado'=>'stock agotado (0 unidades)'][$filtro];
        $total=(int)$db->query('SELECT COUNT(*) FROM productos WHERE estado=1 AND '.$condicion)->fetchColumn();
        $filas=$db->query('SELECT nombre,stock FROM productos WHERE estado=1 AND '.$condicion.' ORDER BY stock,id_producto LIMIT 20')->fetchAll();
        $respuesta['texto']='Hay '.$total.' productos activos con '.$rango.'.';
        if($total)$respuesta['texto'].=' Muestro '.count($filas).' de '.$total.', ordenados por menor stock:';
        foreach($filas as $fila) $respuesta['texto'].="\n• ".$fila['nombre'].': '.$fila['stock'].' unidades';
        $criticos=(int)$db->query('SELECT COUNT(*) FROM productos WHERE estado=1 AND stock BETWEEN 0 AND 14')->fetchColumn();
        $respuesta['texto'].="\n\nAnalítica considera críticos los productos con menos de 15 unidades: hay ".$criticos.'. Los productos inactivos no se incluyen.';
        if($total>count($filas))$respuesta['texto'].=' Abre la lista completa para revisar los restantes.';
        $respuesta['enlaces'][]=$enlace('Ver los '.$total.' productos · lista completa','admin/productos/listar.php?estado=1&orden=stock&stock='.$filtro);
        $respuesta['enlaces'][]=$enlace('Ver stock crítico · menos de 15','admin/productos/listar.php?estado=1&orden=stock&stock=critico');
        $respuesta['fuente']='MySQL / MariaDB · productos activos';
    } elseif ($intencion==='pendientes') {
        $stmt=$db->prepare('SELECT COUNT(*) FROM pedidos WHERE estado=?');$stmt->execute(['Pendiente']);
        $respuesta['texto']='Hay '.(int)$stmt->fetchColumn().' pedidos pendientes de revisión.';
        $respuesta['enlaces'][]=$enlace('Abrir pedidos pendientes','admin/pedidos/listar.php?estado=Pendiente');
        $respuesta['fuente']='MySQL / MariaDB · pedidos';
    } elseif ($intencion==='recientes') {
        $filas=$db->query('SELECT id_pedido,total,estado,fecha_pedido FROM pedidos ORDER BY fecha_pedido DESC,id_pedido DESC LIMIT 5')->fetchAll();
        $respuesta['texto']=$filas?'Estos son los 5 pedidos más recientes:':'Todavía no hay pedidos.';
        foreach($filas as $fila) $respuesta['texto'].="\n• #".$fila['id_pedido'].' · '.dinero($fila['total']).' · '.$fila['estado'].' · '.$fila['fecha_pedido'];
        $respuesta['enlaces'][]=$enlace('Ver pedidos','admin/pedidos/listar.php');$respuesta['fuente']='MySQL / MariaDB · pedidos';
    } elseif ($intencion==='pedidos') {
        $filas=$db->query('SELECT estado,COUNT(*) cantidad FROM pedidos GROUP BY estado')->fetchAll();
        $respuesta['texto']=$filas?'Así se distribuyen los pedidos del historial:':'Todavía no hay pedidos.';
        foreach($filas as $fila) $respuesta['texto'].="\n• ".$fila['estado'].': '.$fila['cantidad'];
        $respuesta['enlaces'][]=$enlace('Ver pedidos','admin/pedidos/listar.php');$respuesta['fuente']='MySQL / MariaDB · pedidos';
    } elseif ($intencion==='clientes') {
        $respuesta['texto']='La tienda tiene '.(int)$db->query('SELECT COUNT(*) FROM clientes')->fetchColumn().' clientes registrados. Para consultar su historial, abre la gestión de clientes.';
        $respuesta['enlaces'][]=$enlace('Ver clientes','admin/clientes/listar.php');$respuesta['fuente']='MySQL / MariaDB · clientes';
    } elseif ($intencion==='resumen') {
        $p=(int)$db->query("SELECT COUNT(*) FROM pedidos WHERE estado='Pendiente'")->fetchColumn();
        $s=(int)$db->query('SELECT COUNT(*) FROM productos WHERE estado=1 AND stock BETWEEN 0 AND 5')->fetchColumn();
        $respuesta['texto']="Resumen para revisar hoy:\n• ".$p." pedidos pendientes.\n• ".$s." productos activos con 0 a 5 unidades.\n\nTambién puedo consultar clientes, últimos pedidos o explicar las predicciones de pedidos y ventas.";
        $respuesta['fuente']='MySQL / MariaDB · resumen actual';
    } else {
        $respuesta['texto']='No tengo una consulta local preparada para esa pregunta. Prueba con pedidos pendientes, productos con poco stock, últimos pedidos, clientes o predicciones de pedidos y ventas. La conversación libre requiere que Gemini esté configurado y disponible.';
    }
    return $respuesta;
}
