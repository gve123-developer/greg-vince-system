# Stage 1: Build React Frontend
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: Production PHP Apache Server
FROM php:8.2-apache

# Install required PHP extensions for MySQL and performance
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql

# Enable Apache mod_rewrite for SPA routing and API handling
RUN a2enmod rewrite

# Configure Apache to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set default environment variables for Port 8000 and Coolify MySQL database
ENV PORT=8000
ENV DB_HOST=ierbkglctwgkpyshwqkdlht3
ENV DB_PORT=3306
ENV DB_USER=mysql
ENV DB_PASSWORD=larable
ENV DB_NAME=default
ENV DATABASE_URL=mysql://mysql:larable@ierbkglctwgkpyshwqkdlht3:3306/default

# Pre-configure Apache to listen on port 8000
RUN sed -i 's/Listen 80/Listen 8000/' /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8000>/' /etc/apache2/sites-available/000-default.conf

# Copy built React static assets to Apache web root
COPY --from=build /app/dist /var/www/html

# Copy backend PHP APIs, includes, and mailer
COPY --from=build /app/api /var/www/html/api
COPY --from=build /app/includes /var/www/html/includes
COPY --from=build /app/PHPMailer /var/www/html/PHPMailer

# Copy diagnostics script and database schema
COPY --from=build /app/test_prod_db.php /var/www/html/test_prod_db.php
COPY --from=build /app/inventory_system_setup.sql /var/www/html/inventory_system_setup.sql

# Copy .htaccess to Apache web root
COPY .htaccess /var/www/html/.htaccess

# Copy entrypoint script for dynamic port adjustment
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

# Set correct ownership for Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
