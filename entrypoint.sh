#!/bin/bash
set -e

# Fix permissions on directories first (ensures the directory is writeable by Apache/PHP)
echo "Fixing permissions on persistent volume directories..."
mkdir -p /var/www/html/database /var/www/html/uploads
chown -R www-data:www-data /var/www/html/database /var/www/html/uploads
chmod -R 775 /var/www/html/database /var/www/html/uploads

# Run database migrations automatically at startup (suppress HTML output, log errors only)
echo "Running database migrations..."
php /var/www/html/migrate.php > /dev/null 2>&1 || echo "Warning: Migration encountered an error (non-fatal, continuing...)"

# Re-ensure permissions are set correctly on new database files created during migration
chown -R www-data:www-data /var/www/html/database /var/www/html/uploads
chmod -R 775 /var/www/html/database /var/www/html/uploads

# Execute default container command (Apache)
echo "Starting Apache..."
exec apache2-foreground
