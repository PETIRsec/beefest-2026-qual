FROM php:8.2-apache

COPY docker/phar.conf /etc/apache2/conf-available/phar.conf
RUN a2enconf phar

RUN mkdir -p /app/keys/keys && printf '%s' 'x9#kP2@mQ7' > /app/keys/keys/default.key

COPY src/ /var/www/html/
RUN mkdir -p /var/www/html/uploads \
 && chown -R root:root /var/www/html \
 && find /var/www/html -type d -exec chmod 755 {} \; \
 && find /var/www/html -type f -exec chmod 644 {} \; \
 && chown www-data:www-data /var/www/html/uploads \
 && chmod 755 /var/www/html/uploads

COPY flag_0203cc75753ab94a5b7d4cc1ddf8b65d.txt /flag_0203cc75753ab94a5b7d4cc1ddf8b65d.txt
RUN chmod 644 /flag_0203cc75753ab94a5b7d4cc1ddf8b65d.txt

EXPOSE 80
