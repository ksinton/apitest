FROM php:8.2-apache

# Install system packages
RUN apt-get update && apt-get install -y \
    unzip curl sqlite3 libsqlite3-dev git nano \
    && docker-php-ext-install pdo pdo_sqlite

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy all files
COPY . .

# Configure Apache to serve from public/
RUN echo "DocumentRoot /var/www/html/public" > /etc/apache2/sites-available/000-default.conf

# Install PHP dependencies
RUN composer install

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
