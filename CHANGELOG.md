# Changelog

Todos los cambios notables de este proyecto se documentan acá.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/)
y el versionado, [SemVer](https://semver.org/lang/es/).

## [Unreleased]

### Added
- Padrón nacional offline (~1,13 M empresas) con validación cruzada CUIT ↔ razón social.
- Orientación automática de la foto por IA (modelo ONNX `doc_ori`).
- Unión de varias fotos en un solo comprobante (tickets ultra-largos).
- Rotación de la imagen en el visor (además de zoom y desplazamiento).
- Auditoría completa en el CRON de cada acción y error (cliente y servidor).

### Changed
- Terminología alineada al Excel de rendición: «Categoría» → «Concepto».
- Lectura adaptativa por franjas para recibos largos.

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
