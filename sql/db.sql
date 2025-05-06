CREATE DATABASE IF NOT EXISTS ERP;

USE ERP;


CREATE TABLE IF NOT EXISTS empleado (
	cod_empleado INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	dni VARCHAR (50) UNIQUE NOT NULL,
	contrasenia VARCHAR (40) NOT NULL,
	nombre VARCHAR (100) NOT NULL,
	telefono VARCHAR (30) UNIQUE NOT NULL,
	mail VARCHAR (100) UNIQUE NOT NULL
	usuario_id INT,  
    CONSTRAINT fk_usuario_empleado FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  -- Agregamos la clave foránea
);

CREATE TABLE IF NOT EXISTS proveedores_clientes (
	cod_actor INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	nombre VARCHAR (100) NOT NULL,
	telefono VARCHAR (30) UNIQUE,
	mail VARCHAR (100) UNIQUE NOT NULL,
	poblacion VARCHAR (100),
	direccion VARCHAR (100) NOT NULL,
	tipo ENUM("proveedor", "cliente") NOT NULL,
	nif_dni VARCHAR (50) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS roles (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO roles (nombre_rol) VALUES ("admin");
INSERT INTO roles (nombre_rol) VALUES ("empleado");
INSERT INTO roles (nombre_rol) VALUES ("cliente");

CREATE TABLE IF NOT EXISTS usuarios (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(100) UNIQUE NOT NULL,
    contrasena VARCHAR(255) NOT NULL, 
    rol_id INT NOT NULL, 
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id) 
);

CREATE TABLE IF NOT EXISTS producto_servicio (
	cod_producto INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	nombre VARCHAR (100) NOT NULL,
	iva INT NOT NULL,
	precio_compra DECIMAL (20,2) NOT NULL,
	precio_venta DECIMAL (20,2) NOT NULL,
	cod_proveedor INT NOT NULL,
    FOREIGN KEY (cod_proveedor) REFERENCES proveedores_clientes(cod_actor) 	-- Agregar proveedor del producto (modificación)
);

-- Modificación: Crear una tabla intermedia por si un proveedor da muchos productos
CREATE TABLE producto_proveedor (
    cod_producto INT NOT NULL,
    cod_proveedor INT NOT NULL,
    precio_compra DECIMAL(20,2),
    PRIMARY KEY (cod_producto, cod_proveedor),
    FOREIGN KEY (cod_producto) REFERENCES producto_servicio(cod_producto),
    FOREIGN KEY (cod_proveedor) REFERENCES proveedores_clientes(cod_actor)
);

CREATE TABLE IF NOT EXISTS almacen (
	cod_almacen INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	ubicacion VARCHAR (100) NOT NULL
);

CREATE TABLE IF NOT EXISTS facturas (
	num_factura INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	cod_empleado INT,
	cod_actor INT,
	tipo ENUM("compra", "venta") NOT NULL,
	total_factura DECIMAL (20,2) NOT NULL,
	fecha DATE NOT NULL,

	CONSTRAINT fk1_factura_empleado FOREIGN KEY (cod_empleado) REFERENCES empleado(cod_empleado) ON DELETE SET NULL,
	CONSTRAINT fk2_factura_proveedorcliente FOREIGN KEY (cod_actor) REFERENCES proveedores_clientes(cod_actor) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS almacen_producto_servicio (    
	cod_almacen INT NOT NULL,
	cod_producto INT NOT NULL,
	cantidad INT NOT NULL,

	PRIMARY KEY (cod_almacen, cod_producto),
	CONSTRAINT fk1_aps_almacen FOREIGN KEY (cod_almacen) REFERENCES almacen(cod_almacen) ON DELETE CASCADE,
	CONSTRAINT fk2_aps_productoservicio FOREIGN KEY (cod_producto) REFERENCES producto_servicio(cod_producto) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lineas (
	num_linea INT NOT NULL,
	num_factura INT NOT NULL,
	cod_producto INT,
	cod_almacen INT,
	cantidad INT NOT NULL,
	precio_negociado_unitario DECIMAL (20,2) NOT NULL,
	precio_total DECIMAL (20,2) NOT NULL,

	PRIMARY KEY (num_linea, num_factura),
	CONSTRAINT fk1_lineas_facturas FOREIGN KEY (num_factura) REFERENCES facturas(num_factura) ON DELETE CASCADE,
	CONSTRAINT fk2_lineas_productoservicio FOREIGN KEY (cod_producto) REFERENCES producto_servicio(cod_producto) ON DELETE SET NULL,
	CONSTRAINT fk3_lineas_almacen FOREIGN KEY (cod_almacen) REFERENCES almacen(cod_almacen) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS facturas_historial (
    cod_fhistorial INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    num_factura INT NOT NULL,
    nombre_empleado VARCHAR(100),
    cod_empleado INT,
    nombre_cliente_proveedor VARCHAR(100),
    nif_dni VARCHAR(50),
    total_factura DECIMAL(20,2) NOT NULL,
    fecha DATE NOT NULL
);

CREATE TABLE IF NOT EXISTS lineas_historial (
    cod_lhistorial INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    num_factura INT NOT NULL,
    num_linea INT NOT NULL,
    nombre_producto_servicio VARCHAR(100),
    cantidad INT NOT NULL,
    precio_negociado_unitario DECIMAL(20,2) NOT NULL,
    precio_total DECIMAL(20,2) NOT NULL,
    ubicacion_almacen VARCHAR(100)
);



DELIMITER //

CREATE TRIGGER eliminar_producto_sin_stock
AFTER DELETE ON almacen_producto_servicio
FOR EACH ROW
BEGIN
    -- Si un producto se queda sin stock en ningún almacén, eliminarlo
    IF NOT EXISTS (SELECT 1 FROM almacen_producto_servicio WHERE cod_producto = OLD.cod_producto) THEN
        DELETE FROM producto_servicio WHERE cod_producto = OLD.cod_producto;
    END IF;
END;

//

DELIMITER ;