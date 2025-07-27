#!/bin/bash
# Deploy Laravel to nano server via AWS Systems Manager or user data

echo "🚀 Creating nano deployment package..."

# Create a simple Laravel container setup
cat > user-data.sh << 'EOF'
#!/bin/bash
# This will run on the nano server

echo "Setting up Laravel container on nano..."

# Navigate to home directory
cd /home/ubuntu

# Create Laravel container setup
mkdir -p jp-site && cd jp-site

# Create Dockerfile
cat > Dockerfile << 'DOCKERFILE'
FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git curl zip unzip sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Create a simple Laravel-style app
RUN echo '<?php
echo "<h1>🎯 jordanpartridge.us - Container Edition</h1>";
echo "<p>Running on: " . gethostname() . "</p>";
echo "<p>PHP: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER["SERVER_SOFTWARE"] . "</p>";
echo "<p>Time: " . date("Y-m-d H:i:s") . "</p>";
echo "<p>💰 Monthly cost: ~$3 (vs $15 on Forge)</p>";
echo "<p>🐳 Status: Container deployed successfully!</p>";
?>' > index.php

# Apache config
RUN echo '<VirtualHost *:80>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2-foreground"]
DOCKERFILE

# Create docker-compose
cat > docker-compose.yml << 'COMPOSE'
version: '3.8'
services:
  web:
    build: .
    ports:
      - "80:80"
    restart: unless-stopped
    environment:
      - APP_ENV=production
COMPOSE

# Build and run
echo "Building container..."
docker-compose up -d --build

echo "✅ Container deployed on nano server!"
echo "🌐 Test: curl http://13.57.206.160"
EOF

echo "✅ Nano deployment script created!"
echo "📋 This script sets up a simple containerized site on your nano server"