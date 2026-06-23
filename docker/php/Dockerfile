FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    icu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        zip \
        intl \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
