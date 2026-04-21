FROM php:8.3-fpm-alpine AS base

# Extensions système nécessaires
RUN apk add --no-cache \
    bash \
    git \
    curl \
    libpq-dev \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev

# Extensions PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    mbstring \
    bcmath

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─────────────────────────────────────────
# Stage développement
# ─────────────────────────────────────────
FROM base AS dev

# Xdebug pour le debug local
RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/app.ini

USER www-data

# ─────────────────────────────────────────
# Stage production
# ─────────────────────────────────────────
FROM base AS prod

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/app.ini

# Copier le code et installer les dépendances sans dev
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html/var

USER www-data
