#!/bin/bash
set -e

# Change ownership of the persistent SQLite database and uploads directories to www-data
echo "Fixing permissions on persistent volume directories..."
mkdir -p /var/www/html/database /var/www/html/uploads
chown -R www-data:www-data /var/www/html/database
chown -R www-data:www-data /var/www/html/uploads

# Execute the default container command (Apache)
echo "Starting Apache..."
exec apache2-foreground
