
-- Ejecutar UNA sola vez después de crear las tablas con BD_2.sql
-- ROLES
INSERT IGNORE INTO roles (nombre) VALUES ('admin');
INSERT IGNORE INTO roles (nombre) VALUES ('invitado');

-- PERMISOS
INSERT IGNORE INTO permisos (descripcion) VALUES ('ver_armazones');
INSERT IGNORE INTO permisos (descripcion) VALUES ('editar_armazones');
INSERT IGNORE INTO permisos (descripcion) VALUES ('ver_inventario');
INSERT IGNORE INTO permisos (descripcion) VALUES ('editar_inventario');
INSERT IGNORE INTO permisos (descripcion) VALUES ('ver_reportes');
INSERT IGNORE INTO permisos (descripcion) VALUES ('gestionar_usuarios');
INSERT IGNORE INTO permisos (descripcion) VALUES ('gestionar_garantias');

-- USUARIO ADMIN INICIAL
-- Usuario: admin
-- Contraseña: 12345678
-- Invitado
-- Usuario: Es el alias que le dio el admin al momento de registrarlo
-- Contraseña: La que el admin desee poner

INSERT IGNORE INTO usuarios (username, password_hash, nombre, apellido_paterno, apellido_materno, rol_id, activo)
VALUES (
    'admin',
    '$2y$10$nUMptbb1ecB08pvVGkAkouCVjjTKwUp1UdLu0dCtqDDVylhuLvL4a',
    'Administrador',
    'Sistema',
    '',
    1,
    1
);