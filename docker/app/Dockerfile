FROM php:8.3-cli-bookworm

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        openssh-client \
        unzip \
        libmagickwand-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick \
    && docker-php-ext-enable redis \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        dom \
        exif \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_mysql \
        xml \
        xmlwriter \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
