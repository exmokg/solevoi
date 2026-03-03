FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libcurl4-openssl-dev \
    libsqlite3-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install curl pdo_sqlite pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Create necessary directories
RUN mkdir -p cloned_sites tool_sessions cookie_jars exports queue logs

# Set permissions
RUN chown -R www-data:www-data /app

# Expose port for web interface
EXPOSE 8080

# Set environment variables
ENV APP_ENV=production

# Default command
CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]