<?php
require_once __DIR__.'/permisos.php';
require_once __DIR__.'/catalogo.php';
require_once __DIR__.'/chat_cliente.php';
function imagen_producto(array $producto): string {
    $imagen=basename($producto['imagen'] ?? '');
    if($imagen && is_file(__DIR__.'/../uploads/productos/'.$imagen)) return ruta('uploads/productos/'.$imagen);
    $categoria=clave_catalogo($producto['categoria'] ?? 'laptops');
    if(!is_file(__DIR__.'/../uploads/ilustraciones/'.$categoria.'.svg')) $categoria='laptops';
    return ruta('uploads/ilustraciones/'.$categoria.'.svg');
}
function render_store_header(string $activo = ''): void {
    $cantidad = array_sum(array_column($_SESSION['carrito'] ?? [], 'cantidad'));
    echo '<header class="store-header"><a class="store-logo" href="'.e(ruta('tienda.php')).'">TechStore <span aria-hidden="true">🚀</span></a><nav class="store-nav" aria-label="Navegación principal">';
    foreach(['tienda.php'=>'Catálogo','rastrear.php'=>'Rastrear pedido','carrito.php'=>'Carrito'] as $archivo=>$nombre) {
        echo '<a href="'.e(ruta($archivo)).'"'.($archivo===$activo?' class="active" aria-current="page"':'').'>'.$nombre;
        if($archivo==='carrito.php') echo ' <span class="cart-badge">'.$cantidad.'</span>';
        echo '</a>';
    }
    echo '<a href="'.e(ruta('index.php')).'">Administración</a></nav></header>';
    render_chat_cliente();
}
function render_admin_sidebar(string $activo = ''): void {
    $rol=$_SESSION['rol']??'administrador';
    if($rol!=='administrador'){
        echo '<aside class="admin-sidebar" aria-label="Menú del equipo"><div class="sidebar-brand">TechStore<small>'.e(ucfirst($rol)).'</small></div><nav><ul class="sidebar-menu"><li><a href="'.e(ruta('admin/gestion.php')).'">Gestión de la tienda</a></li>';
        if($rol==='vendedor')echo '<li><a href="'.e(ruta('admin/pedidos/listar.php')).'">Pedidos</a></li><li><a href="'.e(ruta('admin/clientes/listar.php')).'">Clientes</a></li>';
        echo '</ul></nav><div class="sidebar-footer"><a href="'.e(ruta('tienda.php')).'">Ver tienda ↗</a><form method="POST" action="'.e(ruta('logout.php')).'">'.csrf_campo().'<button type="submit" class="btn-logout">Cerrar sesión</button></form></div></aside>';return;
    }
    $iconos = [
        'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'productos'=>'<path d="m12 3 9 5v8l-9 5-9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/>',
        'categorias'=>'<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 10h18M9 10v10"/>',
        'marcas'=>'<path d="M3 3h8l10 10-8 8L3 11Z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'pedidos'=>'<path d="M7 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="7" y="3" width="10" height="4" rx="1"/><path d="m8 14 3 3 5-6"/>',
        'clientes'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5.65"/>',
        'ml'=>'<path d="M4 3v18h17M8 16l4-5 4 2 5-7"/>',
        'asistente'=>'<path d="M21 11a8 8 0 0 1-8 8H7l-4 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/><path d="M7 9h10M7 13h6"/>',
        'tienda'=>'<path d="M3 10h18l-2-7H5ZM5 10v11h14V10M9 21v-7h6v7"/>',
        'salir'=>'<path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4M9 12h12m-4-4 4 4-4 4"/>'
    ];
    $icono = static function(string $clave) use ($iconos): string {
        return '<svg class="menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$iconos[$clave].'</svg>';
    };
    echo '<aside class="admin-sidebar" aria-label="Menú de administración"><div class="sidebar-brand"><span class="admin-brand-mark" aria-hidden="true">T</span><div>Tech<span class="brand-accent">Store</span><small>ADMINISTRACIÓN</small></div></div><nav aria-label="Secciones del administrador"><ul class="sidebar-menu">';
    $menus=['dashboard'=>'Panel de control','productos'=>'Productos','categorias'=>'Categorías','marcas'=>'Marcas','pedidos'=>'Pedidos','clientes'=>'Clientes'];
    foreach($menus as $clave=>$nombre) {
        if($clave==='productos')echo '<li class="sidebar-section" role="presentation">Catálogo</li>';
        if($clave==='pedidos')echo '<li class="sidebar-section" role="presentation">Ventas</li>';
        $archivo=$clave==='dashboard'?'admin/dashboard.php':'admin/'.$clave.'/listar.php';
        echo '<li><a href="'.e(ruta($archivo)).'"'.($clave===$activo?' class="active" aria-current="page"':'').'>'.$icono($clave).'<span class="menu-label">'.$nombre.'</span></a></li>';
    }
    $esMl = $activo === 'ml' || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/ml/') !== false;
    echo '<li class="sidebar-section" role="presentation">Operación</li>';
    echo '<li><a href="'.e(ruta('admin/gestion.php')).'"'.($activo==='gestion'?' class="active" aria-current="page"':'').'>'.$icono('productos').'<span class="menu-label">Inventario y proveedores</span></a></li>';
    echo '<li><a href="'.e(ruta('admin/agentes/index.php')).'"'.($activo==='agentes'?' class="active" aria-current="page"':'').'>'.$icono('categorias').'<span class="menu-label">Agentes y alertas</span></a></li>';
    echo '<li><a href="'.e(ruta('admin/operaciones.php')).'"'.($activo==='operaciones'?' class="active" aria-current="page"':'').'>'.$icono('ml').'<span class="menu-label">Acciones y resultados</span></a></li>';
    echo '<li class="sidebar-section" role="presentation">Inteligencia</li>';
    $esControl=basename($_SERVER['SCRIPT_NAME']??'')==='control.php'&&$esMl;
    echo '<li><a href="'.e(ruta('admin/ml/controles.php')).'"'.(basename($_SERVER['SCRIPT_NAME']??'')==='controles.php'?' class="active" aria-current="page"':'').'>'.$icono('ml').'<span class="menu-label">Cinco controles ML</span></a></li>';
    echo '<li><a href="'.e(ruta('admin/asistente/index.php')).'"'.($activo==='asistente'?' class="active" aria-current="page"':'').'>'.$icono('asistente').'<span class="menu-label">Asistente de gestión</span></a></li>';
    echo '<li class="menu-analytics"><a href="'.e(ruta('admin/ml/predicciones.php')).'"'.($esMl&&!$esControl?' class="active" aria-current="page"':'').'>'.$icono('ml').'<span class="menu-label">Predicciones y analítica</span></a></li>';
    echo '<li><a href="'.e(ruta('admin/ml/control.php')).'"'.($esControl?' class="active" aria-current="page"':'').'>'.$icono('categorias').'<span class="menu-label">Configurar modelos ML</span></a></li><li class="menu-store"><a href="'.e(ruta('tienda.php')).'">'.$icono('tienda').'<span class="menu-label">Ver tienda</span><span class="menu-external" aria-hidden="true">↗</span></a></li></ul></nav><div class="sidebar-footer">';
    echo '<form action="'.e(ruta('logout.php')).'" method="POST">'.csrf_campo().'<button class="btn-logout" type="submit">'.$icono('salir').'<span>Cerrar sesión</span></button></form></div></aside>';
}
function render_paginacion(int $pagina,int $paginas,int $total): void {
    echo '<nav class="admin-pagination" aria-label="Páginas"><span>'.$total.' registros · Página '.$pagina.' de '.$paginas.'</span>';
    foreach(['Anterior'=>$pagina-1,'Siguiente'=>$pagina+1] as $texto=>$destino) if($destino>=1 && $destino<=$paginas) {
        $parametros=$_GET; unset($parametros['msg'],$parametros['error']); $parametros['pagina']=$destino;
        echo '<a class="btn-action" href="?'.e(http_build_query($parametros)).'">'.$texto.'</a>';
    }
    echo '</nav>';
}
