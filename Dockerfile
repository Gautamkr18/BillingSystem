FROM php:8.2-apache

# Copy the entire project directory into the Apache document root
COPY . /var/www/html/

# Set the working directory
WORKDIR /var/www/html

# Create uploads and database folders and grant full write permissions to Apache's www-data user
RUN mkdir -p /var/www/html/uploads /var/www/html/database && chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
