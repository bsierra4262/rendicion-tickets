# Changelog

Todos los cambios notables de este proyecto se documentan acá.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/)
y el versionado, [SemVer](https://semver.org/lang/es/).

## [Unreleased]

### Added
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
