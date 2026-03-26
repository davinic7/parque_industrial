FROM php:8.2-apache

# Habilitar el módulo rewrite de Apache (muy útil para aplicaciones PHP)
RUN a2enmod rewrite

# Instalar extensiones de base de datos
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Cambiar el DocumentRoot de Apache de /var/www/html a /var/www/html/public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copiar el código de tu proyecto al contenedor
COPY . /var/www/html/

# Dar permisos correctos para que Apache pueda leer los archivos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
