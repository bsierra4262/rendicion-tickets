# Personalización de marca (white-label)

La interfaz se adapta a otra organización mediante configuración, sin modificar el
código. Toda la hoja de estilos se apoya en variables CSS (`--primary`, `--accent`,
`--ink`, …), de modo que redefinir unas pocas alcanza para recolorar la aplicación.

## Parámetros

Por archivo (`config.php`, bloque `app`) o por variable de entorno:

| Config (`app.*`) | Variable de entorno | Descripción |
| --- | --- | --- |
| `name` | `APP_NAME` | Nombre de la aplicación (título, encabezados). |
| `org` | `APP_ORG` | Nombre de la organización (pie de página, alt del logo). |
| `brand.primary` | `APP_BRAND_PRIMARY` | Color primario (ej. `#663399`). |
| `brand.accent` | `APP_BRAND_ACCENT` | Color de acento (ej. `#9CC930`). |
| `brand.logo` | `APP_BRAND_LOGO` | Ruta del logo (ej. `assets/img/mi-logo.png`). |

Cuando `brand.primary` o `brand.accent` tienen valor, el layout inyecta un bloque
`:root { … }` que **sobrescribe** las variables de la hoja de estilos. El resto del
diseño (botones, enlaces, foco, badges) se recolora en consecuencia.

## Logo

Colocá el archivo del logo en `app/web/assets/img/` y referencialo con
`brand.logo` (ruta relativa al web root). Si no se define, se usa el logo por
defecto. Para el favicon, reemplazá `app/web/assets/img/favicon.png`.

## Ejemplo (variables de entorno)

```env
APP_NAME=Gestión de Comprobantes
APP_ORG=ACME S.A.
APP_BRAND_PRIMARY=#0A5CED
APP_BRAND_ACCENT=#00B894
APP_BRAND_LOGO=assets/img/acme-logo.png
```

## Ajustes finos

Para un rediseño más profundo (tipografía, radios, sombras), las variables viven en
`:root` dentro de `app/web/assets/css/app.css`. Redefinir esas mismas variables —vía
el bloque inyectado o un archivo CSS propio— mantiene la coherencia visual sin tocar
las reglas de cada componente.
