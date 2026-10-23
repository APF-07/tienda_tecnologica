-- Migración aditiva MySQL/MariaDB. Seleccionar antes la base de la tienda.
-- La aplicación también prepara estas tablas. No incluye datos ni credenciales.
CREATE TABLE IF NOT EXISTS movimientos_stock (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,id_producto INT NOT NULL,tipo VARCHAR(30) NOT NULL,
 anterior INT NOT NULL,cantidad INT NOT NULL,nuevo INT NOT NULL,referencia VARCHAR(80) NOT NULL,
 usuario INT NOT NULL,motivo VARCHAR(500) NOT NULL,fecha VARCHAR(19) NOT NULL
);
CREATE TABLE IF NOT EXISTS proveedores (
 id VARCHAR(40) PRIMARY KEY,nombre VARCHAR(150) NOT NULL,contacto VARCHAR(190) NOT NULL,
 plazo INT NOT NULL,activo INT NOT NULL
);
CREATE TABLE IF NOT EXISTS compras_reposicion (
 id VARCHAR(40) PRIMARY KEY,proveedor VARCHAR(40) NOT NULL,id_producto INT NOT NULL,
 cantidad INT NOT NULL,recibida INT NOT NULL,costo BIGINT NOT NULL,plazo INT NOT NULL,
 estado VARCHAR(20) NOT NULL,creada VARCHAR(19) NOT NULL,usuario INT NOT NULL
);
CREATE TABLE IF NOT EXISTS recepciones_compra (
 id VARCHAR(40) PRIMARY KEY,compra VARCHAR(40) NOT NULL,cantidad INT NOT NULL,
 fecha VARCHAR(19) NOT NULL,usuario INT NOT NULL,referencia VARCHAR(150) NOT NULL
);
CREATE TABLE IF NOT EXISTS devoluciones (
 id VARCHAR(40) PRIMARY KEY,id_pedido INT NOT NULL,id_producto INT NOT NULL,
 cantidad INT NOT NULL,maximo BIGINT NOT NULL,estado VARCHAR(20) NOT NULL,
 reintegrada INT NOT NULL,motivo VARCHAR(500) NOT NULL,creada VARCHAR(19) NOT NULL,
 usuario INT NOT NULL,recibida VARCHAR(19) NULL
);
CREATE TABLE IF NOT EXISTS reembolsos (
 devolucion VARCHAR(40) PRIMARY KEY,importe BIGINT NOT NULL,referencia VARCHAR(150) NOT NULL,
 firma VARCHAR(64) UNIQUE NOT NULL,fecha VARCHAR(19) NOT NULL,usuario INT NOT NULL
);
CREATE TABLE IF NOT EXISTS gestion_config (clave VARCHAR(60) PRIMARY KEY,valor VARCHAR(100) NOT NULL);
CREATE TABLE IF NOT EXISTS gestion_eventos (
 id VARCHAR(40) PRIMARY KEY,tipo VARCHAR(30) NOT NULL,referencia VARCHAR(40) NOT NULL,
 motivo VARCHAR(500) NOT NULL,usuario INT NOT NULL,fecha VARCHAR(19) NOT NULL
);
CREATE TABLE IF NOT EXISTS usuarios_roles (id_usuario INT PRIMARY KEY,rol VARCHAR(20) NOT NULL);
CREATE TABLE IF NOT EXISTS roles_control (id INT PRIMARY KEY);
INSERT IGNORE INTO roles_control VALUES(1);
CREATE TABLE IF NOT EXISTS roles_historial (
 id VARCHAR(40) PRIMARY KEY,usuario INT NOT NULL,rol VARCHAR(20) NOT NULL,
 actor INT NOT NULL,fecha VARCHAR(19) NOT NULL
);
