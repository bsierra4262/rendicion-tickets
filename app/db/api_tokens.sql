-- Tokens de la API de solo lectura (para consumir desde Jarvis u otros servicios)
CREATE TABLE IF NOT EXISTS api_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(120)    NOT NULL,
  prefijo     VARCHAR(16)     NOT NULL,
  token_hash  CHAR(64)        NOT NULL,
  activo      TINYINT(1)      NOT NULL DEFAULT 1,
  usos        INT UNSIGNED    NOT NULL DEFAULT 0,
  ultimo_uso  DATETIME        NULL,
  creado_por  BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_token_hash (token_hash),
  KEY ix_api_prefijo (prefijo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
