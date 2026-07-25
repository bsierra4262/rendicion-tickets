-- ============================================================
--  Migración: soft delete en comprobantes (regla de oro de auditoría)
--  Lo que se "borra" no se elimina físicamente: se archiva con
--  eliminado_en / eliminado_por. Deja de verse para los usuarios pero
--  sigue en la base para auditoría; el borrado queda en el CRON.
-- ============================================================
ALTER TABLE comprobantes
  ADD COLUMN eliminado_en  DATETIME        NULL DEFAULT NULL AFTER verificado_en,
  ADD COLUMN eliminado_por BIGINT UNSIGNED NULL DEFAULT NULL AFTER eliminado_en,
  ADD KEY ix_comp_eliminado (eliminado_en);
