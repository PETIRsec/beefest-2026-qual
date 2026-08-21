FROM php:8.2-apache

RUN apt-get update && rm -rf /var/lib/apt/lists/*

COPY html /var/www/html
COPY flag.txt /flag.txt


RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"


EXPOSE 80