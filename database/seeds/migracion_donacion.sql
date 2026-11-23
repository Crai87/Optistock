-- ============================================================
-- migracion_permisos_v2.sql
-- Permisos finales coherentes con la lógica de pantallas.
-- Ejecutar UNA SOLA VEZ en optometria_bd.
-- ============================================================

-- 1. Limpiar permisos de invitados
DELETE FROM usuario_permisos WHERE usuario_id IN (
    SELECT id FROM usuarios WHERE rol_id != 1
);

-- 2. Reemplazar catálogo de permisos
DELETE FROM permisos;

-- 3. Los 12 permisos finales (4 por módulo × 3 módulos + admin)
INSERT INTO permisos (id, descripcion) VALUES
-- Armazones
(1,  'Ver armazones'),
(2,  'Generar reporte armazones'),
(3,  'Agregar armazones'),
(4,  'Editar armazones'),
-- Equipos e Inmobiliario
(5,  'Ver equipos'),
(6,  'Generar reporte equipos'),
(7,  'Agregar equipos'),
(8,  'Editar equipos'),
-- Garantías
(9,  'Ver garantias'),
(10, 'Agregar garantias'),
(11, 'Editar garantias'),
-- Administración eliminada del catálogo de permisos
-- El panel admin solo es accesible por rol_id = 1 (admin)
-- Ningún invitado puede acceder sin importar qué permisos tenga
(12, 'reservado_sistema');  -- placeholder para mantener IDs consistentes