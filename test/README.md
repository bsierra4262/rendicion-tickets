# Tests — INTELLIHELP Rendición de Tickets

Baterías de pruebas de regresión. **Requerimiento:** cada bug que se encuentra suma un caso acá para que no vuelva a aparecer.

## `run_tests.php` — pruebas unitarias
Validan la lógica pura (CUIT módulo‑11, parseo de importes AR/US, formato de moneda, período, URLs de Google OAuth) y el ingreso con ITHub (firma RS256 con una clave generada en el momento, emisor, destinatario, vencimiento, nonce, PKCE, aviso de cierre y salida).

```bash
# Local
php test/run_tests.php

# Contra el código desplegado (en el servidor)
TICKETS_SRC=/home/u566451014/domains/intellihelp.tech/public_html/comprobantes/app \
  php /home/u566451014/tickets_build/test/run_tests.php
```
Sale con código 0 si todo pasa, 1 si algo falla.

## `smoke.sh` — pruebas HTTP en producción
Verifican que el sitio responde bien (rutas protegidas 302, assets 200, código bloqueado 403). Con ITHub: `/login` → `ithub-login` → `/oauth/authorize` con PKCE, pantalla `?manual=1` con «Ingresar con ITHub» y aviso de cierre con token inválido → 400. Sin ITHub: login 200 con el botón de Google.

```bash
bash test/smoke.sh
```

## Bugs cubiertos
- **BUG #1** — `google_redirect_uri()` devolvía `http://` (Google rechaza por `redirect_uri_mismatch`). Ahora se fuerza `https://`.
- **BUG #2** — `parse_importe()` malinterpretaba el formato US `9,669.42` (así vienen importes del Excel). Ahora detecta AR (`1.234,56`) y US (`1,234.56`).
