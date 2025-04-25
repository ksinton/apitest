FROM php:8.2-apache

# Enable mod_rewrite for Slim
RUN a2enmod rewrite

# Install SQLite and other needed extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite

# Set working directory
WORKDIR /var/www/html

# Copy project files into container
COPY . /var/www/html

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Use custom Apache config to enable .htaccess
COPY apache.conf /etc/apache2/sites-available/000-default.conf

docker compose up --build
