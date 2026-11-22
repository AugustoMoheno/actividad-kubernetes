# Imagen de la web.
# docker build -t USUARIO_DOCKERHUB/notas-web:1.0 ./app
FROM php:8.3-apache

# mysqli es la librería con la que PHP se conecta a MariaDB.
RUN docker-php-ext-install mysqli \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY index.php /var/www/html/index.php

EXPOSE 80
