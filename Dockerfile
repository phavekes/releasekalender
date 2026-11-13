# syntax=docker/dockerfile:1

FROM ghcr.io/openconext/openconext-basecontainers/php85-apache2:latest

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Point Apache at the public/ docroot (default site config uses /var/www/html)
RUN set -eux; \
    sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
        /etc/apache2/sites-available/*.conf;

WORKDIR /var/www/html

COPY . .

RUN chown -R www-data:www-data /var/www/html

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

EXPOSE 80

# Prepares the database, then hands off to the base image entrypoint
# (APACHE_UID/APACHE_GID handling, cache dirs) which starts Apache.
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
