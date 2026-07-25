#!/bin/sh
# ============================================================
#  Arranque del contenedor: espera la base, prepara storage,
#  importa el esquema si hace falta y levanta Apache.
# ============================================================
set -e

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
STORAGE="${APP_STORAGE:-/var/www/storage}"

echo "→ Esperando la base de datos en ${DB_HOST}:${DB_PORT} ..."
i=0
until php -r '$h=getenv("DB_HOST")?:"db"; $p=(int)(getenv("DB_PORT")?:3306); exit(@fsockopen($h,$p)?0:1);'; do
    i=$((i+1))
    if [ "$i" -ge 60 ]; then
        echo "✗ La base no respondió a tiempo; sigo igual (la app reintentará)."
        break
    fi
    sleep 2
done

echo "→ Preparando almacenamiento en ${STORAGE} ..."
mkdir -p "${STORAGE}/uploads"
chown -R www-data:www-data "${STORAGE}" || true

echo "→ Verificando el esquema de la base ..."
php /var/www/html/docker/dbinit.php || echo "  (se omitió la inicialización del esquema)"

echo "→ Iniciando Apache."
exec apache2-foreground
