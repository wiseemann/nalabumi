FROM php:8.4-cli-alpine

# Install PHP extension installer helper (fast pre-compiled binaries)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    nodejs \
    npm \
    bash \
    mariadb-client

# Install PHP extensions without slow manual source compilation
RUN install-php-extensions pdo_mysql gd bcmath mbstring

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy code
COPY . .

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader 

# Install Node dependencies
RUN npm install

# Expose only the Laravel app port
EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
