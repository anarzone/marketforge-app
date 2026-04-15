FROM php:8.4-cli-alpine

# System packages needed by PHP extensions
RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    autoconf \
    g++ \
    make

# install-php-extensions makes installing PHP extensions painless
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_pgsql \
    redis \
    intl \
    bcmath \
    zip \
    opcache \
    pcntl

# Composer (multi-stage copy keeps the image small)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

# `php artisan serve` is fine for local dev. Swap for FrankenPHP / nginx + fpm
# when we need a production-shaped setup.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
