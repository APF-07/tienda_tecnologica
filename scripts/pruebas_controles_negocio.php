<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/controles_negocio.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE productos(id_producto INT PRIMARY KEY,nombre TEXT,descripcion TEXT,id_categoria INT,stock INT);CREATE TABLE categorias(id_categoria INT PRIMARY KEY,estado INT);INSERT INTO productos VALUES(1,\'Equipo\',\'USB\',1,20);INSERT INTO categorias VALUES(1,1),(2,1),(3,0)');
$r=['datos_id'=>'informe1','controles'=>['clasificacion'=>['filas'=>[['id'=>1,'actual'=>1,'propuesta'=>2,'huella'=>hash('sha256',"Equipo\nUSB")]]]]];
$ok=0;$check=function($cond,$mensaje)use(&$ok){if(!$cond)throw new RuntimeException($mensaje);echo 'OK '.$mensaje.PHP_EOL;$ok++;};
$fallar=function($datos,$reporte)use($db,$check){try{confirmar_categoria_ml($db,$datos,1,$reporte);$check(false,'Debe rechazar entrada');}catch(RuntimeException $e){$check(true,$e->getMessage());}};
$fallar(['producto'=>1,'informe'=>'otro'],$r);
$fallar(['producto'=>99,'informe'=>'informe1'],$r);
$inactivo=$r;$inactivo['controles']['clasificacion']['filas'][0]['propuesta']=3;$fallar(['producto'=>1,'informe'=>'informe1'],$inactivo);
$texto=$r;$texto['controles']['clasificacion']['filas'][0]['huella']=hash('sha256','obsoleto');$fallar(['producto'=>1,'informe'=>'informe1'],$texto);
$check((int)$db->query('SELECT id_categoria FROM productos')->fetchColumn()===1,'Rechazos no cambian categoría');
confirmar_categoria_ml($db,['producto'=>1,'informe'=>'informe1','propuesta'=>3],7,$r);
$check((int)$db->query('SELECT id_categoria FROM productos')->fetchColumn()===2,'Categoría proviene del informe, ignora propuesta del navegador');
$check((int)$db->query('SELECT actor FROM ml_revision_categorias')->fetchColumn()===7,'Registra actor de revisión');
$check((int)$db->query('SELECT stock FROM productos')->fetchColumn()===20,'Confirmar no cambia stock');
$fallar(['producto'=>1,'informe'=>'informe1'],$r);
echo $ok.' comprobaciones; solo SQLite temporal, sin cambios en MySQL.'.PHP_EOL;
