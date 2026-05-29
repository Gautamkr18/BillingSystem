#!/bin/bash
set -e

# Fix permissions on directories first (ensures the directory is writeable by Apache/PHP)
echo "Fixing permissions on persistent volume directories..."
mkdir -p /var/www/html/database /var/www/html/uploads
chown -R www-data:www-data /var/www/html/database /var/www/html/uploads
chmod -R 775 /var/www/html/database /var/www/html/uploads

# Run database migrations automatically at startup so the database is populated on boot
echo "Running database migrations..."
php /var/www/html/migrate.php

# Re-ensure permissions are set correctly on new database files created during migration
chown -R www-data:www-data /var/www/html/database /var/www/html/uploads
chmod -R 775 /var/www/html/database /var/www/html/uploads

# Execute default container command (Apache)
echo "Starting Apache..."
exec apache2-foreground
