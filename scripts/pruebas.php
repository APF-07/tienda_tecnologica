<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../includes/compras.php';
require __DIR__ . '/../includes/pedidos.php';
require __DIR__ . '/../includes/analitica.php';
require __DIR__ . '/../includes/gestion_productos.php';
require __DIR__ . '/../includes/gestion_clasificaciones.php';
require __DIR__ . '/../includes/carrito.php';
require __DIR__ . '/../includes/listados.php';
function comprobar($condicion, $mensaje) { if (!$condicion) throw new RuntimeException('FALLO: '.$mensaje); echo "OK: $mensaje\n"; }
// Las tablas temporales ocultan las reales únicamente en esta conexión y se eliminan al cerrarla.
preparar_registro_agentes($conexion);
foreach (['clientes','productos','pedidos','detalle_pedidos','categorias','marcas','alertas_operativas','agentes_inventario','agentes_estado','agentes_ejecuciones','movimientos_stock','proveedores','compras_reposicion','recepciones_compra','devoluciones','reembolsos','gestion_eventos','gestion_config'] as $tabla) {
    $definicion = $conexion->query("SHOW CREATE TABLE $tabla")->fetch()['Create Table'];
    $definicion = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $definicion);
    $definicion = preg_replace('/^\s*CONSTRAINT[^\r\n]+\R/m', '', $definicion);
    $definicion = preg_replace('/,\s*\)/', "\n)", $definicion);
    $conexion->exec($definicion);
}
$conexion->exec("INSERT INTO categorias(id_categoria,nombre) VALUES(1,'Prueba')");
$conexion->exec("INSERT INTO marcas(id_marca,nombre) VALUES(1,'Prueba')");
$conexion->exec("INSERT INTO productos(id_producto,codigo,nombre,id_categoria,id_marca,precio,stock) VALUES(1,'TEST','Equipo prueba',1,1,100,5)");
$datos=['nombre'=>'Cliente','apellido'=>'Prueba','correo'=>'prueba@example.test','telefono'=>'999999999','dni'=>'12345678','direccion'=>'Dirección uno','tipo_envio'=>'fuera','metodo_pago'=>'Contraentrega'];
$carrito=[1=>['id_producto'=>1,'nombre'=>'Equipo prueba','cantidad'=>2,'precio'=>'100.00']];
$primero=registrar_compra($conexion,$carrito,$datos,static fn($db)=>null);
comprobar((float)$conexion->query("SELECT total FROM pedidos WHERE id_pedido=$primero")->fetchColumn()===215.0,'Total con envío y descuento de stock');
comprobar((int)$conexion->query('SELECT stock FROM productos WHERE id_producto=1')->fetchColumn()===3,'Stock descontado una vez');
$datos['direccion']='Dirección dos'; $carrito[1]['cantidad']=1;
$segundo=registrar_compra($conexion,$carrito,$datos,static fn($db)=>null);
comprobar((int)$conexion->query('SELECT COUNT(*) FROM clientes')->fetchColumn()===1,'Segunda compra reutiliza el mismo correo');
comprobar(str_contains($conexion->query("SELECT cliente_direccion FROM pedidos WHERE id_pedido=$primero")->fetchColumn(),'Dirección uno'),'Entrega histórica conservada');
comprobar(str_contains($conexion->query("SELECT cliente_direccion FROM pedidos WHERE id_pedido=$segundo")->fetchColumn(),'Dirección dos'),'Nueva dirección guardada por pedido');
cambiar_estado_pedido($conexion,$primero,'Cancelado');
cambiar_estado_pedido($conexion,$primero,'Cancelado');
comprobar((int)$conexion->query('SELECT stock FROM productos WHERE id_producto=1')->fetchColumn()===4,'Cancelar dos veces no duplica devolución');
$conexion->exec('UPDATE productos SET stock=0 WHERE id_producto=1');
try { cambiar_estado_pedido($conexion,$primero,'Confirmado'); throw new LogicException('Se permitió reactivar sin stock'); } catch (RuntimeException $e) { comprobar(true,'Reactivación rechazada sin stock'); }
comprobar($conexion->query("SELECT estado FROM pedidos WHERE id_pedido=$primero")->fetchColumn()==='Cancelado','Reactivación fallida conserva el estado');
$conexion->exec('UPDATE productos SET stock=3, precio=120 WHERE id_producto=1');
try { registrar_compra($conexion,$carrito,$datos,static fn($db)=>null); throw new LogicException('Se permitió precio antiguo'); } catch (RuntimeException $e) { comprobar($carrito[1]['precio']==='120.00','Precio cambiado requiere nueva confirmación'); }
$conexion->exec('UPDATE productos SET estado=0 WHERE id_producto=1');
try { registrar_compra($conexion,$carrito,$datos,static fn($db)=>null); throw new LogicException('Se compró producto inactivo'); } catch (RuntimeException $e) { comprobar(true,'Compra rechazada para producto inactivo'); }
comprobar((int)$conexion->query('SELECT COUNT(*) FROM pedidos')->fetchColumn()===2,'Compras fallidas no crean pedidos');
comprobar(csv_seguro('=1+1')==="'=1+1",'Exportación CSV neutraliza fórmulas');
foreach(['ventas','productos','clientes','pedidos'] as $tipo) comprobar(isset(informe_analitica($conexion,$tipo)['graficos']),"Analítica $tipo funciona con datos de prueba");
comprobar(precio_centimos('0.10')*3===30 && decimal_centimos(30)==='0.30','Importes calculados en céntimos exactos');
try { validar_compra(datos_compra(array_merge($datos,['dni'=>'123']))); throw new LogicException('Se permitió DNI corto'); } catch(RuntimeException $e) { comprobar(true,'DNI inválido rechazado'); }
try { validar_producto(datos_producto(['codigo'=>'ABC','nombre'=>'Prueba','precio'=>'-1','stock'=>'1','id_categoria'=>'1','id_marca'=>'1'])); throw new LogicException('Se permitió precio negativo'); } catch(RuntimeException $e) { comprobar(true,'Precio negativo rechazado'); }
$conexion->exec('UPDATE productos SET estado=1,stock=8,precio=0.10 WHERE id_producto=1');
$nuevoCarrito=[]; agregar_carrito($conexion,$nuevoCarrito,1,3);
comprobar($nuevoCarrito[1]['cantidad']===3,'Carrito agrega productos disponibles');
try { agregar_carrito($conexion,$nuevoCarrito,1,10); throw new LogicException('Se permitió exceder stock'); } catch(RuntimeException $e) { comprobar($nuevoCarrito[1]['cantidad']===3,'Carrito rechaza exceso sin alterar cantidad'); }
$conexion->exec('UPDATE productos SET stock=2 WHERE id_producto=1');
$avisos=sincronizar_carrito($conexion,$nuevoCarrito);
comprobar($nuevoCarrito[1]['cantidad']===2 && $avisos!==[],'Carrito actualiza stock y comunica el ajuste');
$tercero=registrar_compra($conexion,$nuevoCarrito,$datos,static fn($db)=>null);
comprobar((string)$conexion->query("SELECT total FROM pedidos WHERE id_pedido=$tercero")->fetchColumn()==='15.20','Compra conserva precisión con importes decimales');
$datosProducto=datos_producto(['codigo'=>'SKU-NUEVO','nombre'=>'Producto nuevo','descripcion'=>'','precio'=>'25.50','stock'=>'7','id_categoria'=>'1','id_marca'=>'1','estado'=>'1']);
$nuevoId=guardar_producto($conexion,$datosProducto);
$datosProducto['stock']='12';guardar_producto($conexion,$datosProducto,null,$nuevoId);
comprobar((int)$conexion->query("SELECT stock FROM productos WHERE id_producto=$nuevoId")->fetchColumn()===12,'Alta y edición de productos');
try { guardar_producto($conexion,$datosProducto); throw new LogicException('Se permitió SKU duplicado'); } catch(PDOException $e) { comprobar((int)$conexion->query('SELECT COUNT(*) FROM productos')->fetchColumn()===2,'SKU duplicado no crea productos'); }
$lista=listar_admin($conexion,'productos',['q'=>'SKU-NUEVO','estado'=>'1']);
comprobar($lista['total']===1 && $lista['filas'][0]['codigo']==='SKU-NUEVO','Búsqueda y filtro de productos');
$lista=listar_admin($conexion,'pedidos',['estado'=>'Cancelado']);
comprobar($lista['total']===1,'Filtro de pedidos cancelados');
$lista=listar_admin($conexion,'clientes',['q'=>'prueba@example.test']);
comprobar($lista['total']===1,'Búsqueda de clientes');
guardar_clasificacion($conexion,'categorias',['nombre'=>'Categoría nueva','descripcion'=>'Demostración']);
comprobar((int)$conexion->query('SELECT COUNT(*) FROM categorias')->fetchColumn()===2,'Alta de categorías');
try { guardar_clasificacion($conexion,'marcas',['nombre'=>str_repeat('a',101)]); throw new LogicException('Se permitió nombre largo'); } catch(RuntimeException $e) { comprobar(true,'Clasificaciones validan longitud'); }
comprobar(categoria_por_nombre('Laptop Pro Edición 2026')==='laptops','Nombre de laptop se clasifica como laptop');
comprobar(categoria_por_nombre('Memoria RAM 16GB')==='componentes','RAM se clasifica como componente');
try { validar_clasificacion_producto('Laptop ASUS','Componentes','ASUS'); throw new LogicException('Se permitió laptop como componente'); } catch(RuntimeException $e) { comprobar(true,'Se rechazan laptops en Componentes'); }
try { validar_clasificacion_producto('Smartphone Ultra','Celulares','ASUS'); throw new LogicException('Se permitió celular ASUS'); } catch(RuntimeException $e) { comprobar(true,'Se rechazan celulares ASUS en este catálogo'); }
validar_clasificacion_producto('Smartphone Ultra','Celulares','Samsung');
comprobar(true,'Se admiten celulares de una marca compatible');
proveedor_gestion($conexion,['nombre'=>'Proveedor integración temporal','contacto'=>'','plazo'=>'2']);$proveedorPrueba=$conexion->query('SELECT id FROM proveedores')->fetchColumn();
$ordenPrueba=crear_reposicion($conexion,['proveedor'=>$proveedorPrueba,'producto'=>$nuevoId,'cantidad'=>5,'costo'=>'10.25'],1);recibir_reposicion($conexion,$ordenPrueba,2,'GUIA-TEMP-1',1);recibir_reposicion($conexion,$ordenPrueba,3,'GUIA-TEMP-2',1);
comprobar((int)$conexion->query("SELECT stock FROM productos WHERE id_producto=$nuevoId")->fetchColumn()===17,'MySQL recibe reposición parcial sin duplicar ni perder stock');
comprobar((int)$conexion->query("SELECT SUM(cantidad) FROM movimientos_stock WHERE id_producto=$nuevoId")->fetchColumn()===17,'Libro de inventario MySQL cuadra tras alta, ajuste y recepción');
cambiar_estado_pedido($conexion,$tercero,'Entregado');$retornoPrueba=solicitar_devolucion($conexion,['pedido'=>$tercero,'producto'=>1,'cantidad'=>1,'motivo'=>'Devolución de integración temporal'],1);resolver_devolucion($conexion,$retornoPrueba,true,true,1);registrar_reembolso($conexion,$retornoPrueba,5,'REEMBOLSO-TEMP-1',1);
comprobar((int)$conexion->query('SELECT stock FROM productos WHERE id_producto=1')->fetchColumn()===1,'MySQL reintegra devolución aceptada una sola vez');
comprobar((int)$conexion->query('SELECT SUM(importe) FROM reembolsos')->fetchColumn()===5,'Reembolso externo conserva céntimos exactos en MySQL');
try{cambiar_estado_pedido($conexion,$tercero,'Cancelado');throw new LogicException('Cancelación duplicaría el retorno');}catch(RuntimeException $e){comprobar(true,'Impide cancelar un pedido con devolución y duplicar stock');}
echo "Todas las pruebas pasaron. No se modificaron los registros de la tienda.\n";
