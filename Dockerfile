FROM php:8.2-apache

# Enable Apache mod_rewrite (required for .htaccess redirect rules)
RUN a2enmod rewrite

# Install system dependency first, then PHP sqlite3 extension
RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Set AllowOverride All so .htaccess rules are respected
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Copy custom Apache virtual host config (enables AllowOverride All and mod_rewrite for .htaccess)
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy the entire project directory into the Apache document root
COPY . /var/www/html/

# Set the working directory
WORKDIR /var/www/html

# Copy the entrypoint script and make it executable
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create uploads and database folders and grant initial write permissions
RUN mkdir -p /var/www/html/uploads /var/www/html/database && chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Configure entrypoint to run permissions fix on persistent disk mounts before starting Apache
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
