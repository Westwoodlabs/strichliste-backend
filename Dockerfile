FROM php:8.1-apache

RUN docker-php-ext-install pdo_mysql

COPY ./ /var/www/html

RUN chown -R www-data:www-data /var/www/html

# configure apache
COPY ./docker/apache.conf /etc/apache2/sites-available/000-default.conf