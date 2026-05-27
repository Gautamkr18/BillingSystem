FROM php:8.2-apache

# Install and enable the mysqli extension required for MySQL connections
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy the entire project directory into the Apache document root
COPY . /var/www/html/

# Set the working directory
WORKDIR /var/www/html

# Create the uploads directory if it doesn't exist and grant write permissions to Apache's www-data user
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
