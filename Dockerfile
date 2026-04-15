FROM php:8.2-cli

WORKDIR /app

COPY . /app

# Install MySQL support
RUN docker-php-ext-install mysqli pdo pdo_mysql

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80"]
