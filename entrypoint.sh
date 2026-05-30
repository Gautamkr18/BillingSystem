#!/bin/bash
set -e

# Ensure database and uploads folders exist
mkdir -p /var/www/html/uploads /var/www/html/database

# Restore database if using SQLite and replica exists
DB_PATH="/var/www/html/database/billing_system.sqlite"
echo "Attempting to restore database from Litestream if replica exists..."
litestream restore -v -if-replica-exists -config /etc/litestream.yml "$DB_PATH" || echo "Warning: Litestream restore failed or skipped (non-fatal, continuing...)"

# Run database migrations BEFORE fixing permissions
echo "Running database migrations..."
php /var/www/html/migrate.php > /dev/null 2>&1 || echo "Warning: Migration encountered an error (non-fatal, continuing...)"

# Fix permissions so Apache (www-data) can write to SQLite and uploads
echo "Fixing permissions..."
chown -R www-data:www-data /var/www/html/uploads /var/www/html/database
chmod -R 775 /var/www/html/uploads /var/www/html/database

# Start Litestream and Apache
echo "Starting Litestream and Apache..."
exec litestream replicate -config /etc/litestream.yml -exec "apache2-foreground"
