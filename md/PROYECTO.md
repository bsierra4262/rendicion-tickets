# PROYECTO MAESTRO — INTELLIHELP · Rendición de Tickets

> Documento maestro del proyecto. **Se actualiza en cada pedido/cambio** (requerimiento del cliente).
> Última actualización: 2026-07-23.

---

## 1. Objetivo
Reemplazar el Excel **"INTELLIHELP - Rendición de Tikets"** por una **aplicación web profesional** que replique sus categorías y funcionalidades, y las mejore. Desplegada en Hostinger, accesible en **https://intellihelp.tech/tickets/**.

## 2. Qué es (dominio)
Sistema de **rendición de gastos / comprobantes que la empresa RECIBE** de proveedores (Argentina: Fact A/B/C, Ticket fiscal "TK", CUIT, IVA, percepciones).
- **No** emite comprobantes → **no** requiere integración con ARCA/AFIP.
- El Excel original: 35 hojas (una por mes, ENE'24 → JUL'26) + hojas en dólares. Listas planas de comprobantes, sin totales.

**Campos por comprobante:** Fecha de Emisión · Tipo (TK/Fact A/B/C) · Letra (A/B/C) · Punto de Venta · Número · Razón social del Proveedor · **Concepto (categoría)** · CUIT · Importe Neto · (Neto U$S) · Tasa IVA · Importe IVA · Importe Percepción · Importe Total.

**Categorías (Concepto)** reales, por frecuencia: Comida, Supermercado, Estacionamiento, Combustible, Insumos, Viáticos, Librería, Peaje, Fletes, Taller, Servicios.

## 3. Decisiones tomadas
- **Usuarios:** equipo con login y **roles** (Admin / Carga).
- **Login:** **100% Google Workspace** (OAuth, app Interna, dominio `intellihelp.tech`). **Sin contraseña.** Admin: `bsierra@intellihelp.tech`.
- **Funciones:** tableros/totales, exportar Excel/PDF, adjuntar foto del comprobante, validación CUIT+IVA, autocompletar proveedor por CUIT.
- **Escaneo IA de tickets:** 100% gratis (ver §8).
- **Datos:** migrar **todo** el histórico (~35 meses).
- **Moneda:** ARS + USD.
- **Orden de trabajo:** servidor + base primero, después construir.

## 4. Identidad visual
- **Fuente:** Saira (Google Fonts).
- **Fondo** `#FFFFFF` · **Primario** `#663399` · **Texto** `#161922` · **Acento** `#9CC930` (solo positivos).

## 5. Stack y arquitectura
- **PHP 8.2** + **MySQL/MariaDB** (sin dependencias pesadas; Composer disponible pero no obligatorio).
- Front controller (`index.php`) + router simple + páginas + vistas + layout.
- Modelo de datos **normalizado** (columna `periodo YYYY-MM` en vez de una hoja por mes).
- Referencias de diseño: Firefly III (modelo de datos), Akaunting (proveedor/categoría/impuesto), InvoicePlane (correr en hosting compartido).

## 6. Estructura de carpetas
### Local (`C:\Users\bsierra\Desktop\APP Tickets\`)
```
app/                      <- TODO el proyecto (se sube al server)
  web/                    <- web root -> public_html/tickets
    index.php  .htaccess
    app/  (bootstrap, db, helpers, auth, google, pages/, views/)
    assets/css/app.css
  db/                     <- schema.sql, fix_conceptos.sql, create_admin.php
  server-config/          <- config.php (se despliega FUERA del web root)
test/                     <- baterías de pruebas (run_tests.php, smoke.sh)
md/                       <- documentación (este archivo)
INTELLIHELP - Rendicion de Tikets (20260708).xlsx   <- fuente (NO se sube)
```
### Servidor (Hostinger)
```
/home/u566451014/domains/intellihelp.tech/public_html/tickets/   <- app (web)
/home/u566451014/tickets_config/config.php                        <- config segura (600)
/home/u566451014/tickets_storage/                                 <- uploads (fuera del web)
/home/u566451014/tickets_build/                                   <- staging (sql, tests)
```

## 7. Base de datos (`u566451014_tickets`)
Tablas: **usuarios** (login+rol), **proveedores** (CUIT único, validado), **conceptos** (11 categorías semilla), **comprobantes** (núcleo), **app_settings**.
- Estados de comprobante: `pendiente_revision` → `verificado` (o `rechazado`).
- Origen: `manual` / `ocr` / `import`.
- Importes en `DECIMAL(15,2)`. Validaciones: CUIT módulo‑11, `Total = Neto + IVA + Percepción`.

## 8. Escaneo de tickets (OCR) — 100% gratis
- **Tesseract.js (WASM) en el navegador** → OCR en el dispositivo, sin costo ni carga en el server, sin binarios.
- **Parser propio** para tickets AR (fecha, CUIT+dv, tipo/letra, PV‑número, importes `1.234,56`, IVA, razón social). Guarda `ocr_text` crudo.
- Flujo: foto → extracción → borrador **Pendiente de revisión** con imagen → persona verifica/corrige → **Verificado**.
- Validaciones que marcan errores solos: CUIT inválido, `Neto+IVA≠Total`.
- Opcional futuro: botón "Mejorar OCR" con OCR.space (nivel gratis).

## 9. Seguridad
- Config sensible fuera del web root (permisos 600). Código de la app bloqueado por `.htaccess` (403).
- Login: contraseñas con `password_hash`, CSRF, sesiones HttpOnly/Secure/SameSite.
- Aislado del WordPress/Wordfence del raíz (`auto_prepend_file none` + rewrite propio), sin tocar la config de producción.
- Google OAuth: `state` anti‑CSRF, verificación de `email_verified`, restricción por dominio `hd`.

## 10. Acceso / despliegue
- **SSH:** `151.106.96.54:65002`, usuario `u566451014` (contraseñas en hPanel, no en este doc).
- Despliegue por **SFTP con paramiko** (scripts `ssh_run.py` / `sftp_put.py`).
- **Regla de despliegue:** SOLO se sube el contenido de `app/`. `test/` y `md/` quedan **solo local**.
- **Login Google:** Client ID `998033647027-...apps.googleusercontent.com`; secret y dominio cargados solo en el config del servidor. Redirect URI: `https://intellihelp.tech/tickets/google-callback`.

## 11. Estado actual
- [x] Investigación y decisiones
- [x] Entorno verificado (PHP 8.2.31, MariaDB 11.8.8, Composer 2.9.8)
- [x] Base de datos creada + categorías
- [x] Scaffold + config segura + aislamiento .htaccess
- [x] Login por contraseña + roles + admin inicial
- [x] **Login con Google Workspace** — 100% Google, probado por el cliente ✓
- [x] CRUD de comprobantes + validaciones (alta/edición/borrado, CUIT, IVA, totales)
- [x] Listado con filtros (mes/categoría/proveedor/estado) + totales por moneda
- [x] Tableros y totales (por categoría, mes, proveedor; ARS/USD)
- [x] Gestión de usuarios (admin: roles y acceso)
- [x] Exportar a CSV (Excel) + PDF por impresión
- [x] Escaneo OCR (Tesseract.js en el navegador) + flujo Pendiente→Verificado + adjuntar/ver foto
- [x] Migrar histórico del Excel — **1.486 comprobantes** (2024-01→2026-07), 483 proveedores, ARS $67.625.702 / USD 4.076,96
- [x] Despliegue y pruebas — 33 tests unitarios + integración + smoke ✓
- [x] **v2.1 — Pulido de UX** (ver §15): file inputs con diseño + drag&drop, selects nativos (sin Tom Select), full-width, tabla con prioridad de columnas + acciones fijas, PDF anti-cache, roles y API explicados, datos de demo. **49/49 tests** + render probe de 7 rutas (0 errores). Desplegado ✓
- [x] **v2.2 — CRON + calendario + fix tabla** (ver §16): auditoría admin (canales Aplicación/API), período con `input month`, totales por moneda, fix del bug de Acciones. Tabla `actividad` + handlers de error. **74/74 tests** + render probe (cron incluido, 0 errores). Desplegado ✓
- [x] **v2.3 — CRON en vivo + IPs + categorías dinámicas** (ver §17): combobox buscable de categorías (no tapa la tabla) + crear al vuelo, IPs reales (`client_ip`), log de navegación (`nav`), auto-refresh en vivo, usuario con foto+email, ventana de 4 meses con histórico. **101/101 tests** + probe (0 errores). Desplegado ✓
- [x] **v2.4 — Diseño unificado** (ver §18): combobox en TODOS los selects, calendarios branded (`datepicker.js`), crear categoría descubrible, CRON en carpetas por tipo, Panel activo con accesos rápidos. **120/120 tests** + probe (0 errores). Desplegado ✓
- [x] **v2.5 — Soft delete + categorías + auditoría** (ver §19): fix dropdown (position:fixed), CRON 15/pág + carpeta Errores fija con código `ERR-XXXXXX`, gestión de categorías (menú), re-aprobar rechazados, y soft delete (regla de oro: se archiva, no se borra). **138/138 tests** + probe (0 errores). Desplegado ✓
- [x] **v2.6 — Papelera de categorías + CRON legible** (ver §20): eliminar categorías (recuperable) con confirmaciones, crear categoría desde el filtro, y CRON con acciones en texto claro ("quién hizo qué"). **147/147 tests** + probe (0 errores). Desplegado ✓
- [x] **v2.7 — Modal profesional + reset OCR** (ver §21): modal de confirmación branded (sin `confirm()` nativo) en toda la app, DB limpia con 14 ejemplos nuevos y CRON vaciado para las pruebas de OCR. **160/160 tests** + probe (0 errores). Desplegado ✓
- [x] **v2.8 — OCR: bugs + parser real** (ver §22): fix "detecta pero no completa" (sync change), fix `NaN`, fix total (SUBTOTAL/OTROS), QR full-res, parser afinado a 3 tickets reales. Validado con esprima. **168/168 tests**. Desplegado ✓
- [x] **v2.9 — OCR: orientación + motor recibos** (ver §23): auto-rotación (fix ticket cabeza abajo), worker con PSM de recibos, preprocesado con upscale. **171/171 tests**. Desplegado ✓

**La app quedó funcional. Histórico real respaldado y reemplazado por datos ficticios de demo para la revisión del cliente. A la espera de su feedback + prueba de OCR con tickets reales.**

## 12. Registro de bugs (regresiones en `test/`)
- **BUG #1** — `google_redirect_uri()` devolvía `http://` → Google lo rechazaba. **Fix:** forzar `https://`. Test: "redirect_uri es HTTPS".
- **BUG #2** — `parse_importe()` malinterpretaba formato US `9,669.42`. **Fix:** detectar AR/US por el separador más a la derecha. Test: "US 9,669.42".

## 13. Cómo correr los tests (LOCAL — `test/` y `md/` NO se suben)
PHP portable local: `C:\Users\bsierra\.tools\php\php.exe` (PHP 8.2, igual que el server).
```powershell
# Unitarios (contra el código local en app/web/app)
& "$env:USERPROFILE\.tools\php\php.exe" "C:\Users\bsierra\Desktop\APP Tickets\test\run_tests.php"
# Smoke HTTP (contra producción, vía Git Bash)
bash "C:\Users\bsierra\Desktop\APP Tickets\test\smoke.sh"
```

## 14. v2 — Profesionalización (2026-07)
Referencia de diseño: **APP Remitos** (`C:\Users\bsierra\Desktop\APP Remitos\app`, solo lectura).
- **Diseño pro:** tokens tipo Remitos (sombra violácea, radio 15px, pills, gris `#6f6a7d`), **iconos SVG** (`app/icons.php`, función `icon()`, sin emojis), responsive total (nav hamburguesa, tablas→tarjetas con `data-label`).
- **Marca:** logos ITH + favicon en `assets/img/`; avatar de Google (`usuarios.avatar_url`, refrescado en cada login) en barra y Usuarios; logo blanco `ITH-logo-white.png` (generado con PIL) para el header del PDF.
- **Foto en la tabla** de comprobantes + **GLightbox** para ampliar (imagen/PDF).
- **Reportes:** gráficos **ApexCharts** (área mensual + barras categoría/proveedor) + **PDF generado** con **Dompdf** (`views/pdf/reporte.php`, vendor en `/home/u566451014/tickets_vendor`), diseño tipo referencia (header violeta, KPIs, zebra, footer paginado).
- **OCR pro** (`assets/js/escanear.js`): **pdf.js** (PDF→canvas) + **QR de AFIP** (jsQR, datos exactos) + **Tesseract** con preprocesado (gris+Otsu) + **autoclasificación** por CUIT (`proveedores.categoria_sugerida_id`) y reglas de comercio. Flujo Pendiente→Verificar.
- **API de solo lectura** (para Jarvis): tabla `api_tokens`, `app/apiauth.php`, rutas `api/estado|comprobantes|pendientes` con header `X-API-Key`, panel admin `api-keys`.
- **UI:** ~~Tom Select~~ (removido en v2.1 — ver §15). **Roles:** borrar comprobantes = solo Admin.
- **Insumos del cliente:** `TICKSTS ESCANEADOS ORIGINALES/` (376 PDF escaneados, imagen pura) para probar/afinar OCR; `Exportaciones de datos/` (backup de lo migrado).
- **Componentes CDN (pineados):** ApexCharts 4.5.0, GLightbox 3.3.1, pdf.js 3.11.174 (legacy UMD), jsQR 1.4.0, Tesseract.js 5. *(Tom Select retirado.)*

## 15. v2.1 — Pulido de UX (2026-07-22)
Feedback del cliente: file inputs sin diseño, filtros/selects confusos con bugs (Tom Select dejaba "escribir" sin serlo), poco aprovechamiento del ancho, tener que scrollear al fondo para ver Foto/Editar, PDF cacheado, roles no visibles, API sin explicar, y DB con histórico real en vez de datos de prueba.
- **File inputs con diseño** (`assets/js/filefield.js` + CSS `.filefield*`): zona con ícono, texto, **drag & drop** y nombre de archivo. En **Escanear** (conserva `id="scanFile"` para el OCR) y en **adjuntar** del formulario. Técnica: `<input>` oculto accesible + `<label>` estilizado.
- **Tom Select retirado:** los `<select>` ahora son **nativos estilizados** (CSS `appearance:none` + caret SVG violeta al foco). Se eliminó su CSS/JS del `layout.php` y la clase `.ts` de los campos. Adiós al bug de "parece campo de texto".
- **Full-width:** `.content` a `max-width:1500px` con padding fluido (`clamp`), aprovecha pantallas grandes.
- **Tabla de comprobantes rediseñada:** fecha **condensada** dentro de la celda "Comprobante"; **prioridad de columnas** (`prio-2/3/4` se ocultan progresivamente al angostar antes de pasar a modo tarjeta); `tfoot` reestructurado por columnas (sin `colspan` frágil). *(La columna Acciones sticky y el scroll interno se revirtieron en §16 por bug visual.)*
- **PDF anti-cache:** headers `no-store/no-cache` + `Content-Disposition: inline` y `&_=<time()>` en el link (ver `pages/reporte-pdf.php`).
- **Roles visibles** (`views/usuarios.php`): tarjetas "Administrador (acceso total)" vs "Carga (operativo)".
- **API explicada** (`views/api-keys.php`): intro sin cron, tabla de endpoints, y **probador en vivo** (fetch con el token → JSON).
- **DB de demo:** histórico real vaciado (backup en `Exportaciones de datos/`), sembrados **14 comprobantes ficticios** (prefijo `DEMO ·`, may–jul 2026, ARS+USD, estados variados). **Limpieza:** `DELETE FROM comprobantes WHERE observaciones LIKE 'DEMO —%';`
- **Carpeta `db/` duplicada en la raíz eliminada** (la buena vive en `app/db/`).
- **CSS versionado** a `?v=5` en `layout.php` para forzar refresco en Hostinger.
- **Regresión:** +16 tests en `test/run_tests.php` (invariantes de las vistas/CSS/JS) → **49/49**. Verificado además con un *render probe* de las 7 rutas privadas (sesión admin uid=2, DB real): full HTML y 0 errores.

## 16. v2.2 — CRON (auditoría), calendario y fix de la tabla (2026-07-22)
Feedback: el bug de "Acciones" volvió (columna sticky se veía como tarjetas flotando); los totales deben ser por moneda seleccionada (no mezclar ARS+USD); filtros de período como **calendario**; y una sección **CRON** (admin) al lado de API con el log total de la app + un log de la API, separados.
- **Fix tabla Acciones:** el `td.acts` era `display:flex` **y** `position:sticky` → la celda no tomaba el alto de la fila y el fondo pegajoso quedaba corto (efecto "tarjetas flotando"). Se **quitó la columna sticky** (redundante: el sistema `prio-*` ya garantiza que "Total" y "Acciones" entran sin scroll horizontal) y el **scroll interno vertical** (adiós doble barra). Tabla estándar, `.acts` como celda normal.
- **Totales por moneda:** el `tfoot` de comprobantes ahora solo se muestra cuando hay **una moneda filtrada** (ARS *o* USD). En "Todas" no se muestra total (sumar monedas distintas no tiene sentido). Reportes ya trabaja siempre con una moneda.
- **Período = calendario:** en Comprobantes y Reportes el `<select>` de período pasó a **`<input type="month">`** (selector de mes nativo, mismo formato `YYYY-MM`, cero dependencias). Reportes suma botón "Ver todos los meses".
- **Sección CRON (admin, `nav` al lado de API):** bitácora de auditoría con **dos canales** (control segmentado):
  - **Aplicación** — movimientos de usuarios (crear/editar/verificar/rechazar/eliminar comprobantes, gestión de usuarios y tokens, ingreso/salida) **+ errores** del sistema.
  - **API** — cada pedido a la API de solo lectura (token, endpoint, IP, rechazos 401).
  - Tabla nueva `actividad` (`app/db/actividad.sql`), módulo `app/actividad.php` (`log_evento/log_app/log_api`, `actividad_list`, `actividad_resumen`), página `pages/cron.php` + `views/cron.php` (filtros tipo/fecha/búsqueda, stats, paginación).
  - **Instrumentado** en el modelo (`comp_save/comp_delete/comp_set_estado`), `auth` (login/logout), `usuarios`, `tokens` y `apiauth` (cada request). **Handlers de error** en `bootstrap.php` (`set_exception_handler` + shutdown) capturan fatales aunque `display_errors=0`. El log **nunca** rompe la request (todo en try/catch; degrada con gracia si falta la tabla).
- **Regresión:** +25 tests (unidad de `actividad_where`/`comp_etiqueta` + invariantes CRON/calendario/tabla) → **74/74** local y server. Render probe de las 7 rutas (incluida `cron`): full HTML, 0 errores. Logging verificado end-to-end (escribe/lee, actor resuelto). CSS a `?v=6`.

## 17. v2.3 — CRON en vivo, IPs reales y categorías dinámicas (2026-07-22)
Feedback: el desplegable de categorías tapaba la tabla y no se podían crear nuevas; el CRON no registraba IPs ni "todo" lo que hace el usuario; en el CRON el usuario debería verse con foto+email; y el CRON debía actualizarse en tiempo real, mostrar solo lo reciente (sin lag) y conservar el histórico.
- **Combobox de categorías** (`assets/js/combobox.js` + CSS `.cbx*`): convierte el `<select data-combobox>` en un desplegable **buscable y acotado** (panel con scroll, máx. ~264px — ya no tapa la tabla). Con `data-combobox-create="URL"` agrega **"Crear «texto»"** para dar de alta una categoría al vuelo. Aplicado al **filtro** (Comprobantes, sin crear) y al **formulario** (con crear). El `<select>` nativo queda oculto como fuente de verdad; se le quita `required` para evitar el bug "invalid form control not focusable" (valida el server).
  - Backend: `concepto_create()` (dedup case-insensitive, reactiva inactivas, log `categoria.crear`), endpoint `pages/concepto-crear.php` (JSON + CSRF), ruta `concepto-crear`. CSRF disponible en JS vía `<meta name="csrf">` en el layout.
- **IPs reales** (`helpers.php::client_ip()`): lee `CF-Connecting-IP / X-Real-IP / X-Forwarded-For / Client-IP / REMOTE_ADDR` (primer IP válido). `REMOTE_ADDR` en hosting compartido era el proxy. El log ahora guarda el IP real del cliente.
- **Log "de todo"**: además de las mutaciones, el front controller (`index.php`) registra la **navegación** (tipo `nav`) de cada página visitada (excluye el propio CRON y endpoints técnicos para no ensuciar). Nuevo valor `nav` en el enum `actividad.tipo` (ALTER aplicado).
- **CRON en tiempo real** (`assets/js/cron-live.js`): switch **"En vivo"** (por defecto ON) que cada ~15s re-pide la misma URL y reemplaza stats (`#cronStats`) + cuerpo de tabla (`#cronBody`) sin recargar; se pausa si la pestaña está oculta o si estás escribiendo en un filtro. El botón "Actualizar" también refresca al toque.
- **Usuario con foto + email** en la bitácora (canal Aplicación): avatar de Google + nombre + email (join a `usuarios.avatar_url`).
- **Ventana + rendimiento**: por defecto muestra los **últimos 4 meses** (`ACTIVIDAD_VENTANA_MESES`), con paginación de 50. El **histórico nunca se borra**: para consultarlo se elige una fecha **«Desde»** anterior (una nota en pantalla lo explica).
- **Regresión:** +27 tests → **101/101** local y server. Render probe (cron/comprobantes/form/reportes/dashboard): full HTML, 0 errores. Verificado end-to-end: IP real capturada (`201.11.22.33` desde X-Forwarded-For), `nav` logueado, avatar resuelto, `concepto_create` inserta/dedup. CSS a `?v=7`.

## 18. v2.4 — Diseño unificado: combobox global, calendarios branded, CRON en carpetas, panel activo (2026-07-22)
Feedback: no se veía cómo crear categorías; los calendarios nativos no eran profesionales; el combobox lindo debía estar en TODOS los selects (Estado, Moneda, roles…), no solo categorías; el CRON se alargaba y convenía organizarlo en "carpetas" por tipo; y el Panel seguía con texto de "próximos pasos" como si estuviera inactivo.
- **Combobox en todos los selects** (`combobox.js` reescrito): ahora `querySelectorAll('select')` — Estado, Moneda, roles, Tipo/Letra/Tasa, etc. tienen el mismo panel branded. Buscador sólo si hay > 7 opciones o permite crear (`.cbx--nosearch` para los chicos). Opt-out con `data-no-combobox`.
- **Crear categoría descubrible**: en el combobox de categoría (form) hay una fila fija **"+ Crear categoría nueva"** siempre visible; al escribir un nombre nuevo pasa a **"Crear «X»"**. Pista bajo el campo. (El create sólo aplica a categorías vía `data-combobox-create`.)
- **Calendarios profesionales** (`datepicker.js` + CSS `.dp*`): reemplaza los `<input type="month|date>` nativos por un **calendario branded** (violeta ITH): mes → grilla de 12 meses con navegación de año; fecha → grilla de días con semana en español, hoy/seleccionado resaltados, min/max respetados, pie "Borrar / Hoy(Este mes)". Progressive-enhancement (si el JS falla, queda el nativo). Un `<input type=hidden>` conserva el name/value ISO; `data-autosubmit` en filtros que refrescan solos (Reportes).
- **CRON en carpetas** (`views/cron.php` + `actividad_conteo_tipos()`): chips-carpeta por tipo con conteo — **Todos · Navegación · Modificaciones · Sesiones · Errores · Sistema** — para encontrar rápido. Reemplazan el dropdown de Tipo. Se ocultan las carpetas vacías. Paginación de 50 (igual que Comprobantes) + ventana de 4 meses ya existentes.
- **Panel activo** (`views/dashboard.php`): fuera el texto "Próximos pasos". Ahora tarjetas de stats **clicables** (van a Comprobantes/Pendientes/Reportes) + **Accesos rápidos** (Escanear, Nuevo comprobante, Ver comprobantes, Reportes, y admin: Usuarios, CRON).
- **Regresión:** +19 tests → **120/120** local y server. Render probe de 7 rutas: full HTML, 0 errores. Assets en vivo (`app.css?v=8`, `combobox.js?v=2`, `datepicker.js`). CSS a `?v=8`.

## 19. v2.5 — Soft delete, gestión de categorías, re-aprobar, errores con código (2026-07-22)
Feedback: faltaba carpeta Errores fija + código para auditar fallos; bug del dropdown de roles en Usuarios (lo recortaba la tabla); no se encontraba dónde crear categorías; un rechazado no se podía re-aprobar; y la **regla de oro**: lo que se borra no se borra (queda oculto para auditoría). Además el CRON mostraba demasiadas filas.
- **Bug dropdown (Usuarios) resuelto**: los paneles de combobox y datepicker ahora se posicionan con **`position:fixed`** (calculado desde el toggle, reubicado en scroll/resize) para **escapar de contenedores con `overflow`** (la tabla los recortaba). Aplica a todos los selects y calendarios.
- **CRON**: paginación bajada a **15 filas** por pantalla (como Comprobantes); carpetas **Errores** y **Sistema** siempre visibles aunque estén en 0.
- **Errores con código de auditoría** (`log_error()` + handlers en `bootstrap.php`): cada error genera un **`ERR-XXXXXX`** estable por `archivo:línea` (mismo lugar → mismo código) con severidad, mensaje y ubicación. Ahora también se capturan **warnings** de PHP (`set_error_handler`, ignora notices/deprecations, guard anti-recursión). Todo cae en la carpeta Errores del CRON.
- **Gestión de categorías** (nueva sección en el menú, `views/categorias.php` + `pages/categorias.php`/`categoria-guardar.php`): **campo para agregar** categorías, tabla con conteo de uso, renombrar y activar/desactivar (`conceptos_all()`, `concepto_update()`). Se suma al create al vuelo del combobox. Ruta `categorias`/`categoria-guardar` para todos los usuarios.
- **Re-aprobar rechazados** (`comprobante-form`): botones de estado desde **cualquier** estado — un rechazado se puede **Aprobar (verificar)** o Volver a pendiente; un verificado se puede Rechazar/pendiente. Badge de estado actual en el encabezado. Controlador acepta `estado_action=pendiente`.
- **Soft delete (regla de oro)**: `comprobantes` suma `eliminado_en` + `eliminado_por` (migración `db/soft_delete.sql`). `comp_delete()` **archiva** (UPDATE) en vez de borrar; `comp_restore()` recupera. Todas las consultas excluyen archivados (`comp_where`, `comp_find`, `rep_*`, `periodos_disponibles`, dashboard, `api/estado`, `api/comprobantes`, `api/pendientes`). Los usuarios no los ven, pero **siguen en la base** para auditoría y el borrado queda en el CRON (detalle "archivado (recuperable)").
- **Regresión:** +18 tests → **138/138** local y server. Render probe de 8 rutas: full HTML, 0 errores. Verificado end-to-end: soft delete oculta pero conserva la fila (`eliminado_en`/`por`), `comp_restore` recupera, borrado logueado con IP. `combobox.js?v=3`, `datepicker.js?v=2`.

## 20. v2.6 — Eliminar categorías (papelera), crear desde el filtro, CRON legible (2026-07-22)
Feedback: poder eliminar categorías (recuperable, con confirmaciones al crear y borrar), crear categoría también desde el filtro de Comprobantes, y que el CRON muestre "literalmente todo" — quién creó/eliminó qué — de forma legible.
- **Eliminar categorías (soft delete + papelera)**: `conceptos` suma `eliminado_en` (migración `db/cat_softdelete.sql`). `concepto_delete()` archiva (recuperable), `concepto_restore()` recupera; `conceptos_deleted()` alimenta la **papelera** en la sección Categorías. `conceptos_list()`/`conceptos_all()` excluyen las eliminadas; los comprobantes que usaban la categoría conservan el nombre (la fila sigue en la base). `concepto_create()` desarchiva/reactiva si el nombre ya existía.
- **Confirmaciones**: al **crear** (form de Categorías + combobox: `confirm('¿Crear la categoría «X»?')`) y al **eliminar** (`confirm` con el nombre; se archiva, recuperable).
- **Crear desde el filtro de Comprobantes**: el combobox del filtro ahora también tiene `data-combobox-create` → "Crear «X»" disponible al buscar/filtrar, no sólo en el formulario.
- **CRON legible ("quién hizo qué")**: la columna Acción muestra una **frase clara** (mapa `accionLabel`: "Creó una categoría", "Archivó un comprobante", "Verificó un comprobante", "Inició sesión", "Navegó a «…»", "Error de PHP"…) con el código técnico (`categoria.crear`) debajo. Se agregó log de **exportación** de CSV (`comprobantes.exportar`).
- **Regresión:** +9 tests → **147/147** local y server. Render probe (categorías/comprobantes/cron): 0 errores. Verificado end-to-end: ciclo crear→eliminar (papelera)→restaurar y el CRON registró las 3 acciones con actor + detalle + IP. `combobox.js?v=4`.

## 21. v2.7 — Modal de confirmación profesional + reset de datos para OCR (2026-07-22)
Feedback: las confirmaciones salían con el `confirm()` nativo del navegador ("intellihelp.tech dice…"); querés un modal profesional propio en TODA la app. Y dejar la DB limpia con ejemplos nuevos + el CRON vacío para registrar desde cero las pruebas de OCR.
- **Modal branded** (`assets/js/confirm.js` + CSS `.modal-overlay`): reemplaza `confirm()` en todo el sistema. Overlay con blur, tarjeta con ícono (pregunta / alerta roja para acciones peligrosas), título, mensaje y botones **Cancelar/Aceptar** (label configurable). Teclado (Enter/Escape), click afuera cierra.
  - Declarativo: `data-confirm="…"` en `<form>` o `<button>` (+ `data-confirm-danger`, `data-confirm-ok`), y `data-confirm-link` en `<a>`. Programático: `window.appConfirm(msg|opts)` → Promise.
  - Reemplazados los 6 `confirm()` nativos: eliminar comprobante, rechazar comprobante, crear categoría (form + combobox vía `appConfirm`), eliminar categoría, eliminar token. Todos ahora con el modal (los de borrado en rojo).
- **Reset de datos**: DB limpia (borrados todos los comprobantes) + **14 ejemplos DEMO nuevos**, y **CRON vaciado** (`TRUNCATE actividad`). El registro arranca en 0 para capturar limpio las pruebas de OCR del cliente.
- **Regresión:** +13 tests → **160/160** local y server. Render probe (comprobantes/categorías/api-keys): 0 errores. `confirm.js?v=1`, `combobox.js?v=5`, CSS `?v=9`.

## 22. v2.8 — OCR: bugs críticos + parser afinado con tickets reales (2026-07-22)
El cliente probó el OCR con 3 tickets reales (DIA Factura A, café Blueberry Factura B con QR, Axión Tique Factura A). Bugs encontrados y arreglados en `assets/js/escanear.js`:
- **Detectaba pero no completaba los campos**: `setVal()` disparaba sólo `input`; los `<select>`/fecha ahora están envueltos en combobox/datepicker que sincronizan con **`change`**. Fix: `setVal()` dispara `input` **y** `change`; el datepicker escucha `change` en su input oculto; la autoclasificación también dispara `change`. Ahora los datos leídos se ven en el form.
- **`NaN` en Importe neto**: `labeled(U,'NETO|GRAVADO|SUBTOTAL')` tenía la alternancia **sin agrupar** → matcheaba "NETO" sin capturar número → `NaN`. Fix: agrupar en `(?:…)` y descartar `NaN`.
- **Total mal leído**: confundía "SUBTOTAL" e "IMPORTE TOTAL OTROS TRIBUTOS" con el total. Fix: `\bTOTAL\b(?!\s+OTROS)` (el `\b` excluye SUBTOTAL) y toma el mayor.
- **QR "0 campos"**: se decodificaba en una copia reducida a 1000px que destruía QRs chicos. Fix: se intenta a **resolución completa** y varias escalas; se agregaron los tipos AFIP 81/82/83 (tique factura).
- **Parser afinado a formatos reales**: tipo con comillas/`TIQUE FACTURA`/`Cód.001/006/011/081`/`Código 6`; fecha etiquetada; `P.V.` + `Nro`/`N°`; **IVA sumando alícuotas** (`IVA 21,00% … + IVA 10,50% …`, `ALICUOTA 21,00%`, `IVA CONTENIDO`) y set de `Tasa IVA` si hay una sola; neto por `NETO GRAVADO/SIN IVA`; **total se setea al final** para que el autocálculo no lo pise.
- **Validación**: los 4 JS pasan por **esprima** (parser real). +8 tests → **168/168**. `escanear.js?v=3`, `datepicker.js?v=3`.

## 23. v2.9 — OCR: orientación automática, motor de recibos y upscale (2026-07-22)
El cliente pidió "entrenarlo lo más posible". Aclaración honesta: Tesseract (OCR gratis en el navegador) **no es un modelo de IA de la nube** (Vision/GPT); esos son pagos y con API key, fuera del requisito 100% gratis. Se lo exprimió al máximo:
- **Orientación automática** (`ocrWithRotation`): el ticket 1 estaba **cabeza abajo** → Tesseract leía basura. Ahora se prueba 0°→180°→90°→270° y se elige la de mayor puntaje (`scoreText` cuenta CUIT/TOTAL/FACTURA/fechas/montos). Para fotos bien orientadas corta en 1 pasada; giradas, 2. Además el **QR** ya se lee a resolución completa (jsQR maneja rotación), así que el ticket 1 puede resolverse entero por QR.
- **Motor configurado para recibos**: se pasó a la API de **worker persistente** de Tesseract.js con `tessedit_pageseg_mode = 6` (bloque uniforme) y `preserve_interword_spaces`; el worker se reutiliza entre escaneos (más rápido).
- **Preprocesado con upscale**: se agranda el texto chico (~1800px lado mayor) antes del gris + Otsu — sube bastante la precisión en tickets fotografiados de lejos.
- **Validación**: esprima OK. +3 tests → **171/171**. `escanear.js?v=4`.
- **Expectativa realista**: sigue siendo lectura + heurísticas + validación humana (no 100% automático). Es **iterativo**: con cada tanda de tickets reales que el cliente pruebe, se afinan patrones. Para nivel "IA" real haría falta una API de visión paga (Google Document AI / Vision / etc.), que hoy está descartada por el requisito de costo cero.

## 24. v2.10 — OCR: montos, feedback de campos, etapas + UX (2026-07-22)
2ª tanda de pruebas del cliente (8 tickets reales). Resultados y arreglos:
- **Bug de montos (crítico)**: los totales salían mal (7800→800, 22700→700, 16800→800). El regex de monto no matcheaba **miles sin separador** (`7800.00`) y agarraba sólo los últimos 3 dígitos. Fix: `MONEY` soporta miles con separador **y** `\d+[.,]\d{2}` (sin separador). Aplicado a total/IVA/neto/amounts.
- **Feedback de campos** (`showSummary`): en vez de "N campos", ahora lista **"✓ Detecté y cargué: Fecha · Tipo · CUIT · Total"** y **"✎ Completá a mano: … · Categoría"**, para que el usuario sepa exactamente qué falta.
- **Progreso por etapas**: el OCR corre **siempre las 4 orientaciones** (QR + 0°/180°/90°/270°) mostrando **"Escaneo X de 4 · orientación N°"**, así no parece que se cuelga repitiéndose. Cada ticket pasa por todo el pipeline.
- **Reset de caché** (`resetScan` + `pageshow`): al recargar / volver atrás / cancelar, el archivo y la previsualización quedaban cacheados; ahora se limpian solos.
- **UX**: el "+" de "Crear categoría nueva" pasó a **SVG** (centrado perfecto); confirmado que el filtro de Comprobantes también permite crear categoría (`data-combobox-create`).
- **Modo testing (DB)**: se limpió todo — comprobantes → 14 DEMO frescos, categorías → **solo las 11 originales** (se borró `RDP` y test), **CRON vaciado**. (Ver nota: mientras dure el testing la DB se limpia en cada iteración; el borrado permanente se activa recién cuando el cliente lo indique.)
- **Validación**: esprima OK. +7 tests → **178/178**. `escanear.js?v=5`, `combobox.js?v=6`, CSS `?v=10`.

## 25. v2.11–2.12 — OCR afinado con OCR real + investigación (2026-07-23)
El cliente no tiene más fotos nuevas → se pasó a **iteración empírica del lado del dev**: se instaló **RapidOCR (ONNX, motor de PaddleOCR)** localmente para correr OCR real sobre los 9 tickets, se armó un **espejo del parser en Python** (`scratchpad/parser.py`) con la verdad esperada de cada ticket, y se ajustaron los regex hasta que el parser extrae bien **7/9 tickets completos**; los 2 restantes fallan **sólo por texto mal leído por el OCR** (ticket arrugado con datos concatenados, CUIT 66→68), no por el parser.
- **Fixes del parser (verificados contra texto OCR real):**
  - Total en el **renglón siguiente** a "TOTAL": el hueco sólo admite conectores (`$ : . - espacio/salto`), no letras → agarra "TOTAL␤$91.336,08" pero NO "TOTAL␤IVA:$3.939" (cae al máximo real). + tope `CAP=1e8` descarta basura del OCR (concatenación de códigos → números gigantes).
  - **PV/Número** aunque venga pegado: `(?:\D|^)(\d{4,5})[-–](\d{6,8})` (antes pedía borde de palabra → fallaba con "N05148-00035742").
  - **Letra** pegada a FACTURA: captura la letra tras "FACTURA" (antes `A\b` fallaba con "FACTURAAORIGINAL").
  - **Fecha** sin borde inicial (venía pegada a otros dígitos).
- **QR robusto** (v2.11): rotaciones + recortes (arriba/abajo) + escalas; se detectan los **2 formatos AFIP**: factura electrónica `?p=<base64 JSON>` (trae datos exactos → fuente de verdad) y tique de controlador fiscal `qr.afip.gob.ar/?qr=CÓDIGO` (sólo código, **sin datos**). En estas fotos de baja resolución ni pyzbar/opencv decodifican; con fotos de celular reales el QR rinde mejor.
- **Formato ARS/USD**: importes legibles `6.500,00` (`fmtAR` con `toLocaleString('es-AR')`), tanto al cargar del OCR como en el autocálculo del formulario.
- **Razón social**: si no hay CUIT, se toma de la primera línea con nombre; con CUIT la trae el padrón local (`proveedores`).
- **Optimización de velocidad (sin saltear pasos)**: detección de orientación en **baja resolución** (4 pasadas rápidas) + **una** lectura final a resolución completa en la mejor orientación (antes eran 4 pasadas full).
- **Investigación (pedida por el cliente):**
  - **API pública CUIT→razón social gratis/ilimitada/sin registro: no existe una confiable.** Las opciones son webs de consulta (cuitonline, migestion), datasets oficiales descargables (Registro Nacional de Sociedades — sólo sociedades) o APIs pagas (Cuitalizer, TusFacturas). Camino 100% gratis real = la **DB local de proveedores que crece** con cada carga (ya implementado); opcionalmente importar el dataset oficial a futuro.
  - **OCR open-source mejor que Tesseract: sí existen (PaddleOCR, docTR, LLMs), pero requieren Python/servidor** → no corren en el stack actual (PHP compartido + navegador) sin costo. **Tesseract.js es la única opción libre in-browser.** PaddleOCR podría correr in-browser vía `onnxruntime-web` (WASM) — es posible pero es una integración grande (~10MB de modelos) y no testeable acá; además, incluso RapidOCR (=PaddleOCR, el "mejor" libre) **también** malinterpreta estas fotos de baja calidad → cambiar de motor no arregla los 2 pendientes. Conclusión: **Tesseract.js + parser afinado + QR-como-verdad + validación humana** es lo correcto para 100% gratis.
  - **Ningún OCR trae la estructura solo** — siempre hace falta una capa de parseo propia (confirmado por la investigación).
- **Validación**: esprima OK. +11 tests → **189/189**. `escanear.js?v=7`, `comprobante-form.js?v=3`.

## 26. v2.13 — PaddleOCR (PP-OCRv6) como motor principal, con fallback (2026-07-23)
El cliente pidió el **estudio comparativo a fondo** y usar el mejor motor open-source. Se hizo con **números reales**:
- **Estudio comparativo** (`scratchpad/compare.py`): mismos 9 tickets, **mismo parser**, contra la verdad de cada uno. Se instaló Tesseract 5.4 (spa+eng) local y se comparó con PaddleOCR (RapidOCR/ONNX). Resultado: **Tesseract 21/32 (66%)** vs **PaddleOCR 25/32 (78%)** campos correctos → **PaddleOCR gana por +12 pts** (mejor en 1/3/5, empata en 4/6/7; Tesseract ganó sólo en el 9). No hay un OCR libre mejor que corra en el navegador: los VLM/LLM (Qwen-VL, Florence) son más robustos con papeles arrugados pero necesitan servidor/GPU (rompen "100% gratis").
- **Implementación**: motor **PaddleOCR PP-OCRv6** en el navegador vía **onnxruntime-web** + paquete open-source **`ppu-paddle-ocr`** (cargado por ESM desde esm.sh; modelos ~6MB auto-fetch + cacheados; WebGPU con fallback a WASM). Nuevo `assets/js/paddleocr.js` expone `window.PaddleOCR.{ready, recognize}`.
- **Arquitectura con fallback** (`escanear.js`): `engine` conmutable — si PaddleOCR carga e inicializa → se usa (mejor); si falla o tarda >90s → **cae automáticamente a Tesseract.js** (nunca se rompe). `recognize()` abstrae el motor; Tesseract usa gris+Otsu, PaddleOCR usa color redimensionado. El resumen muestra **qué motor** se usó.
- **Nota honesta**: no se pudo testear la parte WASM/onnxruntime del navegador desde el entorno de dev (sólo el cliente puede en su browser); por eso el fallback protege. La 1ª lectura baja ~6MB de modelos (después quedan cacheados). Como es open-source e integrado, se puede afinar (modelos server/INT8, config) a futuro.
- **Validación**: esprima OK (escanear.js, paddleocr.js). +8 tests → **197/197**. `escanear.js?v=8`, `paddleocr.js?v=1`.

## 27. v2.15 — PaddleOCR medium, entrenamiento con el Excel, importes coherentes, Proveedores + auto-aprendizaje (2026-07-23)
Segunda tanda de pruebas reales del cliente (9 tickets, en el navegador con PaddleOCR). Se atacó todo con datos reales.
- **Estudio de modelos PP-OCRv6** (Node + onnxruntime, mismos 9 tickets, mismo parser vs verdad): **tiny 75% · small 78% · medium 81%**. Tiempos (4 orientaciones): tiny 3s, small 5s, **medium 23s** (57s el peor). Se eligió **medium** (el más avanzado, pedido del cliente) — **sin limitar** las 4 pasadas — y se lo hace viable: **WebGPU** (2–5×), detección en baja resolución + 1 lectura final, y **barra de progreso** para que nunca parezca colgado. Modelo configurable en `paddleocr.js` (`MODEL_TIER`).
- **Análisis EXHAUSTIVO del Excel maestro** (`scratchpad/audit.py`, `extract.py`): 35 hojas mensuales, **1771 comprobantes reales**. No hay macros/VBA ni hojas/filas/columnas ocultas ni rangos con nombre. El "método" de la empresa: **la categoría es manual por proveedor** (cada proveedor → su categoría), y la única fórmula es **Total = Neto + IVA + Percepción** (N=J+L+M). Las 11 categorías de la app coinciden exactamente con las del Excel.
- **Entrenamiento con el Excel**: se extrajo el mapa **CUIT→(razón, categoría)** (78 CUITs) y **proveedor→categoría** (545). Se **sembró el padrón** (`proveedores`, +columna `categoria_sugerida_id`) → **522 proveedores con categoría sugerida**. Ahora, al leer el CUIT, el OCR autocompleta **razón social y categoría** reales (vía `api-proveedor`).
- **Importes 100% autocompletados y coherentes** (`parseAndFill`): se extrae Neto/IVA/Tasa/Percepción/Total y se **reconcilia** con la fórmula del Excel (deriva lo que falte para que Neto+IVA+Percep=Total). Elimina el "el total no coincide" (ej. ticket 6: 7.800 = 6.446,28 + 1.353,72).
- **Razón social siempre**: etiqueta explícita "Razón social:" → si no, primera línea "nombre" (aún con CUIT); el padrón la refina si el CUIT está registrado.
- **Categorizador inteligente por puntaje**: cada categoría con su **descripción + palabras clave**; gana la de mayor coincidencia (no el primer match). Se ejecuta siempre (aún con CUIT).
- **Auto-aprendizaje** (`prov_entrenar_categoria`, en `comp_save`): tras cada guardado, la categoría sugerida del proveedor = la **más usada** por los usuarios para ese proveedor → proveedor nuevo aprende de la 1ª carga y se corrige solo con el uso (nunca queda "clavado"). 100% gratis, sin IA externa.
- **Nueva sección Proveedores** (`/proveedores`): ABM del catálogo (CUIT · razón · categoría sugerida), buscador, alta/edición; alimenta el autocompletado del OCR. Link en el nav.
- **Auto-verificación (dev)**: con el texto real de medium + pipeline completo → **6/9 tickets se completan enteros** (1,3,4,6,7,9: fecha, tipo, PV, nº, CUIT, razón, categoría e importes coherentes); 2,5,8 quedan parciales por ser fotos arrugadas/rotas que el OCR malinterpreta (el QR las rescata en foto nítida).
- **Validación**: esprima + php -l OK. +18 tests → **215/215**. `escanear.js?v=9`, `paddleocr.js?v=2`. DB de testing reseteada (14 DEMO, CRON vacío, 11 categorías).

## 28. v2.16 — PaddleOCR en Web Worker (no congela), visor de imagen, filtro (2026-07-23)
Feedback de la 2ª prueba real:
- **"La página no responde" / barra invisible**: PaddleOCR (onnxruntime-web WASM) corría en el **hilo principal** → congelaba. Se movió a un **Web Worker** dedicado (`paddleocr.js`): el hilo principal queda libre, la barra de progreso se anima y **no se traba**, sin limitar nada (mismo medium + 4 orientaciones). Se transfiere la imagen como `ImageBitmap` y el worker devuelve el texto. Si no hay Worker/OffscreenCanvas o algo falla → Tesseract (que ya usa worker). Tesseract nunca congelaba por eso mismo.
- **Visor "Ver comprobante" en blanco**: GLightbox no detectaba el tipo porque la URL (`comprobante-imagen?id=`) no tiene extensión → se fuerza `data-type="image"` (o `iframe` para PDF).
- **Filtro de Comprobantes**: se quitó "Crear categoría nueva" del **filtro** (se crea en la pestaña Categorías o al editar). Se mantiene al **editar** un comprobante.
- **QR (ARCA, ex-AFIP)**: se agregó **binarización Otsu + upscale** al lector → decodifica más QR. Verificado (pyzbar) que los 9 tickets **no** tienen QR con datos (`?p=`); los que decodifican (4, 5) son `?qr=` (sólo código, sin datos), así que el QR no rescata esos campos. Para tickets 2/5/8 (arrugados/rotos) ningún método gratis extrae los datos: el QR no los lleva y la foto está dañada → se completan a mano; con foto nítida y plana PaddleOCR los lee bien.
- **Validación**: +6 tests → **220/220**. `escanear.js?v=10`, `paddleocr.js?v=3`.

## 29. v2.17 — PaddleOCR estable (revert del worker), Tesseract off, conclusión ARCA (2026-07-23)
- **Bug: PaddleOCR no cargaba.** El intento de correrlo en un **Web Worker** (v3) rompía la inicialización en el navegador del cliente (ppu-paddle-ocr/onnxruntime tocan APIs que no están en el worker; la CSP del sitio es sólo `upgrade-insecure-requests`, no era eso). Se **revirtió al motor en el hilo principal** (el que leía los 9 tickets). Para el freeze: la búsqueda de QR ahora es liviana (copia ≤1400px) y se **cede el hilo entre las 4 pasadas** (`setTimeout`) + WebGPU. Si aún pesa, bajar `MODEL_TIER` a `small`.
- **Tesseract deshabilitado** a pedido del cliente: todo lo resuelve PaddleOCR (para evaluarlo puro). Si Paddle no carga, muestra aviso de recargar (sin fallback).
- **Conclusión ARCA (definitiva, con fuentes):** NO existe forma gratis / sin registro / sin límites de traer los datos de una factura desde ARCA. Todas las APIs (TusFacturas, ARCA API, pyafipws, afipsdk…) requieren **certificado digital + Clave Fiscal**; los datos son privados (emisor/receptor). Probado: los endpoints públicos (`clavefiscal/qr/mobilePublicInfo.aspx`) devuelven "parámetro inválido o inexistente" y la guía oficial dice que la consulta pública "no funciona". **Único camino gratis/offline = leer el comprobante:** QR `?p=` (datos completos, ya implementado) y, a futuro, **código de barras lineal** (CUIT+tipo+PtoVta+CAE+vto — pendiente de implementar, opcional).
- **Validación**: +? tests → **222/222**. `escanear.js?v=13`, `paddleocr.js?v=4`.

## 30. v2.18 — Código de barras, visor pro, percepción, doc de requerimientos (2026-07-23)
PaddleOCR ya carga y lee bien (6/9 completos). Ajustes pedidos:
- **Lector de código de barras** (ZXing, opensource): lee el código lineal AFIP/ARCA (Interleaved 2of5) = CUIT+tipo+PtoVta+CAE+Vto. Se ejecuta tras el QR; **pisa CUIT/tipo/PtoVta** (machine-readable, más confiable que el OCR) y guarda el **CAE** en Observaciones. Fuente extra 100% offline, gratis, sin registro. Se combina con el OCR (que completa el resto).
- **Visor de comprobantes profesional**: GLightbox con caption (tipo·número / proveedor·total·fecha), fondo oscuro, bordes redondeados, sombra, botones en color de marca. Se quitó el cartel blanco genérico "Ver comprobante".
- **Percepción**: además de "Percepción", detecta "Perc. IIBB / Ing. Brutos" (sin confundir con el nº de IIBB del encabezado).
- **Velocidad**: lectura final acotada a 1800px (medium más rápido en fotos grandes, sin perder legibilidad) + cesión de hilo entre pasadas.
- **`md/REQUERIMIENTOS.md`**: checklist de reglas fijas del cliente (empezando por **vaciar la DB en cada prueba**) para no saltear nada.
- **Validación**: +8 tests → **230/230**. `escanear.js?v=14`, `app.css?v=11`. DB reseteada.

## 31. v2.19–2.20 — QR/barra nativo, visor propio, perf, reset de imágenes (2026-07-23)
- **Parser afinado end-to-end** (prueba mía sobre las 9 fotos reales con PP-OCRv6 medium): tiques con etiquetas mal leídas por el OCR ("P.U.NO", "NO T.", "T0001605", "1IQUE", "Cód.083") → afinados 3 patrones (tique/PV/número). Pasó de 4/9 a **6/9 tickets completos** (los 3 restantes son fotos rotas). Herramienta `scratchpad/e2e.py`.
- **Lector QR/barra "lo mejor de lo mejor"**: **BarcodeDetector NATIVO** del navegador (Chrome/Edge, acelerado por hardware, QR + barra en una pasada) como primario, con jsQR + ZXing de respaldo. `scanCodes()` unifica todo.
- **Visor de comprobantes propio** (`viewer.js` + CSS `.cv-*`): modal branded en la estética del sitio (Saira, #663399, tarjetas redondeadas, sombras), con encabezado (tipo·número / proveedor·total·fecha) e imagen/PDF. **Se eliminó GLightbox** (menos 1 CSS + 1 JS de CDN en TODAS las páginas).
- **Performance**: pdf.js (377KB) se carga **bajo demanda** (solo si suben PDF) → la página de escaneo es más liviana. jsQR/ZXing con `defer`. Server responde en 20–130ms.
- **Reset de testing** ahora borra también las **imágenes subidas** (`STORAGE/uploads/`, archivos aparte de la DB). Documentado en `md/REQUERIMIENTOS.md`.
- **Validación**: +14 tests → **239/239**. `escanear.js?v=16`, `app.css?v=12`, `viewer.js`.

## 32. v2.21 — anti-freeze (worker+fallback), tickets largos, zoom en visor (2026-07-23)
- **Freeze definitivo**: PaddleOCR corre en un **Web Worker** (`paddle-worker.js`) → inferencia fuera del hilo principal, la página **no se congela**. **Bug encontrado y arreglado (v2.22)**: la build `ppu-paddle-ocr/web` usa `document.createElement('canvas')`, que NO existe en un Web Worker → por eso el worker fallaba y caía al hilo principal. Fix: se **shimea `document.createElement` → `OffscreenCanvas`** antes de importar la librería (con import() dinámico para que el shim corra primero). Si aun así no arranca, **cae al hilo principal** (estable) → nunca se rompe. El resumen muestra `· fluido` si el worker está activo.
- **Tickets extremadamente largos**: en modo worker, la lectura final de recibos muy altos (alto/ancho > 2.4) se hace **por franjas** (`readTiled`), cada tira a buena resolución → carga completa del texto chico sin perder detalle, y off-thread (sin congelar).
- **Visor con zoom + pan**: scroll para zoom, arrastrar para mover, doble clic para restablecer, pellizco en táctil — con la misma estética branded.
- **Reset de testing**: ahora deja solo **3 comprobantes DEMO** (el resto los carga el cliente) + borra CRON + imágenes.
- **Validación**: +6 tests → **245/245**. `escanear.js?v=17`, `paddleocr.js?v=5`, `viewer.js?v=2`, `app.css?v=13`, `paddle-worker.js`.

## 33. v2.22 — worker arreglado + auditoría completa al CRON (2026-07-23)
- **Worker arreglado** (ver §32): shim de `document.createElement` → `OffscreenCanvas`. El resumen muestra `· fluido` si corre en el worker.
- **Auditoría completa al CRON**: los eventos del **cliente** ahora también se registran en `actividad` (antes solo se registraban los del servidor). Endpoint `api-log` (POST autenticado + CSRF) + `window.appLog(accion, detalle, tipo)` global (en `layout.php`). Se registra:
  - `ocr.motor` (sistema): qué motor/modo se usó (worker/hilo principal, GPU, tier).
  - `ocr.worker_fallback` / `ocr.no_disponible` (error): fallas del worker/carga.
  - `ocr.escaneo` (acción): cada escaneo con archivo, motor y campos detectados.
  - `ocr.qr` (acción): lecturas de QR de ARCA.
  - `js.error` (error): cualquier error JS no controlado (window.onerror + unhandledrejection).
  Así el CRON es un **log de auditoría general** de todo lo que pasa, cliente y servidor.
- **Validación**: +7 tests → **252/252**. `escanear.js?v=18`, `paddleocr.js?v=7`, `paddle-worker.js?v=2`.

## 34. v2.23 — worker descartado, orden por columnas en Comprobantes (2026-07-23)
- **Web Worker DESCARTADO** (concluyente vía CRON): `ppu-paddle-ocr/web` + onnxruntime-web de esm.sh NO cargan el backend WASM en un worker (`[unenv] module.require is not implemented`; probamos shim de document + `ort.env.wasm.wasmPaths` → mismo error). Es limitación de la librería+CDN en workers, no del cableado. Se volvió al **hilo principal con WebGPU** (la inferencia va a la GPU, casi no bloquea).
- **Anti-freeze en el hilo principal**: la **detección de orientación** ahora corre en **640px** (solo para elegir orientación) → las 4 pasadas son rápidas y ya no aparece "página no responde" en "orientación X de 4". La lectura final va a resolución alta. Tickets largos: lectura por franjas (con cesión de hilo). Si aún pesa, `MODEL_TIER='small'`.
- **Comprobantes ordenables**: default = **lo cargado recientemente arriba** (`c.id DESC`). Cada columna (Comprobante/Proveedor/Categoría/Neto/IVA/Total/Estado) es un **encabezado clicable con flechas** (▲/▼) que ordena asc/desc y preserva filtros, paginación y export (`comp_order()` con whitelist).
- **Validación**: tests ajustados (worker fuera) → **252/252**. `escanear.js?v=19`, `paddleocr.js?v=9`, `app.css?v=14`.

## 35. v2.24–2.26 — orden por columnas, guardado AJAX, lectura adaptativa (2026-07-23/25)
Rondas de prueba real del cliente (loop CRON → fix). Cambios:
- **Comprobantes ordenables**: default = lo cargado recientemente arriba (`c.id DESC`); cada columna con **flechas** asc/desc (`comp_order` + `$sortTh`), preservando filtros/paginación/export.
- **Bug de imagen al guardar (ARREGLADO)**: al recargar tras un error se perdía la imagen. Ahora **guardado por AJAX** (`X-Requested-With: fetch` → JSON) → si hay error, se muestra inline y **la imagen + datos se conservan**; se corrige y guarda sin re-escanear. Borrador OCR: el desajuste de total **no bloquea** (se ajusta en la revisión).
- **Modelo `small`** (medium tardaba 74-103s en el navegador del cliente: no usaba GPU real). small = ~10-13s, casi la misma precisión.
- **Lectura ADAPTATIVA de resolución** (`finalRead`): 1 lectura rápida a 1200px; si ya trae CUIT + señales → listo (tickets claros, ~4-5s). Si es denso/largo y faltan datos → re-lee por **franjas a máxima calidad** (`readTiled`, hasta 7 tiras a 1600px) → recibos de súper (2, 8) leen CUIT→razón→categoría+importes.
- **CRON diagnóstico**: `ocr.motor` loguea `aislado=si/no · cores=N`; `ocr.escaneo` loguea el tiempo en ms. Confirmado `aislado=si · cores=4`.
- **Techo de velocidad (honesto)**: se intentó Web Worker, WebGPU, `ort.env.wasm.proxy`, y multi-hilo (COOP/COEP + import-map + `?external` + `numThreads`) — **ninguno se activó**: el onnxruntime-web que sirve esm.sh corre single-thread WASM CPU en este stack. El piso es ~4-13s (según densidad del ticket). Para bajar de ahí haría falta **auto-hostear** un build de onnxruntime-web con GPU/threads (esfuerzo grande) — pendiente si el cliente lo pide.
- **Estado**: tickets claros (1,3,4,6,7,9) completos; densos (2,8) traen CUIT→razón→categoría+importes (faltan tipo/PV a mano); torn (5) parcial. Bug de imagen resuelto. +9 tests → **264/264**.

Pendiente: (opcional) auto-hostear onnxruntime-web para GPU/multi-hilo. El OCR y el padrón siguen entrenándose con el uso. **El CRON registra TODO (cliente + servidor).**

## 36. v2.28 — CUIT emisor vs comprador, lookup bidireccional (razón↔CUIT), franjas ≥8, estado del arte (2026-07-25)
Ronda a partir de la foto en alta del **ticket 8** (FALABELLA S A, CUIT 30-65572582-9): el parser agarraba el CUIT del **comprador** (INTELLIHELP) en vez del **emisor**, y ponía una razón equivocada.
- **Selección de CUIT (emisor vs comprador)**: se juntan TODOS los CUIT del texto con su posición. El del receptor aparece tras marcas de comprador (`A CONSUMIDOR FINAL`, `Cliente`, `Sr(es)`, `DNI`, `INTELLIHELP`). Si el **único** CUIT válido está en esa zona → **NO se toma** (es del comprador): se deja vacío para que lo complete el lookup por razón o el código de barras. Fin del bug "razón/CUIT del comprador".
- **Lookup BIDIRECCIONAL en el padrón (100% local, sin depender de OCR ni APIs)**: `api-proveedor` ahora acepta `cuit` **y/o** `razon`. CUIT→razón (limpia, pisa la del OCR) y **razón→CUIT** del emisor (`prov_by_razon`, difuso: token más largo + `levenshtein ≤1`, tolera "FFLABELLA"→"FALABELLA"). En el cliente, `enrichProveedor(cuit, razon)` reemplaza a `autocategorize` y consulta por las dos puntas. Es la idea del cliente: *"si el CUIT se lee bien sacás la razón; si la razón se lee bien sacás el CUIT"*.
- **Razón social más precisa**: se descartan líneas de **dirección** (Piso/Av/Calle/CP/localidad) y **etiqueta:valor / fechas**; se exige nombre real (≥5 letras, mayormente letras). Si no hay razón legible → **queda vacía** (mejor vacío que basura como "Piso 18").
- **Fragmentación agresiva** (pedido del cliente): recibos largos se leen en **mínimo 8 franjas (hasta 14) a 1800px** de máxima calidad (antes 3-7 a 1600px).
- **Padrón sembrado**: FALABELLA S A / 30655725829 (verificado) queda en el padrón para el lookup razón↔CUIT del ticket 8.
- **Techo real del ticket 8 (honesto)**: aun al 22% superior a **2000px**, PaddleOCR lee el logo de FALABELLA como `SFEAERG 54` — **"FALABELLA" es un LOGO (imagen), no texto**, y el CUIT del emisor sale mutilado (`3065572!029`). De ESA foto el OCR no puede sacar ni la razón ni el CUIT del emisor; la vía confiable es el **QR/código de barras** (ya implementado) o carga manual. Ahora al menos **no pone datos equivocados**.
- **Estado del arte investigado** (universidades, foros, GitHub, productos comerciales AR, ARCA):
  - **Consenso**: la fuente de verdad es el **QR de ARCA** (URL `?p=<base64 JSON>` con CUIT/PtoVta/tipo/nro/importe/CAE/fecha — NO trae razón social), y el **código de barras I2of5** (RG 1702: CUIT+tipo+PtoVta+CAE+vto). El OCR es el eslabón débil (85-92% a nivel campo) → siempre con **revisión humana**. Xubio, Contabilium, GestorMax hacen exactamente esto: QR para cabecera + IA/OCR para el detalle (y cobran por la IA).
  - **Constatación oficial (WSCDC)**: devuelve razón + importe + validez, pero **requiere certificado digital (WSAA)** → no es gratis/sin registro.
  - **CUIT→razón gratis**: el endpoint REST `soa.afip.gob.ar/sr-padron/v2/persona/{CUIT}` que la comunidad usaba **hoy responde 404** (baja/migración ARCA + geo-bloqueo a IP argentina); **cuitonline** está detrás de **Cloudflare** (bloquea scraping server-side). No hay API pública gratis y sin registro confiable **hoy**. Existe un dataset volcado (cuitonline scraping, ~26M) como opción offline si se quisiera precargar.
  - **Modelos SOTA** (por si se quiere subir el techo): LayoutLMv3 (CORD F1 ~97), Donut (OCR-free, F1 ~91.6), o LLM multimodal (>95% en campos críticos) — todos más pesados que PaddleOCR en el navegador.
- **Validación**: +9 tests → **277/277**. `escanear.js?v=27`. Deploy + reset (3 DEMO, 11 categorías, CRON vacío, FALABELLA sembrado).

**Recomendación para "datos reales sin depender del OCR"**: priorizar la lectura del **QR/código de barras** (ya está) + el **padrón bidireccional** (ya está). Para cobertura nacional CUIT→razón sin OCR, la única vía sólida a futuro sería un **certificado digital de ARCA** (constatación/padrón oficiales) — requiere registro pero es de la propia INTELLIHELP; queda como opción si el cliente la habilita.

## 37. v2.29 — padrón nacional offline de empresas (1,13M) sin certificado (2026-07-25)
El cliente pidió "descargar todos los CUIT/razón, aunque sea pesado, y que quede cargado para siempre". Se implementó **sin depender de ninguna API ni certificado**:
- **Fuente**: dump comunitario de cuitonline ([jboirazian/CUIT_DATABASE_SCRAPING](https://github.com/jboirazian/CUIT_DATABASE_SCRAPING), oct-2021, ~27M registros, formato `idx/CUIT/nombre`). Se descargó 1 vez (1.1GB).
- **Alcance = SOLO EMPRESAS** (decisión del cliente): se filtró a personas jurídicas (CUIT 30/33/34) → **1.129.812 empresas** (48MB). Se excluyeron los ~26M de personas físicas por privacidad (Ley 25.326 de datos personales) y por peso/rendimiento en hosting compartido. Los monotributistas individuales se siguen aprendiendo solos con el uso (tabla `proveedores`).
- **Tabla `padron_nacional`** (`cuit` PK + `FULLTEXT(razon_social)`), separada de `proveedores`. **Sólo lectura, se carga 1 vez y PERSISTE entre resets de testing.** Import por lotes vía PHP/PDO (`import_padron.php`, sin `LOAD DATA` que suele estar deshabilitado). El TSV se borró del webroot tras importar (no queda público).
- **Cadena de resolución en `api-proveedor`** (bidireccional, 100% local): `proveedores` por CUIT → `proveedores` por razón → `padron_nacional` por CUIT → `padron_nacional` por razón. Devuelve `fuente` (`padron`/`nacional`) y el cliente lo avisa/loguea (`ocr.padron_nacional`).
  - **CUIT→razón (lo principal)**: exacto, instantáneo. Verificado: `30655725829`→FALABELLA S A, `30546689979`→YPF SOCIEDAD ANONIMA, `30500000127`→SEGUROS SURA S.A.
  - **razón→CUIT (bonus)**: FULLTEXT + verificación **conservadora** — sólo devuelve CUIT si el núcleo del nombre (sin sufijos SA/SRL/"SOCIEDAD ANONIMA") coincide EXACTO y es ÚNICO. La razón social NO es única (homónimos, franquicias) → **nunca adivina**: YPF/TELECOM/AEROLÍNEAS/SEGUROS SURA resuelven bien; FALABELLA (varias entidades) → abstiene (mejor vacío que un CUIT equivocado).
- **"Lo mejor de lo mejor" aplicado (sin certificado)**: (1) QR/código de barras como fuente de verdad (ya estaba, es el consenso del estado del arte); (2) padrón nacional offline para CUIT↔razón (lo que hacen Xubio/Contabilium/GestorMax como capa de enriquecimiento, acá gratis); (3) revisión humana (ya estaba, coincide con la tesis de La Laguna y los productos comerciales). Fuera de alcance por peso/costo/cert: LayoutLMv3/Donut (modelos pesados), LLM multimodal (API paga), constatación/padrón oficial de ARCA (certificado).
- **Validación**: +5 tests → **282/282**. `escanear.js?v=28`. `schema.sql` con la tabla nueva. Deploy + reset (3 DEMO, 11 categorías, CRON vacío, **padrón nacional = 1.129.812 empresas** persistente).

## 38. v2.30 — motor de resolución con VALIDACIÓN CRUZADA (inequívoco, ~99%) (2026-07-25)
Pedido del cliente: que la DB nacional se USE de verdad y matchee **inequívoco** (que CUIT y razón coincidan) para funcionar el 99% de las veces sin errores. Se construyó `resolver_proveedor($cuit, $razon, $cuitOcr)` — un motor que cruza lo que leyó el OCR contra las dos tablas y **nunca asigna un dato dudoso**:
- **Orden**: (1) CUIT exacto en `proveedores` (con categoría) → (2) CUIT exacto en `padron_nacional` **cruzado con la razón** → (3) razón en `proveedores` (exige el token más distintivo) → (4) razón en `padron_nacional` (homónimos) → (5) recuperación **difusa** del CUIT.
- **Coincidencia INEQUÍVOCA = confianza `alta`**: CUIT y razón apuntan al mismo registro. El cliente entonces **corrige** el CUIT que puso el OCR (recupera errores de lectura).
- **Desempate de homónimos por CUIT** (`_prov_pick_por_cuit`, levenshtein ≤2): "FALABELLA" hay varias, pero el CUIT leído elige la correcta. Si el CUIT no está cerca de ninguna (p. ej. es el del **comprador**) → **no adivina** (devuelve nada).
- **Recuperación difusa del CUIT** (`padron_nac_fuzzy_cuit`): genera variantes a 1 edición (sustitución/inserción/borrado, 10-12 díg) y consulta las que EXISTEN en el padrón en 1 query indexada → recupera CUIT mal leídos por OCR, y si además la razón coincide → `alta`.
- **Núcleo de razón consistente** (`prov_razon_core`): quita acentos, puntuación, sufijos societarios ("SOCIEDAD ANONIMA", SA, SRL) y **letras sueltas** → "SRL" (OCR junto) y "S R L" (dataset espaciado) normalizan IGUAL (clave para matchear SRL).
- **Cliente**: `parseAndFill` manda un hint crudo `cuit_ocr` (aunque falle el DV / tenga 10-12 díg) para la recuperación difusa; `applyProveedor` corrige el CUIT en confianza `alta` y avisa/loguea la fuente + confianza (`ocr.padron_nacional [alta|media]`).
- **Probado en el servidor** (todos OK): CUIT+razón de acuerdo→alta · razón+CUIT con 1 error→corrige→alta · razón+CUIT del comprador→rechaza · CUIT difuso mal leído→recupera→alta · razón única→media · SRL junto vs espaciado→resuelve · basura→nada.
- **Validación**: +8 tests → **289/289**. `escanear.js?v=29`. Deploy + reset. Así el OCR y la DB nacional **se validan entre sí** = datos reales sin errores.

## 39. v2.30–2.32 — honestidad del motor, anti-basura, orientación por IA (2026-07-25)
Ronda a partir de capturas reales del cliente (motor decía "GPU" sin serlo; razón social basura; valores viejos entre escaneos; orientación floja). Investigación a fondo (6 subagentes: académico, foros, GitHub, productos, orientación, preprocesado) → aplicado lo evidence-based.
- **Etiqueta de motor HONESTA (sin hardcodear)**: antes decía "GPU" si el navegador *soportaba* WebGPU (disponibilidad, no uso). Ahora lee `ort.env.webgpu.device` — onnxruntime-web sólo lo puebla si una sesión WebGPU se creó **de verdad**; si no, reporta `CPU (WASM · N hilos)`. Erra del lado seguro (no sobre-afirma GPU). `paddleocr.js` expone `api.backend` real; el estado y el CRON lo muestran.
- **Campos limpios entre escaneos**: `clearFields()` vacía los campos que llena el OCR al iniciar cada escaneo → no quedan datos del ticket anterior (bug de las capturas: CUIT/PV repetidos).
- **Razón social sin basura** (principio del cliente "si no lo distingue exacto, no se pone"): descarta líneas con dígitos, símbolos iniciales, o palabras de ítems (IMPORTE/NETO/PRECIO/…). Filtra "[NBI 1 IMPORTE" y ".SHARTIN4 SAATT" → queda vacío para carga manual.
- **Importes coherentes**: si Neto+IVA+Percep no da el Total (tolerancia 2%), NO completa Neto/IVA/Tasa (evita el caso Neto=IVA de la captura). Mejor vacío que incoherente.
- **ORIENTACIÓN por IA (lo mejor de lo mejor, sin certificado)**: se integró el clasificador **PP-LCNet doc_ori** (ONNX, 6.7 MB, 4 clases 0/90/180/270, del mismo autor que ppu-paddle-ocr) corriendo en **onnxruntime-web**. **Una inferencia (~ms) endereza la foto** antes del OCR, en vez de probar las 4 rotaciones (lento y ~90%). Validado **100% en los 9 tickets reales** en Node (rot0→0…rot270→270; ticket 4 de costado detectado). Preprocesado 256+crop224 + normalización ImageNet; corrección = `(360-pred)%360`. Alojado en `assets/models/doc_ori.onnx`; si el modelo no carga, cae al bruteforce anterior.
- **Tickets largos**: fragmentación a **mínimo 10 franjas (hasta 16)** con **solape 22%** (SAHI: 20-25% óptimo) a 1800px → el texto queda ≥25-30px (causa raíz: el detector reescala el lado largo a ≤960px).
- **Evidence-based (NO se aplicó)**: binarización dura (Otsu/Sauvola) — la evidencia (Tesseract #3083, EasyOCR, PaddleOCR DBNet) muestra que **empeora** el OCR deep; se alimenta color/gris. Pendientes de alto valor documentados: **jscanify** (recorte + corrección de perspectiva, mayor logro para fotos), `UVDoc.onnx` (dewarping) y `PP-DocLayoutV2.onnx` (layout/regiones) — mismos autores, quedan como próximo paso si el cliente los pide.
- **Validación**: +11 tests → **300/300**. `paddleocr.js?v=15`, `escanear.js?v=31`. Deploy + reset.

## 40. v2.33 — dewarping adaptativo (UVDoc) para tickets difíciles (2026-07-25)
Se aplicó el último pendiente de alto valor: **rectificación geométrica del papel (dewarping)** con el modelo **UVDoc** (ONNX, 31.6 MB) vía la librería `ppu-doc-correction` (mismo autor), en onnxruntime-web.
- **Validado en Node** antes de integrar (OCR original vs dewarpeado): ticket 8 score 10→**14**, ticket 2 0→**2** (ayuda en densos), ticket 9 neutral (12→12) → **sin regresión**.
- **ADAPTATIVO + PEREZOSO**: el modelo de 31.6 MB NO se baja ni corre en tickets claros. La lectura final es en cascada: (1) lectura rápida → si tiene CUIT + señales, listo (rápido); (2) si no alcanza → **dewarp** (baja UVDoc la 1ª vez) y re-lee; (3) si sigue faltando y es largo → franjas. Así los tickets fáciles quedan rápidos y sólo los difíciles pagan el costo del dewarp.
- **Self-hosted**: `assets/models/UVDoc.onnx` (no depende de GitHub). `api.unwarp(canvas)` en paddleocr.js hace `import('ppu-doc-correction/web?external=onnxruntime-web')` (comparte el mismo ort), corre `TextUnwarpService`, y convierte el PNG resultante a canvas. Si el modelo/lib no cargan → fallback total (la app sigue igual, sin dewarp) → **cero riesgo de regresión**.
- **Pendiente documentado (no aplicado)**: `PP-DocLayoutV2.onnx` (detección de layout/regiones) — para tickets fiscales (texto denso) el beneficio es marginal frente al peso/complejidad; queda disponible si se necesita clasificar gráficas/tablas.
- **Validación**: +6 tests → **304/304**. `paddleocr.js?v=16`, `escanear.js?v=32`. Modelos en `assets/models/`: `doc_ori.onnx` (6.7 MB, orientación) + `UVDoc.onnx` (31.6 MB, dewarping). Deploy + reset.

**Pipeline OCR final (100% gratis, sin certificado, en el navegador)**: código/QR de ARCA (fuente de verdad) → **orientación por IA** (doc_ori, 1 inferencia) → lectura adaptativa → **dewarping** si hace falta (UVDoc) → franjas si es largo → parser AR → **validación cruzada contra el padrón nacional** (1.13M empresas) → revisión humana. Cada capa con fallback.

## 41. v2.33–2.34 — fix del resumen + verdad medida sobre dewarp y layout (2026-07-25)
Ronda con **acceso directo a SSH + DB del cliente** (inspección del CRON/`actividad` en vivo, sin pasar texto).
- **BUG del resumen (arreglado)**: el resumen decía "Completá a mano: Categoría" AUNQUE la categoría se cargaba (keyword/padrón) — y se calculaba ANTES del enriquecimiento async. Reescrito `refreshSummary()` que lee el **estado REAL de los campos del formulario** y se **re-ejecuta tras cada paso** (categoría, padrón). Notas via `summaryNotes` (no `innerHTML +=`, que se pisaba). Ahora el resumen nunca miente.
- **Motor honesto confirmado en vivo**: el CRON muestra `PaddleOCR small · CPU (WASM · 4 hilos)` (era el objetivo). Orientación por IA funcionando: `ocr.orientacion: imagen enderezada 270°` en el ticket 4.
- **DEWARPING (UVDoc) — RETIRADO tras medir**: validado en Node (ticket 8 +4), pero el CRON probó que **disparó 0 veces en el navegador** en 9 escaneos → la cadena esm.sh/onnxruntime-web (ppu-doc-correction espera ort 1.24.2 vs 1.23.2 del import map, + ppu-ocv) lo bloquea en el browser, igual que pasó con el Web Worker. No se envía peso muerto: se quitó el código + el modelo de 31.6 MB del servidor. (El ticket-2 de 40s eran las **franjas**, no el dewarp.)
- **PP-DocLayout — EVALUADO y DESCARTADO (con números)**: experimento en Node sobre los 9 tickets (orientados): baseline vs recorte a regiones de texto. Resultado: **mejora +1 en sólo 2 de 9 tickets (5 y 8, los difíciles), 0 empeora, pero ~duplica el tiempo** (analyze ~2s + re-OCR ~1.5s). Y el modelo **PP-DocLayoutV2 pesa 213 MB**. Conclusión: 213 MB en el navegador por +1 en 2/9 no vale la pena → no se integra.
- **Regla aplicada** (pedido del cliente "probar/comparar en el sitio antes de decidir"): cada mejora candidata se **mide** (tiempo + % de mejora real) antes de enviarla; sólo se envía lo que mejora de verdad sin romper velocidad/confiabilidad. Lo que quedó: orientación por IA (real, gratis, rápida) + padrón nacional + validación cruzada + parser afinado + franjas para largos.
- **Validación**: **304/304** tests. `paddleocr.js?v=17`, `escanear.js?v=34`. Modelo activo: `doc_ori.onnx` (6.7 MB, orientación). Deploy + reset.

## 42. v2.35–2.36 — repo profesional en GitHub + autoría/licencia (2026-07-25)
Se publicó el proyecto en **GitHub** (cuenta personal `bsierra4262`, repo **privado** `rendicion-tickets`) con estándar profesional, sin ninguna mención a IA:
- **Documentación completa**: `README.md` (banner SVG, badges, diagrama mermaid del pipeline, capturas), `LICENSE`, `CHANGELOG.md`, `CONTRIBUTING.md` (Conventional Commits + "cada bug agrega un test"), `SECURITY.md`, `.editorconfig`, y CI (`.github/workflows/ci.yml`: lint + tests en PHP 8.2 y 8.3, en verde).
- **Sin secretos ni datos**: `.gitignore` sube sólo `app/` (código) + docs; excluye `config.php` (password DB + client_secret Google — vive fuera del web root) y toda carpeta de datos reales (fotos, Excel, exportaciones, PDFs). Sólo se versiona `config.example.php` con placeholders. Verificado: cero secretos/datos filtrados.
- **Autoría y licencia (decisión del cliente, con base legal)**: investigación exhaustiva de la Ley 11.723 art. 4 inc. d) (incorporado por Ley 25.036). Conclusión honesta: el empleador retiene los derechos **patrimoniales** SÓLO si el empleado fue **contratado para programar**; como el cargo de Brian **no es programador** (responsable de servicios → apunta a SysAdmin/Automatización IT), art. 4 inc. d) **no aplica** y — reforzado por LCT art. 82 (las creaciones personales del trabajador son suyas, aun usando instrumentos ajenos) — el **autor conserva sus derechos**. El derecho **moral** (autoría) es siempre suyo e irrenunciable (art. 52 + Berna 6 bis). `LICENSE` quedó **neutro en lo patrimonial** ("se rige por la ley y los acuerdos escritos") + **autoría 100% de Brian Sierra** reconocida. README con secciones "Licencia" y "Autor".

## 43. v2.36 — auto-cierre de sesión por inactividad y máximo absoluto (2026-07-25)
Requisito de seguridad: cerrar la sesión sola por inactividad. Investigado (OWASP) → **10 min de inactividad + 8 h de sesión máxima**, con **enforcement REAL en el servidor** (el cliente es sólo UX).
- **Servidor (`bootstrap.php`)**: en cada request, si `now - last_activity > 10 min` o `now - login_time > 8 h`, destruye la sesión. A endpoints JSON (fetch) responde **HTTP 440**; a la navegación normal la **redirige al login** con aviso (`?expiro=inactividad|expiro`). `last_activity` se sella en cada request; `login_time`/`last_activity` se fijan en el login (contraseña y Google). Cada cierre queda en el **CRON** (`sesion.cierre_auto`).
- **Cliente (`idle.js`)**: detecta actividad real (mouse/teclado/scroll/touch), manda un **keepalive** (throttle 60 s, endpoint `api-sesion`) para no vencer mientras se trabaja sin recargar, muestra un **aviso branded con cuenta regresiva** ("¿Seguís ahí?" · Seguir conectado / Salir) y redirige al vencer. También corta al volver de una pestaña en segundo plano y al llegar al máximo absoluto.
- **Allowlist JSON**: `api-keys` (página HTML admin) se **redirige** (no 440); sólo los endpoints fetch reales devuelven 440.
- **Probado E2E en producción** (inyectando sesiones de prueba, borradas al terminar): idle vencido→302 login?expiro=inactividad · idle vencido→api-sesion 440 · sesión fresca→api-sesion 200 · sesión fresca→dashboard 200 (nav normal intacta) · absoluto vencido→302 login?expiro=expiro. CRON registró ambos tipos.
- **Validación**: +12 tests → **334**. Deploy live verificado (api-sesion 440, avisos, home 302).

## 44. v2.37 — respaldo automático de imágenes en Google Drive (2026-07-25)
Las imágenes de comprobantes se respaldan en **Google Drive** (cuenta de 24 TB), con la copia local como fallback.
- **OAuth offline**: página admin **Drive** (`/drive`) con "Conectar Google Drive" → consentimiento con `access_type=offline` + `prompt=consent` (obtiene refresh token) y scope `.../auth/drive`. **Reutiliza el mismo redirect** que el login (`google-callback`, distinguido por `state="drive:…"`), así **no hay que registrar otra URI** en Google Cloud. El refresh token se guarda en `app_settings`; el access token se refresca y cachea solo.
- **Estructura y nombres**: `Carpeta raíz / AÑO / "AÑO MM - Mes" / archivo`. Nombre legible y ordenable por fecha: `2026-07-19 · Fact A · 00585-00002182 · FALABELLA SA.jpg` (sanitizado, sin barras). Meses en español. Carpetas creadas on-demand (buscar-o-crear).
- **Guardado con FALLBACK total**: al guardar un comprobante, primero se guarda la imagen **local** (como siempre); luego, si Drive está conectado, se sube y se guarda el **link** en `comprobantes.drive_link` / `drive_file_id`. Todo el bloque va en try/catch → **el guardado nunca se rompe** si Drive falla o no está conectado. Cada subida/fallo queda en el CRON (`drive.subida` / `drive.fallo`).
- **Admin**: estado de conexión, quién/cuándo conectó, métrica de respaldados, botón "Probar conexión", carpeta raíz configurable (acepta URL o ID), desconectar (revoca el token). En el detalle del comprobante aparece "Ver en Google Drive".
- **Probado en producción**: `/drive` sin login→302; con admin→render OK ("No conectado" + botón); `/drive-connect`→302 a Google con scope drive + offline + state drive:. Falta **un paso manual del cliente**: entrar a Drive (admin) y autorizar con la cuenta dueña de la carpeta.
- **Validación**: +14 tests → **343/343**. Columnas nuevas en `comprobantes`. Módulo `app/google_drive.php`.

## 45. v2.37–2.38 — portabilidad total (Docker + esquema + white-label) y README profesional (2026-07-25)
Pedido del cliente: que el repo se pueda **clonar, rebrandear y desplegar en otra empresa** sin implementar nada a mano; y que la documentación de GitHub tenga **lenguaje técnico/profesional** (sin frases casuales tipo "tu Excel", "sacás", "pegá").
- **Esquema versionado y completo** (`app/db/schema.sql`): exportado de la base real (8 tablas, con las columnas `drive_link`/`drive_file_id` y el `FULLTEXT` del padrón), normalizado a `CREATE TABLE IF NOT EXISTS` (idempotente). El schema previo tenía solo 6 tablas (faltaban `actividad` y `api_tokens`).
- **Configuración por entorno** (`app/web/app/config_env.php`): `bootstrap` resuelve la config en orden — `TICKETS_CONFIG` → rutas conocidas (`app/server-config/config.php`) → **variables de entorno** (Docker/12-factor). Un mismo código sirve para hosting (archivo) y contenedor (env), sin secretos en el repo.
- **Docker listo** (`Dockerfile` PHP 8.2-apache + `docker-compose.yml` con MariaDB): `docker/entrypoint.sh` espera la base, prepara storage e **importa el esquema en el primer arranque** (`docker/dbinit.php`, por PDO, sin cliente mysql); `docker/000-default.conf` fija el DocumentRoot en `app/web`, arma el front controller y **protege el código** (`app/` denegado). `cp .env.example .env && docker compose up -d --build` deja la app corriendo.
- **Instalador/diagnóstico** (`bin/setup.php`): detecta PHP + extensiones, localiza la config, prueba la base, importa el esquema si falta y valida el almacenamiento. Para hosting compartido: `php bin/setup.php`.
- **White-label por configuración** (sin tocar código): `app.name`, `app.org`, `app.brand.{primary,accent,logo}` (o `APP_BRAND_*`). El layout inyecta un `:root{}` que sobrescribe las variables CSS (`--primary`, `--accent`) y usa logo/organización configurables. Documentado en `BRANDING.md`. Toda la hoja de estilos ya estaba basada en variables → recolorar es trivial.
- **README/docs profesionales**: README reescrito en lenguaje técnico neutro (sin voseo casual), con arquitectura, stack, Docker quick-start y enlaces a `INSTALL.md`/`BRANDING.md`. `CONTRIBUTING.md` y `SECURITY.md` neutralizados. `.gitignore` versiona lo nuevo (Docker, `bin/`, `.env.example`) y sigue excluyendo `config.php` y `.env` reales (verificado: nada de secretos se sube).
- **Validación**: +21 tests → **364/364**. Deploy de los archivos que cambian comportamiento (bootstrap/config_env/layout) a producción, sin regresión (login 200, dashboard autenticado 200 con la marca ITH intacta, api-sesion 440).

## 46. v2.39 — portal único: el login conecta Drive (sin botón aparte) (2026-07-25)
Pedido del cliente: que no haya "dos portales" (login por un lado, conectar Drive por otro). La gente de administración usa la app, así que el **propio login** debe pedir el permiso de Drive.
- **Login condicional**: `google_auth_url()` agrega el scope de Drive + `access_type=offline` + `prompt=consent` **solo mientras Drive no esté conectado**. Una vez conectado, el login vuelve a ser simple (`online`, sin Drive) → verificado en producción: con Drive ya conectado el login sale limpio.
- **Captura automática**: el callback del login, si Google devuelve `refresh_token` y Drive aún no está conectado, guarda la conexión (mismo mecanismo que el botón manual). Así el primer login de administración deja Drive conectado en un solo paso; los siguientes entran normal.
- **Respaldo probado end-to-end en producción**: con el token real ya conectado (`bsierra@intellihelp.tech`), se validó `drive_probar()` (carpeta 2026 accesible) + una **subida real** a la carpeta de 24 TB (link generado) + borrado del archivo de prueba. El pipeline de respaldo funciona.
- La página **Drive** sigue disponible para estado/desconectar/reconectar manual. **367/367** tests.
