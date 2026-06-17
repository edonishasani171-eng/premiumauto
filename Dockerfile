FROM php:8.3-apache

# Install MySQLi extension for your database connection
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Force Apache to pass Render environment variables to PHP scripts
RUN echo "PassEnv DB_HOST DB_USER DB_PASSWORD DB_NAME DB_PORT" >> /etc/apache2/apache2.conf

# Copy all local project files into the Apache server directory (Double check the spaces here!)
COPY . /var/www/html/

# Expose port 80 for web traffic
EXPOSE 80