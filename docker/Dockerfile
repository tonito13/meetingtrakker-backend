FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install system dependencies and PHP extensions for CakePHP + PostgreSQL
RUN apt-get update -y && apt-get install -y \
    libicu-dev libpq-dev unzip zip vim git && \
    docker-php-ext-install intl pdo pdo_pgsql && \
    rm -rf /var/lib/apt/lists/*

# Set document root to html
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Update Apache default site config
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for better Docker layer caching)
COPY composer.json ./
COPY composer.lock* ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy all application files
COPY . /var/www/html/

# Run post-install scripts if needed
RUN composer run-script post-install-cmd --no-dev

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Start Apache
CMD ["apache2-foreground"] 