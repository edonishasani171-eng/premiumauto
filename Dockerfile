FROM php:8.3-apache

# Install MySQLi extension for your database connection
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy your local project files into the Apache server directory
COPY . /var/www/html/

# Expose port 80 for web traffic
EXPOSE 80