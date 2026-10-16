FROM php:8.3-apache
RUN docker-php-ext-install pdo pdo_mysql && a2enmod rewrite
COPY public/ /var/www/html/
COPY config.example.php /var/www/config.php
RUN printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/onecar-rewrite.conf \
 && a2enconf onecar-rewrite
EXPOSE 80
