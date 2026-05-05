#!/bin/sh
set -eu

# Make sure the uploads directory exists and is writable by Apache
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads

exec apache2-foreground
