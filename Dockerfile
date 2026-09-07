FROM php:8.2-apache

# Instalar extensiones necesarias para MySQL y PDO
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite de Apache para URLs limpias (si lo usas)
RUN a2enmod rewrite

# Copiar los archivos de la tienda al directorio web del servidor
COPY . /var/www/html/

# Dar permisos correctos a la carpeta
RUN chown -R www-data:www-data /var/www/html