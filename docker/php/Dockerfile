FROM php:8.4-fpm

# install-php-extensions handles the build deps and cleanup for us.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_mysql intl opcache zip @composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /app
