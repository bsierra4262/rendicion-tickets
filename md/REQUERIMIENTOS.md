# REQUERIMIENTOS PERMANENTES — INTELLIHELP · Rendición de Tickets

> Checklist de reglas fijas del cliente. **Se revisa en CADA iteración** para no
> saltear ninguna. Si el cliente pide algo nuevo permanente, se agrega acá.
> Última actualización: 2026-07-23.

---

## 🔴 Antes y después de CADA prueba (modo testing) — OBLIGATORIO
- [ ] **Vaciar la DB de testing SIEMPRE** antes de entregar para probar:
  - Comprobantes → borrar los de prueba y dejar **14 DEMO** frescos.
  - **CRON vacío** (tabla `actividad` truncada) para registrar las pruebas nuevas.
  - **Imágenes subidas borradas** (`STORAGE/uploads/`, archivos en disco APARTE de la DB).
  - Categorías → **solo las 11 originales** (Comida, Supermercado, Estacionamiento,
    Combustible, Insumos, Viáticos, Librería, Peaje, Fletes, Taller, Servicios).
  - Padrón `proveedores` → **NO se borra** (se conserva y crece; entrena la categoría).
  - Script: `scratchpad/_reset2.php` (subir con `sftp_deploy.py`, correr con `ssh_run.py`, borrar).
- Esto se hace **hasta que el cliente diga que pasamos a producción 100%**.

## 🟢 Base / plataforma
- [ ] **100% gratis**: sin APIs pagas, **sin registro, sin límites de consulta**, todo open-source.
- [ ] Login **100% Google Workspace** (dominio intellihelp.tech).
- [ ] Diseño **profesional**, **totalmente responsive**, **iconos SVG**.
- [ ] **Ventanas emergentes profesionales** (modal branded, nunca `confirm()` del navegador).
- [ ] Solo se sube al server el contenido de **`app/`** (webroot `public_html/comprobantes`).

## 🎨 Diseño / UI — profesional y CONSISTENTE (auditar, no parchar)
> Se audita **de raíz**, no solo lo que el cliente señala. El **módulo comprobantes** es el patrón de referencia; todo lo nuevo lo copia. Guardado por tests en `test/run_tests.php` (sección «diseño/UI»).
- [ ] **Responsive sin scroll horizontal en NINGÚN ancho** (desktop/laptop/notebook/tablet/celular). El menú superior colapsa a **drawer ≤1400px** y tiene red anti-overflow; todo se **reacomoda/redimensiona**, nunca aparece una barra horizontal.
- [ ] **Sin controles nativos crudos**: para on/off usar el **toggle `.switch`**; checkboxes/radios restantes con `accent-color:var(--primary)`. Selects → `combobox.js`; fechas → `datepicker.js` (nunca `style=` inline en select/date/month: es código muerto).
- [ ] **Filtros**: `class="filters card"` + `.filter-grid` + `<label><span>`. **Formularios**: `.form-card card` con `<h2 class="sec">` + `.fld`/`.grid-fields`. Sin estilos inline ad-hoc.
- [ ] **Campos repetibles** (ej. varios CUIT): lista `.multi-list` con botones Agregar/Quitar, no un texto con comas.
- [ ] **Badges** siempre con variante (`badge-ok`/`badge-info`/…), nunca `.badge` pelado (sale transparente).
- [ ] **Colores/spacing por variables CSS** (`var(--…)`), nunca hex crudo ni px a ojo.
- [ ] Tablas en `.table-scroll` con `data-label` + `prio-*` (modo tarjeta en ≤768px).

## 🟣 OCR / Escaneo
- [ ] **Solo PaddleOCR** (PP-OCRv6 **medium**, el más avanzado). **Tesseract DESHABILITADO** hasta nuevo aviso.
- [ ] **Optimizar al máximo sin limitar** el trabajo (no saltear pasos/orientaciones; sí acelerar).
- [ ] **No debe congelar** la página; barra de progreso visible.
- [ ] El OCR debe **autocompletar TODOS los campos** posibles.
- [ ] **Importes coherentes**: `Total = Neto + IVA + Percepción` (fórmula del Excel).
- [ ] **Razón social** y **Categoría** siempre que se pueda (por CUIT del padrón o heurística).
- [ ] **QR `?p=` (ARCA) = fuente de verdad** cuando trae datos (pisa al OCR).
- [ ] **Código de barras** (CUIT/tipo/PtoVta/CAE) como fuente extra offline.
- [ ] La **verdad absoluta siempre es ARCA** por sobre el OCR (cuando hay dato de ARCA).

## 🧠 Inteligencia / datos
- [ ] Categorización entrenada con el **Excel maestro** (método real de la empresa: categoría por proveedor).
- [ ] **Auto-aprendizaje**: la categoría sugerida del proveedor = la más usada (se corrige sola con el uso).
- [ ] Sección **Proveedores** (ABM: CUIT/razón/categoría) que alimenta el OCR.
- [ ] Nuevas categorías/proveedores se vinculan solos y mejoran con el uso.

## 🗄️ Regla de oro (producción)
- [ ] **Nada se borra físico**: soft-delete (columna oculta) para auditoría. El borrado permanente
      se activa **solo** cuando el cliente diga *"la db no se toca más"*.

## ✅ Proceso de trabajo (dev)
- [ ] Mantener **`md/PROYECTO.md`** actualizado en cada cambio.
- [ ] **Un test de regresión por bug** (batería `test/run_tests.php`).
- [ ] Validar JS (esprima) y PHP (`php -l`) antes de desplegar.
- [ ] Terminología **ARCA** (ex-AFIP desde fines de 2024).
- [ ] Primero **investigar a fondo**, después aplicar. Ser honesto con lo que se puede y no se puede.
