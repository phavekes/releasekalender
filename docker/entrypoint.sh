#!/bin/sh
set -e

# Make the application writable by the Apache user
chown -R www-data:www-data /var/www/html

echo "Waiting for MariaDB..."
php /var/www/html/bin/init-db.php

# Hand off to the OpenConext base container entrypoint (APACHE_UID etc.)
exec /entrypoint.sh "$@"
