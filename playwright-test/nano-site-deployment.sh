#!/bin/bash
cd /home/ubuntu
mkdir -p jp-site && cd jp-site

# Create simple PHP site container
cat > Dockerfile << 'DOCKERFILE'
FROM php:8.3-apache

WORKDIR /var/www/html

RUN echo '<?php
echo "<h1>🎯 jordanpartridge.us - Nano Container</h1>";
echo "<p><strong>Server:</strong> " . gethostname() . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Time:</strong> " . date("Y-m-d H:i:s T") . "</p>";
echo "<p><strong>Instance:</strong> t4g.nano ARM (AWS)</p>";
echo "<p><strong>Cost:</strong> ~$3/month vs $15/month on t3.small</p>";
echo "<p><strong>Status:</strong> 🟢 Container deployed successfully!</p>";
echo "<hr>";
echo "<h2>🐳 Container Info</h2>";
echo "<pre>";
system("uname -a");
echo "</pre>";
?>' > index.php

EXPOSE 80
CMD ["apache2-foreground"]
DOCKERFILE

# Build and run container
docker build -t jp-nano-site .
docker stop jp-site 2>/dev/null || true
docker rm jp-site 2>/dev/null || true
docker run -d --name jp-site -p 80:80 --restart=unless-stopped jp-nano-site

echo "✅ Site deployed on nano container!"
