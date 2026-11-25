-- ============================================================
--  Migración: ingreso con ITHub (sesión única de Intellihelp)
--  Idempotente: se puede correr varias veces sin romper nada.
--  Tabla donde ithub-logout anota las sesiones de ITHub que se cerraron
--  (aviso por detrás, back-channel logout). auth_user() la consulta en cada
--  pedido: una sesión de la app abierta con ese `sid` se corta. No toca
--  ningún dato existente. schema.sql ya la trae para una instalación nueva.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `ithub_sesiones_cerradas` (
  `sid`        CHAR(32)  NOT NULL,     -- id de la sesión de ITHub (claim `sid`)
  `cerrada_en` DATETIME  NOT NULL,
  PRIMARY KEY (`sid`),
  KEY `idx_ithub_cerrada_en` (`cerrada_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
