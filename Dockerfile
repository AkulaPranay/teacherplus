FROM php:8.2-apache

# Enable mysqli
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project files
COPY . /var/www/html/public

# Set working directory
WORKDIR /var/www/html

# Expose port
EXPOSE 80
