#!/bin/bash
set -e

# Create directories if they don't exist
mkdir -p /var/www/html/db-data /var/www/html/uploads

# Run database migrations automatically at startup so the database is populated on boot
echo "Running database migrations..."
php /var/www/html/database/migrate.php

# Fix permissions on directories (ensures the SQLite DB and uploads are fully writeable by Apache)
echo "Fixing permissions on persistent volume directories..."
chown -R www-data:www-data /var/www/html/db-data
chown -R www-data:www-data /var/www/html/uploads

# Execute default container command (Apache)
echo "Starting Apache..."
exec apache2-foreground
