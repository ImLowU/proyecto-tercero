# =====================================================================
# IMAGEN DE LA APLICACIÓN WEB (servicio "web" de docker-compose.yml)
# PHP 8.2 con Apache, la misma versión de PHP que XAMPP. El código se
# copia dentro de la imagen: el contenedor no depende de la carpeta del
# proyecto en la máquina, así que se puede llevar a cualquier servidor.
# =====================================================================
FROM php:8.2-apache

# - pdo_mysql: la conexión a MariaDB (mbstring y lo demás que usa la
#   aplicación ya viene en la imagen).
# - openssl: genera un certificado autofirmado si todavía no hay uno real.
RUN apt-get update \
    && apt-get install -y --no-install-recommends openssl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql

# Módulos de Apache: rewrite (la API y las direcciones sin ".html"),
# headers (las cabeceras de seguridad de public/.htaccess) y ssl (HTTPS).
RUN a2enmod rewrite headers ssl

# PHP: la configuración recomendada para producción, más la propia.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/web/php.ini "$PHP_INI_DIR/conf.d/nextleague.ini"

# Apache: el sitio, con public/ como raíz y HTTPS.
COPY docker/web/apache.conf /etc/apache2/sites-available/000-default.conf

# El código (.dockerignore deja afuera lo que no hace falta). config.php
# es el de docker/web: toma todo de las variables de entorno (.env).
COPY . /var/www/nextleague
COPY docker/web/config.php /var/www/nextleague/config.php

# Apache (usuario www-data) solo escribe en storage/ (sesiones, registros)
# y en public/uploads/ (fotos, logos y reglas que suben los usuarios).
RUN mkdir -p /var/www/nextleague/storage/sesiones /var/www/nextleague/storage/logs \
             /var/www/nextleague/storage/correos /var/www/nextleague/public/uploads \
    && chown -R www-data:www-data /var/www/nextleague/storage /var/www/nextleague/public/uploads

# Puerto HTTPS que ve el navegador (para redirigir de HTTP a HTTPS).
# docker-compose.yml lo cambia por el del archivo .env.
ENV NL_PUERTO_HTTPS=443

# El script de arranque (sed: por si llegó con fin de línea de Windows).
COPY docker/web/entrypoint.sh /usr/local/bin/nextleague-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/nextleague-entrypoint \
    && chmod +x /usr/local/bin/nextleague-entrypoint

WORKDIR /var/www/nextleague
EXPOSE 80 443
ENTRYPOINT ["nextleague-entrypoint"]
CMD ["apache2-foreground"]
