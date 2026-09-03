FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    icu-dev \
    rabbitmq-c \
    && docker-php-ext-install \
        pdo_pgsql \
        zip \
        intl \
        opcache \
        bcmath \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers rabbitmq-c-dev \
    && pecl install xdebug amqp \
    && docker-php-ext-enable xdebug amqp \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
