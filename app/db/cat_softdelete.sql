-- ============================================================
--  Migración: soft delete (papelera) en categorías (conceptos)
--  "Eliminar" una categoría la archiva (recuperable), no la borra.
-- ============================================================
ALTER TABLE conceptos
  ADD COLUMN eliminado_en DATETIME NULL DEFAULT NULL AFTER orden,
  ADD KEY ix_conceptos_eliminado (eliminado_en);
