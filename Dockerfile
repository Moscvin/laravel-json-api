FROM php:8.3-fpm-bullseye

RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    curl \
    libonig-dev \
    bzip2 \
    libxml2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    pkg-config \
    libssl-dev \
    zlib1g-dev \
    autoconf \
    bison \
    re2c \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www

EXPOSE 9000

# === CRON SETUP ===
RUN apt-get update && apt-get install -y cron && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/log/laravel

# Job cron cu calea completă la php
RUN echo "* * * * * cd /var/www/html && /usr/local/bin/php artisan import:loadsmart >> /var/log/laravel/cron.log 2>&1" > /etc/cron.d/laravel-import

RUN chmod 0644 /etc/cron.d/laravel-import

RUN crontab /etc/cron.d/laravel-import

# Pornește cron în background + php-fpm ca proces principal
CMD exec cron -f & php-fpm
