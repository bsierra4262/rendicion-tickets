-- ============================================================
--  Rendición de Tickets — esquema de base de datos
--  MySQL 8.0+ / MariaDB 10.4+  ·  utf8mb4
--
--  Uso:  mysql -u USER -p DATABASE < app/db/schema.sql
--  (En Docker se importa automáticamente en el primer arranque.)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


CREATE TABLE IF NOT EXISTS `actividad` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `canal` enum('app','api') NOT NULL DEFAULT 'app',
  `tipo` enum('accion','nav','auth','error','sistema') NOT NULL DEFAULT 'accion',
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `actor` varchar(160) DEFAULT NULL,
  `accion` varchar(120) NOT NULL,
  `entidad` varchar(60) DEFAULT NULL,
  `entidad_id` bigint(20) unsigned DEFAULT NULL,
  `detalle` varchar(500) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_creado` (`creado_en`),
  KEY `idx_canal` (`canal`,`creado_en`),
  KEY `idx_tipo` (`tipo`,`creado_en`),
  KEY `idx_user` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `prefijo` varchar(16) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `scope` varchar(20) NOT NULL DEFAULT 'lectura',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `usos` int(10) unsigned NOT NULL DEFAULT 0,
  `ultimo_uso` datetime DEFAULT NULL,
  `creado_por` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_token_hash` (`token_hash`),
  KEY `ix_api_prefijo` (`prefijo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `app_settings` (
  `clave` varchar(60) NOT NULL,
  `valor` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Módulo de Alertas (centralizado) ----
-- Cada condición de la app genera una alerta idempotente (clave única). Vive DENTRO del
-- sistema (bandeja) por default y, según config, se envía por Email / ITHOS.
CREATE TABLE IF NOT EXISTS `alertas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(60) NOT NULL,
  `severidad` enum('info','warn','danger') NOT NULL DEFAULT 'info',
  `titulo` varchar(200) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `entidad` varchar(40) DEFAULT NULL,
  `entidad_id` bigint(20) unsigned DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `clave` varchar(120) NOT NULL,
  `canales` varchar(120) DEFAULT NULL,
  `leida_en` datetime DEFAULT NULL,
  `resuelta_en` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alerta_clave` (`clave`),
  KEY `idx_alerta_estado` (`resuelta_en`, `leida_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `comprobantes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `periodo` char(7) NOT NULL,
  `fecha_emision` date NOT NULL,
  `tipo_comprobante` varchar(20) NOT NULL,
  `letra` char(1) DEFAULT NULL,
  `punto_venta` varchar(10) DEFAULT NULL,
  `numero` varchar(30) DEFAULT NULL,
  `proveedor_id` bigint(20) unsigned DEFAULT NULL,
  `concepto_id` int(10) unsigned DEFAULT NULL,
  `moneda` enum('ARS','USD') NOT NULL DEFAULT 'ARS',
  `tipo_cambio` decimal(14,4) DEFAULT NULL,
  `importe_neto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tasa_iva` decimal(5,2) DEFAULT NULL,
  `importe_iva` decimal(15,2) NOT NULL DEFAULT 0.00,
  `importe_percepcion` decimal(15,2) NOT NULL DEFAULT 0.00,
  `importe_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente_revision','verificado','rechazado') NOT NULL DEFAULT 'verificado',
  `origen` enum('manual','ocr','import') NOT NULL DEFAULT 'manual',
  `imagen_path` varchar(255) DEFAULT NULL,
  `drive_link` varchar(512) DEFAULT NULL,
  `drive_file_id` varchar(128) DEFAULT NULL,
  `ocr_text` mediumtext DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `creado_por` bigint(20) unsigned DEFAULT NULL,
  `verificado_por` bigint(20) unsigned DEFAULT NULL,
  `verificado_en` datetime DEFAULT NULL,
  `eliminado_en` datetime DEFAULT NULL,
  `eliminado_por` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_comp_periodo` (`periodo`),
  KEY `ix_comp_fecha` (`fecha_emision`),
  KEY `ix_comp_prov` (`proveedor_id`),
  KEY `ix_comp_concepto` (`concepto_id`),
  KEY `ix_comp_estado` (`estado`),
  KEY `ix_comp_moneda` (`moneda`),
  KEY `ix_comp_tipo` (`tipo_comprobante`),
  KEY `fk_comp_creado` (`creado_por`),
  KEY `fk_comp_verif` (`verificado_por`),
  KEY `ix_comp_eliminado` (`eliminado_en`),
  CONSTRAINT `fk_comp_concepto` FOREIGN KEY (`concepto_id`) REFERENCES `conceptos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comp_creado` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comp_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comp_verif` FOREIGN KEY (`verificado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `conceptos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conceptos_nombre` (`nombre`),
  KEY `ix_conceptos_eliminado` (`eliminado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `padron_nacional` (
  `cuit` char(11) NOT NULL,
  `razon_social` varchar(150) NOT NULL,
  PRIMARY KEY (`cuit`),
  FULLTEXT KEY `ft_padron_razon` (`razon_social`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cuit` varchar(11) DEFAULT NULL,
  `razon_social` varchar(200) NOT NULL,
  `cuit_valido` tinyint(1) NOT NULL DEFAULT 0,
  `condicion_iva` varchar(40) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `categoria_sugerida_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proveedores_cuit` (`cuit`),
  KEY `ix_proveedores_razon` (`razon_social`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin','carga') NOT NULL DEFAULT 'carga',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar_url` varchar(400) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---- Módulo Gastos Recurrentes ----
CREATE TABLE `gastos_proveedores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `rubro` varchar(120) DEFAULT NULL,
  `categoria` enum('recurrente','unica_vez') NOT NULL DEFAULT 'recurrente',
  `momento_facturacion` enum('adelantado','vencido') DEFAULT NULL,
  `tipo_factura` varchar(60) DEFAULT NULL,
  `vigencia_descuento` date DEFAULT NULL,
  `nombre_gasto` varchar(150) DEFAULT NULL,
  `cuit` varchar(160) DEFAULT NULL,
  `multi_entidad` tinyint(1) NOT NULL DEFAULT 0,
  `entidades` text DEFAULT NULL,
  `drive_folder` varchar(150) DEFAULT NULL,
  `drive_folder_id` varchar(128) DEFAULT NULL,
  `activo` tinyint(4) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `eliminado_en` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`,`eliminado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gastos_recurrentes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(10) unsigned NOT NULL,
  `periodo` char(7) NOT NULL,
  `valor_mensual` decimal(14,2) DEFAULT NULL,
  `moneda` varchar(4) NOT NULL DEFAULT 'ARS',
  `fecha_factura` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `drive_factura_id` varchar(128) DEFAULT NULL,
  `drive_factura_link` varchar(512) DEFAULT NULL,
  `factura_path` varchar(255) DEFAULT NULL,
  `drive_pago_id` varchar(128) DEFAULT NULL,
  `drive_pago_link` varchar(512) DEFAULT NULL,
  `pago_path` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','pagado') NOT NULL DEFAULT 'pendiente',
  `observaciones` varchar(500) DEFAULT NULL,
  `creado_por` int(10) unsigned DEFAULT NULL,
  `eliminado_en` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_prov` (`proveedor_id`),
  KEY `idx_periodo` (`periodo`),
  KEY `idx_estado` (`estado`,`eliminado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Ajustes por defecto de la aplicación ----
INSERT INTO `app_settings` (`clave`, `valor`) VALUES
  ('app_nombre', 'Rendición de Tickets'),
  ('moneda_default', 'ARS'),
  ('tipo_cambio_usd', '0')
ON DUPLICATE KEY UPDATE `valor` = `valor`;

SET FOREIGN_KEY_CHECKS = 1;
