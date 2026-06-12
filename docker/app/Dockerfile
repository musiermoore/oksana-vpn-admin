FROM php:8.3-cli-bookworm

ARG DEBIAN_FRONTEND=noninteractive
ARG IMAGICK_VERSION=3.8.1
ARG REDIS_VERSION=6.3.0

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
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
    && curl -fsSL -o /tmp/imagick.tgz "https://github.com/Imagick/imagick/archive/refs/tags/${IMAGICK_VERSION}.tar.gz" \
    && git clone --depth 1 --branch "${REDIS_VERSION}" https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && git -C /tmp/phpredis submodule update --init --recursive \
    && pecl install /tmp/imagick.tgz \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
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
    && rm -rf /tmp/imagick.tgz /tmp/phpredis \
    && apt-get purge -y --auto-remove \
        $PHPIZE_DEPS \
        libmagickwand-dev \
        libpng-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
