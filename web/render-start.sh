#!/bin/sh
set -eu

mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads

exec apache2-foreground
