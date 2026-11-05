FROM php:8.2-apache

# Install recommended system dependencies and PHP extensions (curl, gd, mbstring)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Configure Apache port for Render (Render binds to $PORT, default 80 or 10000)
ENV PORT 10000
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create upload directories and set full write permissions
RUN mkdir -p /var/www/html/images/uploads/slips \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 777 /var/www/html/images/uploads

# Expose port
EXPOSE 10000

# Start Apache in foreground
CMD ["apache2-foreground"]
