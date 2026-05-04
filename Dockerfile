# ==========================================
# Stage 1: Build Frontend Assets (Vite)
# ==========================================
FROM node:20-alpine AS node-builder
WORKDIR /app

# Copy seluruh source code
COPY . .

# Install NPM dan Build (Menggunakan Node 20 agar tidak error versi)
RUN npm install && npm run build

# ==========================================
# Stage 2: Setup PHP-FPM Backend
# ==========================================
FROM php:8.2-fpm

# Install system dependencies (Tanpa Node.js karena sudah di Stage 1)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    zip \
    unzip \
    libpq-dev \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Laravel and Filament
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql exif pcntl bcmath gd zip intl

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory contents
COPY . .

# Copy hasil build aset Frontend dari Stage 1
COPY --from=node-builder /app/public/build ./public/build

# Membuat struktur cache Laravel dan Install PHP dependencies
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && cp .env.example .env \
    && php -d memory_limit=-1 /usr/bin/composer install --no-interaction --prefer-dist --optimize-autoloader

# Set directory permissions for Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
