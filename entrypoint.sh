#!/bin/bash
set -e

# Fix permissions on writable directories
echo "Fixing permissions..."
mkdir -p /var/www/html/uploads /var/www/html/database
chown -R www-data:www-data /var/www/html/uploads /var/www/html/database
chmod -R 775 /var/www/html/uploads /var/www/html/database

# Run database migrations (creates/upgrades schema in PostgreSQL or SQLite)
echo "Running database migrations..."
php /var/www/html/migrate.php > /dev/null 2>&1 || echo "Warning: Migration encountered an error (non-fatal, continuing...)"

# Start Apache
echo "Starting Apache..."
exec apache2-foreground
