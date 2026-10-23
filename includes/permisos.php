<?php
function preparar_roles(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS usuarios_roles (id_usuario INT PRIMARY KEY,rol VARCHAR(20) NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS roles_control (id INT PRIMARY KEY)');
    $db->exec('CREATE TABLE IF NOT EXISTS roles_historial (id VARCHAR(40) PRIMARY KEY,usuario INT NOT NULL,rol VARCHAR(20) NOT NULL,actor INT NOT NULL,fecha VARCHAR(19) NOT NULL)');
    try{$db->exec('INSERT INTO roles_control VALUES(1)');}catch(PDOException $e){if(!in_array((string)$e->getCode(),['23000','23505'],true))throw $e;}
}
function rol_usuario(PDO $db,int $id): ?string {
    $q=$db->prepare('SELECT COALESCE(r.rol,u.rol) rol,u.estado FROM usuarios u LEFT JOIN usuarios_roles r ON r.id_usuario=u.id_usuario WHERE u.id_usuario=?');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);
    return $u&&(int)$u['estado']===1&&in_array($u['rol'],['administrador','vendedor','almacen'],true)?$u['rol']:null;
}
function permite_ruta_equipo(string $rol,string $ruta): bool {
    $ruta=strtolower(str_replace('\\','/',$ruta));if($rol==='administrador')return true;
    $comunes=['gestion.php'];$vendedor=['pedidos/listar.php','pedidos/detalle.php','pedidos/cambiar_estado.php','pedidos/exportar.php','clientes/listar.php','clientes/detalle.php'];
    return in_array($rol,['vendedor','almacen'],true)&&in_array($ruta,array_merge($comunes,$rol==='vendedor'?$vendedor:[]),true);
}
function permite_gestion(string $rol,string $accion): bool {
    if($rol==='administrador')return true;
    return in_array($accion,$rol==='almacen'?['recibir','ajustar','resolver_devolucion']:($rol==='vendedor'?['solicitar_devolucion']:[]),true);
}
function asignar_rol(PDO $db,int $id,string $rol,int $actor): void {
    if(!in_array($rol,['administrador','vendedor','almacen'],true))throw new InvalidArgumentException('Rol inválido.');
    $db->beginTransaction();try{
        $db->query('SELECT id FROM roles_control WHERE id=1'.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetchColumn();
        if(rol_usuario($db,$actor)!=='administrador')throw new InvalidArgumentException('Solo un administrador puede cambiar permisos.');
        if(!rol_usuario($db,$id))throw new InvalidArgumentException('Usuario inactivo o inexistente.');
        $n=(int)$db->query("SELECT COUNT(*) FROM usuarios u LEFT JOIN usuarios_roles r ON r.id_usuario=u.id_usuario WHERE u.estado=1 AND COALESCE(r.rol,u.rol)='administrador'")->fetchColumn();
        if(rol_usuario($db,$id)==='administrador'&&$rol!=='administrador'&&$n<=1)throw new InvalidArgumentException('Debe quedar al menos un administrador activo.');
        $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO usuarios_roles VALUES(?,?) ON CONFLICT(id_usuario) DO UPDATE SET rol=excluded.rol':'INSERT INTO usuarios_roles VALUES(?,?) ON DUPLICATE KEY UPDATE rol=VALUES(rol)';$db->prepare($sql)->execute([$id,$rol]);
        $db->prepare('INSERT INTO roles_historial VALUES(?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),$id,$rol,$actor,date('Y-m-d H:i:s')]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function crear_usuario_equipo(PDO $db,array $d,int $actor): void {
    $nombre=trim((string)($d['nombre']??''));$correo=trim((string)($d['correo']??''));$password=(string)($d['password']??'');$rol=(string)($d['rol']??'');
    if(mb_strlen($nombre)<2||mb_strlen($nombre)>100||strlen($correo)>100||!filter_var($correo,FILTER_VALIDATE_EMAIL)||strlen($password)<12||strlen($password)>200||!in_array($rol,['administrador','vendedor','almacen'],true))throw new InvalidArgumentException('Completa nombre, correo válido, rol y contraseña de 12 a 200 caracteres.');
    $db->beginTransaction();try{
        $db->query('SELECT id FROM roles_control WHERE id=1'.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetchColumn();if(rol_usuario($db,$actor)!=='administrador')throw new InvalidArgumentException('Se requiere administrador.');
        $db->prepare("INSERT INTO usuarios(nombre,correo,password,rol,estado) VALUES(?,?,?,'administrador',1)")->execute([$nombre,$correo,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$db->lastInsertId();
        $db->prepare('INSERT INTO usuarios_roles VALUES(?,?)')->execute([$id,$rol]);$db->prepare('INSERT INTO roles_historial VALUES(?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),$id,$rol,$actor,date('Y-m-d H:i:s')]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function activar_usuario_equipo(PDO $db,int $id,bool $activo,int $actor): void {
    $db->beginTransaction();try{
        $db->query('SELECT id FROM roles_control WHERE id=1'.($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''))->fetchColumn();if(rol_usuario($db,$actor)!=='administrador')throw new InvalidArgumentException('Se requiere administrador.');
        $q=$db->prepare('SELECT u.estado,COALESCE(r.rol,u.rol) rol FROM usuarios u LEFT JOIN usuarios_roles r ON r.id_usuario=u.id_usuario WHERE u.id_usuario=?');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u)throw new InvalidArgumentException('Usuario inexistente.');
        if(!$activo&&$u['estado']&&$u['rol']==='administrador'){$n=(int)$db->query("SELECT COUNT(*) FROM usuarios u LEFT JOIN usuarios_roles r ON r.id_usuario=u.id_usuario WHERE u.estado=1 AND COALESCE(r.rol,u.rol)='administrador'")->fetchColumn();if($n<=1)throw new InvalidArgumentException('No puedes desactivar al último administrador.');}
        $db->prepare('UPDATE usuarios SET estado=? WHERE id_usuario=?')->execute([$activo?1:0,$id]);$db->prepare('INSERT INTO roles_historial VALUES(?,?,?,?,?)')->execute([bin2hex(random_bytes(16)),$id,$activo?'activado':'desactivado',$actor,date('Y-m-d H:i:s')]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
