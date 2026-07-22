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

# Install the phpredis extension (used by the redis queue & cache).
RUN apt-get update && apt-get install -y $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y $PHPIZE_DEPS \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Avoid git "dubious ownership" errors on the bind-mounted project
RUN git config --global --add safe.directory /var/www

WORKDIR /var/www
