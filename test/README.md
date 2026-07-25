# Tests — INTELLIHELP Rendición de Tickets

Baterías de pruebas de regresión. **Requerimiento:** cada bug que se encuentra suma un caso acá para que no vuelva a aparecer.

## `run_tests.php` — pruebas unitarias
Validan la lógica pura (CUIT módulo‑11, parseo de importes AR/US, formato de moneda, período, URLs de Google OAuth).

```bash
# Local
php test/run_tests.php

# Contra el código desplegado (en el servidor)
TICKETS_SRC=/home/u566451014/domains/intellihelp.tech/public_html/tickets/app \
  php /home/u566451014/tickets_build/test/run_tests.php
```
Sale con código 0 si todo pasa, 1 si algo falla.

## `smoke.sh` — pruebas HTTP en producción
Verifican que el sitio responde bien (login 200, rutas protegidas 302, assets 200, código bloqueado 403, botón de Google presente).

```bash
bash test/smoke.sh
```

## Bugs cubiertos
- **BUG #1** — `google_redirect_uri()` devolvía `http://` (Google rechaza por `redirect_uri_mismatch`). Ahora se fuerza `https://`.
- **BUG #2** — `parse_importe()` malinterpretaba el formato US `9,669.42` (así vienen importes del Excel). Ahora detecta AR (`1.234,56`) y US (`1,234.56`).
