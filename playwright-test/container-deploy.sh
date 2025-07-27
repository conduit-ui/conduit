#!/bin/bash
# Deploy Laravel containers to your ARM server

echo "🚀 Deploying jordanpartridge.us containers..."

# Create Dockerfile for Laravel
cat > Dockerfile.laravel << 'EOF'
FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache nginx supervisor curl zip unzip git \
    mysql-client redis \
    && docker-php-ext-install pdo_mysql bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Setup directory
WORKDIR /var/www/html

# Copy Laravel app
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Setup permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

# Nginx config
COPY <<NGINX /etc/nginx/nginx.conf
events {
    worker_connections 1024;
}
http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    server {
        listen 80;
        root /var/www/html/public;
        index index.php;
        
        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }
        
        location ~ \.php\$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        }
    }
}
NGINX

# Supervisor config
COPY <<SUPERVISOR /etc/supervisor/conf.d/laravel.conf
[supervisord]
nodaemon=true

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
SUPERVISOR

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/laravel.conf"]
EOF

# Create docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  # Laravel App
  laravel-app:
    build: 
      context: ./laravel-app
      dockerfile: ../Dockerfile.laravel
    container_name: jp-laravel-app
    ports:
      - "80:80"
    environment:
      - APP_ENV=production
      - DB_CONNECTION=mysql
      - DB_HOST=mysql
      - DB_DATABASE=jordanpartridge
      - DB_USERNAME=root
      - DB_PASSWORD=secret
      - REDIS_HOST=redis
    volumes:
      - ./laravel-app:/var/www/html
    depends_on:
      - mysql
      - redis
    networks:
      - jp-network

  # MySQL Database
  mysql:
    image: mysql:8.0
    container_name: jp-mysql
    environment:
      - MYSQL_ROOT_PASSWORD=secret
      - MYSQL_DATABASE=jordanpartridge
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - jp-network

  # Redis Cache
  redis:
    image: redis:7-alpine
    container_name: jp-redis
    volumes:
      - redis-data:/data
    networks:
      - jp-network

volumes:
  mysql-data:
  redis-data:

networks:
  jp-network:
    driver: bridge
EOF

echo "✅ Container configuration created!"
echo "🎯 Next: Transfer this to your container server at 13.57.206.160"
echo "💰 This will cost ~$3/month vs current $15/month"