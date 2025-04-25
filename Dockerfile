FROM php:8.2-apache

# Enable mod_rewrite for Slim
RUN a2enmod rewrite

# Install SQLite and other dependencies needed by composer packages
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    unzip \
    zip \
    git \
    && docker-php-ext-install pdo pdo_sqlite

# Set working directory
WORKDIR /var/www

# Copy project files
COPY . .

# Copy Composer from official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run composer install to generate vendor/
RUN composer install

# Set correct permissions
RUN chown -R www-data:www-data /var/www

# Use custom Apache config
COPY apache.conf /etc/apache2/sites-available/000-default.conf
