#!/bin/sh
set -e

# Make the application writable by the Apache user
chown -R www-data:www-data /var/www/html

# Install composer dependencies
if [ -f /var/www/html/composer.json ]; then
    echo "Installing Composer dependencies..."
    cd /var/www/html && composer install --no-interaction --optimize-autoloader
fi

# Enable mod_rewrite and allow .htaccess
if [ -f /etc/apache2/mods-available/rewrite.load ]; then
    a2enmod rewrite
fi

# Copy our custom rewrite config
if [ -f /var/www/html/docker/apache-rewrite.conf ]; then
    cp /var/www/html/docker/apache-rewrite.conf /etc/apache2/conf-available/rewrite.conf
    a2enconf rewrite
fi

echo "Waiting for MariaDB..."
php /var/www/html/sql/init-db.php

# Hand off to the OpenConext base container entrypoint (APACHE_UID etc.)
exec /entrypoint.sh "$@"
