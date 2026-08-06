FROM php:8.2-cli

# Install system packages and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        gd \
        intl \
        zip \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

WORKDIR /app

# Copy the entire Laravel application first
COPY . .

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction

# Build frontend assets
RUN if [ -f package.json ]; then \
    npm install && \
    npm run build; \
    fi

# Laravel cache directories
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Set permissions
RUN chmod -R 775 storage bootstrap/cache


EXPOSE 8080

CMD if [ "${CLEAR_MAINTENANCE_ON_DEPLOY:-false}" = "true" ]; then php artisan up; fi; exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
