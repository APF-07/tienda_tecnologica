<?php
require_once '../../includes/verificar_admin.php';
require_once '../../config/conexion.php';
require_once '../../includes/gestion_clasificaciones.php';
$error='';
$id=intval($_GET['id'] ?? 0);
$marca=['nombre'=>'','descripcion'=>'','estado'=>'1'];
$stmt=$conexion->prepare('SELECT * FROM marcas WHERE id_marca=?');$stmt->execute([$id]);$marca=$stmt->fetch();
if(!$marca) { header('Location: listar.php');exit; }
if($_SERVER['REQUEST_METHOD']==='POST') {
    $entrada=[];foreach(['nombre','descripcion','estado'] as $campo) $entrada[$campo]=is_string($_POST[$campo] ?? null)?$_POST[$campo]:($campo==='estado'?'1':'');
    $marca=array_merge($marca,$entrada);
    try { guardar_clasificacion($conexion,'marcas',$entrada,$id); header('Location: listar.php?msg='.rawurlencode('Registro guardado correctamente.'));exit; }
    catch(Throwable $e) { error_log($e->getMessage());$error=$e instanceof PDOException?($e->getCode()==23000?'Ese nombre ya existe. Utiliza otro.':'No se pudo guardar. Inténtalo nuevamente.'):$e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Marca - TechStore Admin</title>
    <link rel="stylesheet" href="../../css/admin.css">
    <style>
        .form-container { background: #fff; padding: 30px; border-radius: 8px; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #333; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .form-group textarea { resize: vertical; height: 100px; }
        .btn-submit { background-color: #f39c12; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-submit:hover { background-color: #d68910; }
        .btn-back { background-color: #95a5a6; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-left: 10px; }
        .alert-error { background: #fde8e8; color: #c0392b; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    </style>
<link rel="stylesheet" href="../../css/admin-mejoras.css?v=20261013-menu">
<link rel="stylesheet" href="../../css/experiencia.css?v=20261014"><script src="../../js/experiencia.js?v=20261014" defer></script>
<link rel="stylesheet" href="../../css/admin-experiencia.css?v=1"><script src="../../js/admin-experiencia.js?v=1" defer></script>
</head>
<body>

    <?php render_admin_sidebar('marcas'); ?>

    <div class="admin-main">
        <header class="admin-header">
            <h1>Editar Marca</h1>
        </header>

        <main class="admin-content">
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form action="editar.php?id=<?php echo $id; ?>" method="POST"><?php echo csrf_campo(); ?>
                    <div class="form-group">
                        <label for="nombre">Nombre de la Marca *</label>
                        <input type="text" id="nombre" name="nombre" maxlength="100" value="<?php echo htmlspecialchars($marca['nombre']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion"><?php echo htmlspecialchars($marca['descripcion']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="1" <?php echo ($marca['estado'] == 1) ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo ($marca['estado'] == 0) ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">Actualizar Marca</button>
                    <a href="listar.php" class="btn-back">Cancelar</a>
                </form>
            </div>
        </main>
    </div>

</body>
</html>