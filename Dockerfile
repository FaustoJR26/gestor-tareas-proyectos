FROM php:8.3-apache

# Extensión para conectarse a MySQL
RUN docker-php-ext-install pdo_mysql

# Composer (genera vendor/autoload.php sin las herramientas de pruebas)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache debe servir la carpeta public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts

COPY . .

# Carpeta donde se guardan los documentos (va montada en un volumen)
ENV DOCS_DIR=/var/www/storage/documentos
RUN mkdir -p /var/www/storage/documentos \
 && chown -R www-data:www-data /var/www/storage