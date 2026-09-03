FROM php:8.5.9-apache-bookworm

ARG APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV APACHE_DOCUMENT_ROOT=${APACHE_DOCUMENT_ROOT}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli \
        pdo_mysql \
        intl \
        zip \
        gd \
    && a2enmod rewrite headers expires \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY ./docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

EXPOSE 80
