# Instalación y despliegue

La aplicación admite dos modos de despliegue. En ambos, la base de datos se
inicializa desde [`app/db/schema.sql`](app/db/schema.sql) y la configuración se
resuelve por archivo (`config.php`) o por variables de entorno.

Orden de resolución de la configuración (lo maneja `app/web/app/bootstrap.php`):

1. Archivo indicado por la variable `TICKETS_CONFIG`.
2. Rutas conocidas: `app/server-config/config.php`, `config.local.php`.
3. Variables de entorno (`DB_HOST`, `DB_NAME`, …) → `app/web/app/config_env.php`.

---

## Opción A — Docker (recomendada para replicar en otra organización)

Requisitos: Docker y Docker Compose.

```bash
git clone <URL-del-repositorio> rendicion-tickets
cd rendicion-tickets
cp .env.example .env
# Editá .env: credenciales de la base, dominio de Google, marca, etc.
docker compose up -d --build
```

En el primer arranque, el contenedor:

- espera a que la base responda,
- crea el almacenamiento de imágenes,
- importa el esquema si la base está vacía (`docker/dbinit.php`),
- levanta Apache.

La aplicación queda en `http://localhost:8080` (configurable con `APP_PORT`).

Para servir en la raíz de un dominio, dejá `APP_BASE_PATH=` (vacío). Detrás de un
proxy inverso (nginx/traefik) con HTTPS, terminá TLS en el proxy y pasá el tráfico
al contenedor.

## Opción B — Hosting web (Hostinger / cPanel / Apache + PHP 8.1+)

1. Subí el contenido del repositorio al hosting.
2. Configurá el **DocumentRoot** del sitio en `app/web/`.
3. Base de datos: creala en el panel e importá `app/db/schema.sql`
   (por phpMyAdmin o `mysql -u USER -p BASE < app/db/schema.sql`).
4. Configuración: copiá `app/server-config/config.example.php` a un `config.php`
   **fuera del web root** y completá los valores. Apuntá a él con la variable
   `TICKETS_CONFIG` (el `.htaccess` de ejemplo ya la define).
5. Almacenamiento: definí `app.storage` a una carpeta **fuera del web root**.
6. Verificá la instalación:

```bash
php bin/setup.php
```

`bin/setup.php` comprueba PHP y extensiones, localiza la configuración, prueba la
base, importa el esquema si falta y valida el almacenamiento.

---

## Configuración de Google (login + respaldo en Drive)

En Google Cloud → **APIs y servicios**:

1. Pantalla de consentimiento OAuth: tipo **Interno** (una organización de
   Workspace) para no requerir verificación de scopes.
2. Credencial **OAuth 2.0 (aplicación web)**:
   - Orígenes autorizados: el dominio del sitio.
   - URI de redirección autorizada: `https://TU-DOMINIO/BASE_PATH/google-callback`
     (la misma URI sirve para el login y para conectar Drive).
3. Habilitá la **Google Drive API** (para el respaldo de imágenes).
4. Cargá `client_id`, `client_secret` y `allowed_domain` en la configuración.

El respaldo en Drive se activa desde la propia app (menú **Drive**, solo admin):
botón **Conectar**, autorizando con la cuenta dueña de la carpeta destino. Mientras
no se conecte, las imágenes se guardan localmente sin inconvenientes.

## Requisitos técnicos

- PHP **8.1+** con `pdo_mysql`, `mbstring`, `curl`, `fileinfo`.
- MySQL **8.0+** o MariaDB **10.4+** (utf8mb4).
- Apache con `mod_rewrite` (o equivalente).

## Marca / white-label

Ver [BRANDING.md](BRANDING.md) para adaptar nombre, colores y logo a otra
organización sin tocar el código.
