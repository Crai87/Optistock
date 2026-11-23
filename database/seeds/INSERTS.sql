-- Aqui vamos a poner las consultas para insertar datos a la base de datos
-- OJO NO QUIERAN AQUI PONER UNA CONSULTA PARA SACAR DATOS DE LA BASE DE DATOS 
-- ESTO ES EXCLUSIVO PARA PONER DATOS EN LA BASE DE DATOS 

INSERT INTO roles (id, nombre) VALUES
(1, 'ADMIN'),
(2, 'GERENTE'),
(3, 'EMPLEADO');

INSERT INTO permisos (id, descripcion) VALUES
(1, 'Gestionar usuarios'),
(2, 'Gestionar inventario armazones'),
(3, 'Gestionar inmobiliario'),
(4, 'Gestionar equipos clinicos'),
(5, 'Gestionar garantias'),
(6, 'Generar reportes'),
(7, 'Ver armazones'),
(8, 'Editar armazones'),
(9, 'Ver inventario');

INSERT INTO usuarios
    (id, username, password_hash, nombre, apellido_paterno, apellido_materno, rol_id, activo, creado_por)
VALUES
    (1, 'admin', '$2y$10$nUMptbb1ecB08pvVGkAkouCVjjTKwUp1UdLu0dCtqDDVylhuLvL4a', 'Administrador', 'General', NULL, 1, 1, NULL);

INSERT INTO usuario_permisos (usuario_id, permiso_id) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6);

INSERT INTO marcas_armazon (id, nombre) VALUES
(1,'OP'),
(2,'OPTIKIDS'),
(3,'ANIMATED'),
(4,'SD'),
(5,'CONVERSE ALL STAR');

INSERT INTO proveedores (id, nombre, telefono, email, direccion) VALUES
(1, 'Proveedor General Armazones', NULL, NULL, NULL);

INSERT INTO armazones 
(codigo, descripcion, area, marca_id, material, modelo, proveedor_id, tiene_garantia, cantidad_stock, costo, estado, created_by)
VALUES

('ARM-OP-808-001',
'ARMAZON DE METAL CON VARILLA FLEX DE PLASTICO',
'46 - 17 - 30',
1,
'METAL CON VARILLA DE PLASTICO',
'OP 808',
1,
0,
0,
NULL,
'disponible',
1),

('ARM-OPT-VINNY-001',
'ARMAZON DE METAL CON VARILLA DE METAL, COLOR...',
'43 - 22 -130',
2,
'METAL',
'VINNY - OPTIKIDS C1',
1,
0,
0,
NULL,
'disponible',
1),

('ARM-ANI-BROWN-001',
'ARMAZON DE METAL CON VARILLA FLEX DE METAL',
'47 - 17 - 135',
3,
'METAL',
'BROWN ANIMATED',
1,
0,
0,
NULL,
'disponible',
1),

('ARM-SD-001',
'ARMAZON DE METAL CON PATILLAS DE PLASTICO',
'51 - 16',
4,
'METAL',
'SD',
1,
0,
0,
NULL,
'disponible',
1),

('ARM-CONV-A207-001',
'ARMAZON DE METAL CON VARILLAS PLATEADA Y PATILLAS',
'49 - 16 - 130',
5,
'METAL',
'A207',
1,
0,
0,
NULL,
'disponible',
1);

INSERT INTO movimientos_inventario
(tipo_item, item_id, tipo_movimiento, cantidad, motivo, usuario_id)
VALUES
('armazon',1,'entrada',1,'Carga inicial desde inventario Excel',1),
('armazon',2,'entrada',1,'Carga inicial desde inventario Excel',1),
('armazon',3,'entrada',1,'Carga inicial desde inventario Excel',1),
('armazon',4,'entrada',1,'Carga inicial desde inventario Excel',1),
('armazon',5,'entrada',1,'Carga inicial desde inventario Excel',1);

INSERT INTO categorias_inmobiliario (id, nombre) VALUES
(1,'Laptops'),
(2,'Mobiliario'),
(3,'Equipos de oficina');

INSERT INTO marcas_equipos (id, nombre) VALUES
(1,'MARCO'),
(2,'BAUSH AND LOMB'),
(3,'SABLON'),
(4, 'UAL'),
(5, 'KONNING'),
(6, 'AMERICAN OPTICAL'),
(7, 'N/A'),
(8, 'COMERCIAL'),
(9, 'LYBBAY'),
(10, 'OIL'),
(11, 'KLEENEX BRAND'),
(12, 'AUGEN'),
(13, 'WOODLYM'),
(14, 'BURTON'),
(15, 'ALCON'),
(16, 'TOPCON'),
(17, 'HAAG-STREIT BERN'), 
(18, 'S/M'),
(19, 'ZARTEX');

INSERT INTO reportes (titulo, tipo, filtros_json, generado_por)
VALUES
('Inventario General Armazones','inventario_armazones',NULL,1);