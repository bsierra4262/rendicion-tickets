-- ============================================================
--  Migración: copia local PENDIENTE de subir a Drive (gastos)
--  Si Drive falla al cargar la factura o el pago de un gasto, el archivo
--  se guarda en el servidor (storage/uploads/pendientes-drive/…) y su ruta
--  queda acá hasta que se sube solo a Drive (drive_sincronizar_pendientes).
--  Los comprobantes usan la columna imagen_path que ya existe.
-- ============================================================
ALTER TABLE gastos_recurrentes
  ADD COLUMN factura_path VARCHAR(255) NULL DEFAULT NULL AFTER drive_factura_link,
  ADD COLUMN pago_path    VARCHAR(255) NULL DEFAULT NULL AFTER drive_pago_link;
