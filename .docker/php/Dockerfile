FROM php:8.3-fpm-alpine

# Dependencias del sistema y extensiones de PHP para PostgreSQL, manipulación de imágenes, ZIP y Filament
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    bash \
    git \
    curl \
    nano \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        exif \
        zip \
        intl \
        bcmath \
        opcache

# Instalar Composer versión 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
