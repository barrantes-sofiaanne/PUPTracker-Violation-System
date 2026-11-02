FROM public.ecr.aws/docker/library/php:8.5-cli
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
    gnupg \
    ca-certificates \
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

RUN set -eux; \
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php; \
    php /tmp/composer-setup.php \
        --install-dir=/usr/local/bin \
        --filename=composer; \
    rm -f /tmp/composer-setup.php; \
    composer --version
# Allow Composer plugins to run and configure process timeout
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_PROCESS_TIMEOUT=1200

# Install Node.js 22
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs
# Install Oracle MySQL client tools for remote binlog access
RUN curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 \
    | gpg --dearmor -o /usr/share/keyrings/mysql.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/mysql.gpg] https://repo.mysql.com/apt/debian/ trixie mysql-9.7-lts" \
    > /etc/apt/sources.list.d/mysql.list \
    && apt-get update \
    && apt-get install -y mysql-community-client \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
RUN command -v mysqldump \
    && command -v mysqlbinlog \
    && mysqldump --version \
    && mysqlbinlog --version
# Copy the entire Laravel application
COPY . .

# Install PHP dependencies with retries
RUN set -eux; \
    attempts=0; \
    until composer install --no-dev --optimize-autoloader --no-interaction; do \
        attempts=$((attempts + 1)); \
        if [ "$attempts" -ge 5 ]; then \
            echo "Composer install failed after $attempts attempts" >&2; \
            exit 1; \
        fi; \
        echo "Composer install attempt $attempts failed; retrying in 5 seconds..." >&2; \
        sleep 5; \
    done

# Verify PHP 8.5 and installed production dependencies
RUN composer check-platform-reqs --no-dev
# Build frontend assets
RUN if [ -f package.json ]; then \
    npm ci && \
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

# Start Laravel
CMD ["sh", "-c", "if [ \"${CLEAR_MAINTENANCE_ON_DEPLOY:-false}\" = \"true\" ]; then php artisan up; fi; exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
