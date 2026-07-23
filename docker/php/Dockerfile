FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    libonig-dev

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip

# phpredis — required for QUEUE_CONNECTION=redis / REDIS_CLIENT=phpredis
RUN pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Avoid git "dubious ownership" errors on the bind-mounted project
RUN git config --global --add safe.directory /var/www

WORKDIR /var/www
