FROM php:8.2-apache

# Enable Apache mod_rewrite (required for .htaccess redirect rules)
RUN a2enmod rewrite

# Install system dependencies + PHP extensions (SQLite for local dev, PostgreSQL for production)
RUN apt-get update && apt-get install -y libsqlite3-dev libpq-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_pgsql

# Set AllowOverride All so .htaccess rules are respected
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Copy custom Apache virtual host config
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Install Litestream
ADD https://github.com/benbjohnson/litestream/releases/download/v0.3.13/litestream-v0.3.13-linux-amd64.tar.gz /tmp/litestream.tar.gz
RUN tar -C /usr/local/bin -xzf /tmp/litestream.tar.gz

# Copy the entire project directory into the Apache document root
COPY . /var/www/html/

# Copy Litestream configuration
COPY litestream.yml /etc/litestream.yml

# Set the working directory
WORKDIR /var/www/html

# Copy the entrypoint script and make it executable
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create uploads and database folders and grant initial write permissions
RUN mkdir -p /var/www/html/uploads /var/www/html/database && chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
