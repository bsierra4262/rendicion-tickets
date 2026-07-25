-- Recarga limpia de las categorías (UTF-8 correcto). Seguro: sin referencias aún.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE conceptos;
SET FOREIGN_KEY_CHECKS = 1;
INSERT INTO conceptos (id, nombre, orden) VALUES
  (1,  'Comida',          10),
  (2,  'Supermercado',    20),
  (3,  'Estacionamiento', 30),
  (4,  'Combustible',     40),
  (5,  'Insumos',         50),
  (6,  'Viáticos',        60),
  (7,  'Librería',        70),
  (8,  'Peaje',           80),
  (9,  'Fletes',          90),
  (10, 'Taller',          100),
  (11, 'Servicios',       110);
