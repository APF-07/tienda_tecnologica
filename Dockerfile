FROM php:8.2-apache-bookworm

# Instalar extensiones necesarias para MySQL y PDO
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev python3 python3-venv \
    && docker-php-ext-install pdo_mysql mysqli curl mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN python3 -m venv /opt/ml
COPY ml/requirements.txt /tmp/ml-requirements.txt
RUN /opt/ml/bin/pip install --no-cache-dir -r /tmp/ml-requirements.txt
ENV PYTHON_BIN=/opt/ml/bin/python
ENV PATH="/opt/ml/bin:${PATH}"

# Habilitar mod_rewrite de Apache para URLs limpias (si lo usas)
RUN a2enmod rewrite
COPY deploy/apache.conf /etc/apache2/conf-available/tienda.conf
RUN a2enconf tienda
RUN printf 'display_errors=Off\nlog_errors=On\nexpose_php=Off\ndate.timezone=America/Lima\n' > /usr/local/etc/php/conf.d/production.ini

# Copiar los archivos de la tienda al directorio web del servidor
COPY . /var/www/html/

# Dar permisos correctos a la carpeta
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
