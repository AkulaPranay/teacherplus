FROM php:8.2-apache

RUN rm -rf /etc/apache2/mods-enabled/mpm_* \
 && a2enmod mpm_prefork

WORKDIR /var/www/html

COPY . /var/www/html/

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

EXPOSE 80
