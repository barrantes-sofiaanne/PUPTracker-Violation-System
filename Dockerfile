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

# Allow Composer plugins to run (build runs as root) and give downloads
# a generous timeout. This avoids "Plugins have been disabled" warnings and
# intermittent GitHub/API timeouts aborting the build.
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_PROCESS_TIMEOUT=1200

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

WORKDIR /app

# Copy the entire Laravel application first
COPY . .

# Install PHP dependencies with retries — package downloads from
# api.github.com / packagist are flaky and a single transient 504 must
# not abort the whole build.
RUN set -eux; \
    install_cmd="composer install --no-dev --optimize-autoloader --no-interaction"; \
    attempts=0; \
    until eval "$install_cmd"; do \
        attempts=$((attempts + 1)); \
        if [ "$attempts" -ge 5 ]; then \
            echo "composer install failed after $attempts attempts" >&2; \
            exit 1; \
        fi; \
        echo "composer install attempt $attempts failed (network?) - retrying in 5s..." >&2; \
        sleep 5; \
    done

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

# JSON-array (exec) form via sh -c so OS signals are handled correctly while
# still allowing the shell features (env expansion + conditional artisan up).
CMD ["sh", "-c", "if [ \"${CLEAR_MAINTENANCE_ON_DEPLOY:-false}\" = \"true\" ]; then php artisan up; fi; exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
