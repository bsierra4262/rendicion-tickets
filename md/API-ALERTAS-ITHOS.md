# API de Alertas — integración con ITHOS (Jarvis)

Guía para que **ITHOS** consuma las alertas de la app *Rendición de comprobantes* (INTELLIHELP)
y las reenvíe al **grupo de WhatsApp** de la empresa. La app expone la API; ITHOS la consume
(polling) o, alternativamente, la app le hace *push* a un endpoint de ITHOS.

- **Base URL (producción):** `https://intellihelp.tech/comprobantes`
- **Autenticación:** API-key de la sección **API** de la app (menú *API*). Se envía en:
  - `X-API-Key: <key>` — recomendado, o
  - `Authorization: Bearer <key>`, o
  - `?api_key=<key>` (solo para pruebas rápidas).
- **Alcance / permisos:** la API-key es de **solo lectura** — sirve para *consultar* alertas y
  datos (comprobantes/pendientes). **NO** es un usuario admin/root ni permite acciones de
  administración: la API no expone mutaciones. ITHOS solo lee y reenvía.
- **Modo TEST:** mientras la app esté en *testing*, los textos vienen con prefijo `[TEST] ` y
  la respuesta trae `"entorno":"testing"` y `"test":true`. En producción no llevan prefijo.

---

## Opción A — ITHOS consume nuestra API (recomendado: pull/polling)

### `GET /api/alertas?desde=<id>`

Devuelve las alertas **abiertas** (no resueltas) con `id` mayor a `desde`. ITHOS guarda el
último `id` procesado y vuelve a pedir con ese valor (polling incremental, ej. cada 2–5 min).

**Request**
```
GET https://intellihelp.tech/comprobantes/api/alertas?desde=0
X-API-Key: TU_API_KEY
```

**Response 200**
```json
{
  "ok": true,
  "entorno": "testing",
  "test": true,
  "alertas": [
    {
      "id": 12,
      "tipo": "gasto_pago_vencido",
      "severidad": "danger",
      "titulo": "[TEST] Pago VENCIDO · EDENOR (2026-06)",
      "mensaje": "[TEST] El pago de «EDENOR» del período 2026-06 · $12.345,00 venció hace 3 días (venc. 10/06/2026) y sigue pendiente.",
      "url": "https://intellihelp.tech/comprobantes/gasto-form?id=45",
      "creado": "2026-07-29 14:20:11",
      "canal_sugerido": "whatsapp"
    }
  ]
}
```

**Flujo sugerido en ITHOS**
1. Mantener `ultimo_id` (persistido por inquilino).
2. Cada X minutos: `GET /api/alertas?desde=<ultimo_id>`.
3. Por cada alerta nueva, postear al grupo de WhatsApp (formato sugerido abajo) y actualizar
   `ultimo_id = max(id)`.
4. Respetar `severidad` para el tono/emoji: `danger` 🔴, `warn` 🟡, `info` 🔵.

**Formato sugerido del mensaje de WhatsApp**
```
🔴 {titulo}
{mensaje}
👉 {url}
```

**Campos de cada alerta**

| Campo | Tipo | Detalle |
|---|---|---|
| `id` | int | Identificador incremental. Usar para `desde`. |
| `tipo` | string | `gasto_pago_vencido` · `gasto_por_vencer` · `comprobante_pendiente` (extensible). |
| `severidad` | string | `info` · `warn` · `danger`. |
| `titulo` | string | Título corto (con `[TEST] ` si testing). |
| `mensaje` | string | Texto completo (con `[TEST] ` si testing). |
| `url` | string | Link directo al ítem en la app. |
| `creado` | datetime | `YYYY-MM-DD HH:MM:SS`. |
| `canal_sugerido` | string | `whatsapp`. |

---

## Opción B — la app le hace *push* a ITHOS

Si ITHOS prefiere **recibir** (en vez de pollear), exponer un endpoint y cargar su URL + API-key
en la app (menú *Alertas → Canales externos → Endpoint/API-key de ITHOS*). La app hará:

```
POST <endpoint_de_ITHOS>
Authorization: Bearer <api_key_de_ITHOS>
Content-Type: application/json

{
  "origen": "rendicion-comprobantes",
  "tipo": "gasto_pago_vencido",
  "entorno": "testing",
  "test": true,
  "titulo": "[TEST] Pago VENCIDO · EDENOR (2026-06)",
  "mensaje": "[TEST] El pago de «EDENOR» … venció hace 3 días …",
  "severidad": "danger",
  "url": "https://intellihelp.tech/comprobantes/gasto-form?id=45",
  "canal_sugerido": "whatsapp"
}
```

El *push* se dispara **una sola vez** al crear cada alerta (no reenvía en cada evaluación). Se
espera `2xx` como confirmación. WhatsApp lo resuelve ITHOS (grupo del cliente).

---

## Notas de implementación
- **Idempotencia:** cada alerta tiene un `id` estable; ITHOS no debe duplicar (usar `desde`/`ultimo_id`).
- **Multi-inquilino:** la app es de un solo cliente (INTELLIHELP); usar la API-key de este inquilino.
- **Paso a producción:** cuando la app sale de *testing*, `entorno` pasa a `"produccion"` y los
  textos dejan de llevar `[TEST] ` — no requiere cambios en ITHOS.
- **Contacto de campos nuevos:** el catálogo de `tipo` puede crecer; tratar tipos desconocidos de
  forma genérica (usar `titulo`/`mensaje`/`severidad`).
