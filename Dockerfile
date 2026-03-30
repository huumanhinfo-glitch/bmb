# BMB Application - Dockerfile for Fly.io
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    curl \
    libzip-dev \
    zip \
    unzip

# Install PHP extensions with SSL support
RUN docker-php-ext-install pdo pdo_mysql pdo_sqlite zip

# Configure Nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Configure Supervisor
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create directories
RUN mkdir -p /var/www/html/uploads /var/log/supervisor

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 8080 (Fly.io uses this)
EXPOSE 8080

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
