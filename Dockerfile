# Use PHP 8.3 with Apache
FROM php:8.3-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libgd-dev \
    libicu-dev \
    libpq-dev \
    jpegoptim optipng pngquant gifsicle \
    zip \
    unzip \
    nodejs \
    npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
        intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Verify Composer installation and PHP extensions
RUN composer --version \
    && php -m | grep -E "(pdo|pgsql|mbstring|bcmath|gd|zip|opcache|intl)" \
    && echo "All required PHP extensions are installed"

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy Apache configuration
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set PHP configuration for production
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini \
    && echo "upload_max_filesize=100M" >> /usr/local/etc/php/conf.d/docker-php-uploads.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/docker-php-uploads.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/docker-php-timeouts.ini

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Verify composer files exist
RUN ls -la composer.* && echo "Composer files found"

# Install PHP dependencies with verbose output (skip scripts that need Laravel)
RUN composer install --no-dev --optimize-autoloader --no-scripts --verbose

# Copy package files for Node dependencies
COPY package.json package-lock.json ./

# Verify package files
RUN ls -la package*.json && echo "Package files found"

# Install Node dependencies
RUN npm ci --verbose

# Copy application files
COPY . /var/www/html

# Create .env file for build process (real values come from environment at runtime)
RUN cp .env.example .env && \
    echo "APP_KEY=base64:dGVtcG9yYXJ5a2V5Zm9yYnVpbGRvbmx5MTIzNA==" >> .env

# Run composer dump-autoload without triggering Laravel scripts
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --optimize --no-scripts

# Build frontend assets
RUN npm run build

# Publish Livewire config (may already exist, so allow failure)
RUN php artisan vendor:publish --tag=livewire:config --force || true

# Configure Livewire for HTTPS (only if file exists)
RUN if [ -f config/livewire.php ]; then \
    sed -i "s/'secure' => false,/'secure' => env('LIVEWIRE_SECURE', false),/" config/livewire.php; \
    fi

# Set timezone to UTC
RUN ln -snf /usr/share/zoneinfo/Etc/UTC /etc/localtime && echo Etc/UTC > /etc/timezone

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create storage directories
RUN mkdir -p /var/www/html/storage/app/public \
    && mkdir -p /var/www/html/storage/logs

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 80
EXPOSE 80

# Use custom entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
