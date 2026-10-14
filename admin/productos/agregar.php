<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';
require_once '../../includes/gestion_productos.php';
$error = '';
$id = 0;
$producto = ['codigo'=>'','nombre'=>'','descripcion'=>'','id_categoria'=>'','id_marca'=>'','precio'=>'','stock'=>'10','estado'=>'1','imagen'=>null];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $datos = datos_producto($_POST);
    $producto = array_merge($producto,$datos);
    try {
        guardar_producto($conexion,$datos,$_FILES['imagen'] ?? null,$id);
        header('Location: listar.php?msg=' . rawurlencode('Producto guardado correctamente.')); exit;
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $error = $e instanceof PDOException ? ($e->getCode()==23000 ? 'El código ya existe. Utiliza otro SKU.' : 'No se pudo guardar el producto. Inténtalo nuevamente.') : $e->getMessage();
    }
}
$categorias = $conexion->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
$marcas = $conexion->query('SELECT * FROM marcas ORDER BY nombre')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .form-container { background: #fff; padding: 30px; border-radius: 8px; max-width: 700px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .form-row { display: flex; gap: 20px; }
        .form-group { margin-bottom: 20px; flex: 1; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; height: 90px; }
        .btn-submit { background-color: #27ae60; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background-color: #219653; }
        .btn-back { background-color: #95a5a6; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-left: 10px; }
        .alert-error { background: #fde8e8; color: #c0392b; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
<link rel="stylesheet" href="../../css/admin-mejoras.css?v=20261013-menu">
<link rel="stylesheet" href="../../css/experiencia.css?v=20261014"><script src="../../js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="../../css/admin-experiencia.css?v=1"><script src="../../js/admin-experiencia.js?v=1" defer></script>
</head>
<body>

    <?php render_admin_sidebar('productos'); ?>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Registrar Nuevo Producto</h1>
        </header>

        <main class="admin-content">
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="agregar.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_campo(); ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo">Código / SKU *</label>
                            <input type="text" id="codigo" name="codigo" maxlength="50" required placeholder="Ej. LAP-ASUS-01" value="<?php echo e($producto['codigo']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="nombre">Nombre del Producto *</label>
                            <input type="text" id="nombre" name="nombre" maxlength="150" required placeholder="Ej. Laptop ASUS ROG Strix" value="<?php echo e($producto['nombre']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_categoria">Categoría *</label>
                            <select id="id_categoria" name="id_categoria" required>
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>" <?php if ((int)$producto['id_categoria']===(int)$cat['id_categoria']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="id_marca">Marca *</label>
                            <select id="id_marca" name="id_marca" required>
                                <option value="">Seleccione una marca</option>
                                <?php foreach ($marcas as $marca): ?>
                                    <option value="<?php echo $marca['id_marca']; ?>" <?php if ((int)$producto['id_marca']===(int)$marca['id_marca']) echo 'selected'; ?>><?php echo htmlspecialchars($marca['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="precio">Precio (S/) *</label>
                            <input type="number" step="0.01" id="precio" name="precio" min="0.01" max="99999999.99" required placeholder="0.00" value="<?php echo e($producto['precio']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock Inicial *</label>
                            <input type="number" id="stock" name="stock" required min="0" value="<?php echo e($producto['stock']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" placeholder="Especificaciones técnicas completas..."><?php echo e($producto['descripcion']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="imagen">Imagen del Producto (JPG, PNG, WEBP)</label>
                        <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <button type="submit" class="btn-submit">Guardar Producto</button>
                    <a href="listar.php" class="btn-back">Cancelar</a>
                </form>
            </div>
        </main>
    </div>

<script>
 document.addEventListener('DOMContentLoaded',()=>{
   const categoria=document.getElementById('id_categoria'),marca=document.getElementById('id_marca');
   const politica=<?php echo json_encode(politica_marcas(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
   const subtipos=<?php echo json_encode(politica_subtipos(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
   const nombre=document.getElementById('nombre');
   const normalizar=t=>t.normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toLowerCase();
   function filtrar(){const clave=normalizar(categoria.options[categoria.selectedIndex]?.textContent || '');let admitidas=politica[clave];if(clave==='componentes')for(const [tipo,marcas] of Object.entries(subtipos))if(normalizar(nombre.value).includes(tipo)){admitidas=marcas;break;}for(const opcion of marca.options){const permitida=!opcion.value || !admitidas || admitidas.includes(normalizar(opcion.textContent));opcion.disabled=!permitida;opcion.hidden=!permitida;}if(marca.selectedOptions[0]?.disabled)marca.value='';}
   categoria.addEventListener('change',filtrar);nombre.addEventListener('input',filtrar);filtrar();
 });
 </script></body>
</html>