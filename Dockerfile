FROM php:8.2-apache

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
