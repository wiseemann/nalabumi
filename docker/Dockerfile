# Stage 1: PHP dependencies
FROM composer:latest AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts

# Stage 2: Frontend assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 3: Production image
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd bcmath \
    && docker-php-ext-enable opcache

RUN addgroup -S app && adduser -S app -G app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY --chown=app:app . /app
COPY --from=vendor --chown=app:app /app/vendor /app/vendor
COPY --from=frontend --chown=app:app /app/public/build /app/public/build

WORKDIR /app

RUN php artisan storage:link || true

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
