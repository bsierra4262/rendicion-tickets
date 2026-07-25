# ============================================================
#  Rendición de Tickets — imagen de aplicación (PHP 8.2 + Apache)
# ============================================================
FROM php:8.2-apache

# Extensiones PHP requeridas + mod_rewrite
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev default-mysql-client \
 && docker-php-ext-install pdo_mysql mbstring \
 && a2enmod rewrite \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configuración de Apache (DocumentRoot → app/web, front-controller, protección del código)
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Código de la aplicación
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
 && chmod +x /var/www/html/docker/entrypoint.sh

ENV APP_STORAGE=/var/www/storage
VOLUME ["/var/www/storage"]

EXPOSE 80
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
