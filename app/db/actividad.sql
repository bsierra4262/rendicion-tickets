-- ============================================================
--  Tabla de actividad / auditoría (alimenta la sección "CRON")
--  Registra dos canales:
--    · app  → movimientos de usuarios + errores de la aplicación
--    · api  → cada pedido a la API de solo lectura (Jarvis, etc.)
-- ============================================================
CREATE TABLE IF NOT EXISTS actividad (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    creado_en   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    canal       ENUM('app','api')                       NOT NULL DEFAULT 'app',
    tipo        ENUM('accion','nav','auth','error','sistema') NOT NULL DEFAULT 'accion',
    usuario_id  INT UNSIGNED    NULL,
    actor       VARCHAR(160)    NULL,            -- nombre del usuario o del token (snapshot)
    accion      VARCHAR(120)    NOT NULL,        -- ej. 'comprobante.crear', 'api/estado'
    entidad     VARCHAR(60)     NULL,            -- ej. 'comprobante', 'usuario', 'token'
    entidad_id  BIGINT UNSIGNED NULL,
    detalle     VARCHAR(500)    NULL,
    ip          VARCHAR(45)     NULL,
    PRIMARY KEY (id),
    KEY idx_creado (creado_en),
    KEY idx_canal  (canal, creado_en),
    KEY idx_tipo   (tipo, creado_en),
    KEY idx_user   (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
