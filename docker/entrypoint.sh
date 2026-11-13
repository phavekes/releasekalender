#!/bin/sh
set -e

# Make the application writable by the Apache user
chown -R www-data:www-data /var/www/html

# Install composer dependencies
if [ -f /var/www/html/composer.json ]; then
    echo "Installing Composer dependencies..."
    cd /var/www/html && composer install --no-interaction --optimize-autoloader
fi

echo "Waiting for MariaDB..."
php /var/www/html/sql/init-db.php

# Hand off to the OpenConext base container entrypoint (APACHE_UID etc.)
exec /entrypoint.sh "$@"
