#!/bin/bash
# Simple container deployment script

echo "🚀 Setting up simple Laravel container..."

# First create a basic working directory
mkdir -p ~/jp-containers && cd ~/jp-containers

# Create a simple Dockerfile for Laravel
cat > Dockerfile << 'EOF'
FROM php:8.3-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy app (we'll mount this)
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader || echo "No composer.json found"

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Apache config for Laravel
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2-foreground"]
EOF

# Create docker-compose for complete setup
cat > docker-compose.yml << 'EOF'
version: '3.8'
services:
  web:
    build: .
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    environment:
      - APP_ENV=production
    networks:
      - jp-network

networks:
  jp-network:
    driver: bridge
EOF

# Create a basic index.php to test
cat > index.php << 'EOF'
<?php
echo "<h1>🐳 Container Test Page</h1>";
echo "<p>Server: " . $_SERVER['SERVER_NAME'] ?? 'Unknown' . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Status: ✅ Container is running!</p>";
?>
EOF

echo "✅ Container setup files created!"
echo "🎯 Run: docker-compose up -d"
echo "💰 This saves you ~$12/month vs Forge"