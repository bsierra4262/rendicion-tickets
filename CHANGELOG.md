# Changelog

Todos los cambios notables de este proyecto se documentan acá.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/)
y el versionado, [SemVer](https://semver.org/lang/es/).

## [Unreleased]

### Added
- **Módulo de Alertas centralizado**: motor idempotente + bandeja dentro del sistema
  (campana con contador + **popup/toast**), configurable por el admin. Canales: sistema
  (default), Email (SMTP propio, múltiples destinatarios) e ITHOS/Jarvis (WhatsApp a través
  de ITHOS). Reglas: pago de gasto vencido (días de atraso), por vencer y comprobantes sin
  revisar. **24/7** por cron (Hostinger) + evaluación on-demand. **Modo TEST** (prefijo
  `[TEST]` en los envíos externos hasta pasar a producción). Al activar un canal se
  **reenvían las últimas 5** alertas. Resolver una alerta la saca de la bandeja y la deja en
  el log (CRON). Filtro **«Alertas»** en el CRON.
- **API con permisos (scopes)**: token **Lectura** (consultas, ideal ITHOS) o **Root**
  (acceso total). Endpoint `api/alertas` para que ITHOS consuma y reenvíe a WhatsApp; token
  «ITHOS» generado por defecto. Guía: `md/API-ALERTAS-ITHOS.md`.
- **Re-leer al editar (Gastos)**: la factura/pago ya cargados se re-leen con OCR sin volver a
  subirlos (proxy desde Drive; no se duplican) — como en comprobantes.
- **Proveedor multi-entidad** (bancos): un proveedor puede agrupar varias entidades, cada
  una con su nombre + CUIT; los proveedores de una sola entidad quedan bloqueados a 1 CUIT.
- **Re-escanear al editar**: el comprobante trae su archivo ya subido para volver a leerlo
  con OCR sin re-subirlo (no se duplica en Drive).
- **Módulo Gastos recurrentes**: maestro de proveedores + carga de la factura con respaldo
  en Drive por proveedor/año/mes; auditoría completa.
- **Lectura de factura de gastos** en cascada: QR de ARCA (datos exactos) → texto del PDF →
  OCR de imagen (PaddleOCR) sólo si es un escaneo/foto sin texto ni QR.
- Parser de gastos con soporte de número **AR** (`1.234,56`) y **US** (`1,234.56`) y de
  facturas en inglés (OpenAI/ChatGPT: fecha «September 12, 2025», importe en USD).
- Un proveedor de gastos puede tener **varios CUIT** (expensas/bancos agrupan empresas).
- Padrón nacional offline (~1,13 M empresas) con validación cruzada CUIT ↔ razón social.
- Orientación automática de la foto por IA (modelo ONNX `doc_ori`).
- Unión de varias fotos en un solo comprobante (tickets ultra-largos).
- Rotación de la imagen en el visor (además de zoom y desplazamiento).
- Auditoría completa en el CRON de cada acción y error (cliente y servidor).

### Changed
- La aplicación se sirve desde `/comprobantes` (antes `/tickets`).
- Terminología alineada al Excel de rendición: «Categoría» → «Concepto».
- Lectura adaptativa por franjas para recibos largos.
- Impuesto Inmobiliario/ABL: se toma el monto a pagar y no la valuación del terreno.

### Security
- `config.php` (credenciales de DB + secreto de Google) fuera del control de versiones.

## [1.0.0] - 2026-07

### Added
- Carga de comprobantes con OCR en el navegador (PaddleOCR PP-OCRv6 + QR/código de ARCA).
- Parser fiscal argentino (CUIT módulo 11, tipo/letra, PV-número, importes, IVA, CAE).
- Categorización automática auto-entrenada por proveedor.
- Reportes por mes, proveedor y concepto; export a PDF/CSV.
- Login con Google Workspace (OAuth 2.0) restringido por dominio.
- Batería de tests de regresión.
