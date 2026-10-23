<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';
require_once '../../includes/listados.php';
$lista=listar_admin($conexion,'clientes',$_GET);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Clientes · TechStore</title><link rel="stylesheet" href="../../css/admin.css"><link rel="stylesheet" href="../../css/admin-mejoras.css?v=20261013-menu"><link rel="stylesheet" href="../../css/experiencia.css?v=20261020"><script src="../../js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="../../css/admin-experiencia.css?v=20261020"><script src="../../js/admin-experiencia.js?v=20261020" defer></script>
</head><body>
<?php render_admin_sidebar('clientes'); ?><div class="admin-main"><header class="admin-header"><h1>Clientes</h1><span><?php echo e($_SESSION['nombre']); ?></span></header><main class="admin-content">
<div class="admin-toolbar"><p>Consulta los datos de contacto y el historial de pedidos de tus clientes.</p><a class="btn-action" href="exportar.php?<?php echo e(http_build_query(array_intersect_key($_GET,array_flip(['q','orden'])))); ?>">Exportar clientes (CSV)</a></div>
<?php render_filtros_admin('clientes',$lista); ?><table><thead><tr><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Registro</th><th>Historial</th></tr></thead><tbody>
<?php foreach($lista['filas'] as $cliente): ?><tr><td><?php echo e($cliente['nombre'].' '.$cliente['apellido']); ?></td><td><?php echo e($cliente['correo']); ?></td><td><?php echo e($cliente['telefono']); ?></td><td><?php echo e($cliente['fecha_registro']); ?></td><td><a class="btn-action" href="detalle.php?id=<?php echo (int)$cliente['id_cliente']; ?>">Ver historial del cliente</a></td></tr><?php endforeach; ?>
<?php if(!$lista['filas']): ?><tr><td colspan="5">No hay clientes que coincidan con la búsqueda.</td></tr><?php endif; ?></tbody></table><?php render_paginacion($lista['pagina'],$lista['paginas'],$lista['total']); ?>
</main></div></body></html>
