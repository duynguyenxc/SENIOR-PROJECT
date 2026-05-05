# PHP 8.3 + Apache image for serving the web application
FROM php:8.3-apache

# Install SSL certs (needed for remote DB connections) and the PDO MySQL driver
RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql \
 && a2enmod rewrite \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/
COPY ./render-start.sh /usr/local/bin/render-start.sh

RUN chmod +x /usr/local/bin/render-start.sh \
 && mkdir -p /var/www/html/uploads \
 && chown -R www-data:www-data /var/www/html

CMD ["render-start.sh"]
