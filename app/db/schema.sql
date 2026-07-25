-- ============================================================
--  INTELLIHELP · Rendición de Tickets  ·  Esquema de base de datos
--  MySQL/MariaDB (utf8mb4). Diseñado para replicar y superar el Excel.
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ------------------------------------------------------------
--  Usuarios (login + roles)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(120)    NOT NULL,
  email          VARCHAR(190)    NOT NULL,
  password_hash  VARCHAR(255)    NOT NULL,
  rol            ENUM('admin','carga') NOT NULL DEFAULT 'carga',
  activo         TINYINT(1)      NOT NULL DEFAULT 1,
  ultimo_acceso  DATETIME        NULL,
  created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Proveedores (tabla maestra; CUIT único cuando existe)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS proveedores (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuit                  VARCHAR(11)     NULL,
  razon_social          VARCHAR(200)    NOT NULL,
  cuit_valido           TINYINT(1)      NOT NULL DEFAULT 0,
  condicion_iva         VARCHAR(40)     NULL,
  categoria_sugerida_id INT UNSIGNED    NULL,               -- categoría aprendida (auto-entrenamiento)
  created_at            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proveedores_cuit (cuit),
  KEY ix_proveedores_razon (razon_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Padrón nacional (empresas / personas jurídicas) — CUIT → razón social.
--  Base offline precargada 1 sola vez (dato comercial público). Sólo lectura:
--  enriquece el OCR cuando el CUIT/razón no está en `proveedores`. `proveedores`
--  sigue siendo la tabla viva (auto-aprendizaje + categoría por proveedor).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS padron_nacional (
  cuit         CHAR(11)     NOT NULL,
  razon_social VARCHAR(150) NOT NULL,
  PRIMARY KEY (cuit),
  FULLTEXT KEY ft_padron_razon (razon_social)         -- búsqueda razón→CUIT (aprox.)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Conceptos (categorías de gasto)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conceptos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(60)  NOT NULL,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  orden        INT          NOT NULL DEFAULT 0,
  eliminado_en DATETIME     NULL,                        -- soft delete (papelera, recuperable)
  PRIMARY KEY (id),
  UNIQUE KEY uq_conceptos_nombre (nombre),
  KEY ix_conceptos_eliminado (eliminado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Comprobantes (núcleo: una fila = un comprobante de gasto)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS comprobantes (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  periodo            CHAR(7)         NOT NULL,               -- YYYY-MM (derivado de fecha_emision)
  fecha_emision      DATE            NOT NULL,
  tipo_comprobante   VARCHAR(20)     NOT NULL,               -- TK / Fact A / Fact B / Fact C / NC / ND
  letra              CHAR(1)         NULL,                   -- A / B / C
  punto_venta        VARCHAR(10)     NULL,
  numero             VARCHAR(30)     NULL,
  proveedor_id       BIGINT UNSIGNED NULL,
  concepto_id        INT UNSIGNED    NULL,
  moneda             ENUM('ARS','USD') NOT NULL DEFAULT 'ARS',
  tipo_cambio        DECIMAL(14,4)   NULL,                   -- si moneda = USD
  importe_neto       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  tasa_iva           DECIMAL(5,2)    NULL,                   -- 0 / 10.50 / 21.00 / 27.00 (NULL si mixto)
  importe_iva        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  importe_percepcion DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  importe_total      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
  estado             ENUM('pendiente_revision','verificado','rechazado') NOT NULL DEFAULT 'verificado',
  origen             ENUM('manual','ocr','import') NOT NULL DEFAULT 'manual',
  imagen_path        VARCHAR(255)    NULL,
  ocr_text           MEDIUMTEXT      NULL,
  observaciones      TEXT            NULL,
  creado_por         BIGINT UNSIGNED NULL,
  verificado_por     BIGINT UNSIGNED NULL,
  verificado_en      DATETIME        NULL,
  eliminado_en       DATETIME        NULL,                   -- soft delete (regla de oro: no se borra físico)
  eliminado_por      BIGINT UNSIGNED NULL,
  created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_comp_eliminado (eliminado_en),
  KEY ix_comp_periodo   (periodo),
  KEY ix_comp_fecha     (fecha_emision),
  KEY ix_comp_prov      (proveedor_id),
  KEY ix_comp_concepto  (concepto_id),
  KEY ix_comp_estado    (estado),
  KEY ix_comp_moneda    (moneda),
  KEY ix_comp_tipo      (tipo_comprobante),
  CONSTRAINT fk_comp_prov      FOREIGN KEY (proveedor_id)   REFERENCES proveedores (id) ON DELETE SET NULL,
  CONSTRAINT fk_comp_concepto  FOREIGN KEY (concepto_id)    REFERENCES conceptos   (id) ON DELETE SET NULL,
  CONSTRAINT fk_comp_creado    FOREIGN KEY (creado_por)     REFERENCES usuarios    (id) ON DELETE SET NULL,
  CONSTRAINT fk_comp_verif     FOREIGN KEY (verificado_por) REFERENCES usuarios    (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Configuración de la app (clave/valor)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
  clave      VARCHAR(60)  NOT NULL,
  valor      TEXT         NULL,
  updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Semilla: categorías (Concepto) reales del Excel
-- ------------------------------------------------------------
INSERT IGNORE INTO conceptos (nombre, orden) VALUES
  ('Comida', 10),
  ('Supermercado', 20),
  ('Estacionamiento', 30),
  ('Combustible', 40),
  ('Insumos', 50),
  ('Viáticos', 60),
  ('Librería', 70),
  ('Peaje', 80),
  ('Fletes', 90),
  ('Taller', 100),
  ('Servicios', 110);

INSERT IGNORE INTO app_settings (clave, valor) VALUES
  ('app_nombre', 'Rendición de Tickets'),
  ('moneda_default', 'ARS'),
  ('tipo_cambio_usd', '0');
