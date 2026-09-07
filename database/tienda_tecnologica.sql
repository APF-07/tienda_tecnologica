-- =====================================================
-- CREACIÓN DE LA BASE DE DATOS
-- =====================================================
DROP DATABASE IF EXISTS tienda_tecnologica;
CREATE DATABASE tienda_tecnologica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tienda_tecnologica;

-- =====================================================
-- 1. TABLA USUARIOS (Administradores)
-- =====================================================
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('administrador') NOT NULL DEFAULT 'administrador',
    estado TINYINT NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. TABLA CATEGORIAS
-- =====================================================
CREATE TABLE categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) UNIQUE NOT NULL,
    descripcion TEXT NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 3. TABLA MARCAS
-- =====================================================
CREATE TABLE marcas (
    id_marca INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) UNIQUE NOT NULL,
    descripcion TEXT NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 4. TABLA PRODUCTOS
-- =====================================================
CREATE TABLE productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    id_categoria INT NOT NULL,
    id_marca INT NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    imagen VARCHAR(255) NULL,
    estado TINYINT NOT NULL DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria) 
        REFERENCES categorias(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_producto_marca FOREIGN KEY (id_marca) 
        REFERENCES marcas(id_marca) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 5. TABLA CLIENTES
-- =====================================================
CREATE TABLE clientes (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion TEXT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 6. TABLA PEDIDOS
-- =====================================================
CREATE TABLE pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    estado ENUM('Pendiente', 'Confirmado', 'En preparación', 'Enviado', 'Entregado', 'Cancelado') NOT NULL DEFAULT 'Pendiente',
    CONSTRAINT fk_pedido_cliente FOREIGN KEY (id_cliente) 
        REFERENCES clientes(id_cliente) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 7. TABLA DETALLE_PEDIDOS (Relación N:M Pedidos - Productos)
-- =====================================================
CREATE TABLE detalle_pedidos (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detalle_pedido FOREIGN KEY (id_pedido) 
        REFERENCES pedidos(id_pedido) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto) 
        REFERENCES productos(id_producto) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- DATOS INICIALES (SEEDERS)
-- =====================================================

-- 1. Insertar Administrador por defecto
-- Correo: admin@tienda.com | Contraseña: admin123
INSERT INTO usuarios (nombre, correo, password, rol, estado) VALUES 
('Administrador Principal', 'admin@tienda.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador', 1);

-- 2. Insertar Categorías iniciales
INSERT INTO categorias (nombre, descripcion) VALUES 
('Laptops', 'Computadoras portátiles para trabajo, estudio y gaming'),
('Computadoras', 'PCs de escritorio ensambladas y de marca'),
('Monitores', 'Pantallas y monitores LED/IPS de alta resolución'),
('Componentes', 'Tarjetas madre, procesadores, memorias RAM y fuentes'),
('Almacenamiento', 'Discos de estado sólido SSD y discos duros externos'),
('Periféricos', 'Teclados, mouse y audífonos para oficina y gaming'),
('Celulares', 'Smartphones y accesorios móviles');

-- 3. Insertar Marcas iniciales
INSERT INTO marcas (nombre, descripcion) VALUES 
('ASUS', 'Fabricante global de hardware y computadoras'),
('Lenovo', 'Líder en tecnología de PCs y portátiles'),
('HP', 'Soluciones de computación e impresión'),
('Dell', 'Equipos de escritorio y laptops profesionales'),
('Samsung', 'Tecnología móvil, monitores y almacenamiento'),
('Logitech', 'Periféricos y accesorios de alta precisión'),
('Kingston', 'Memorias RAM y unidades de almacenamiento SSD'),
('MSI', 'Componentes y laptops orientadas al rendimiento extremo');