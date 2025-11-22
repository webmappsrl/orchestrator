#!/bin/bash
set -e

echo "Deployment started ..."

composer install
composer dump-autoload

# Clear and cache routes
# php artisan route:clear
# php artisan route:cache

# Clear and cache config
php artisan config:cache
php artisan config:clear

# Clear the old cache
php artisan clear-compiled

# TODO: Uncomment when api.favorite issue will be resolved
# php artisan optimize

php artisan db:restore
php artisan migrate

# Create DomPDF directories with correct permissions (for PDF generation)
mkdir -p storage/app/dompdf/fonts storage/app/dompdf/tmp
chmod -R 755 storage/app/dompdf

# Create activity reports directory for PDF storage
mkdir -p storage/app/public/activity-reports
chmod -R 755 storage/app/public/activity-reports

# Create PDF logo directory if it doesn't exist
mkdir -p storage/app/pdf-logo
chmod -R 755 storage/app/pdf-logo

# Create symbolic link for storage if it doesn't exist
if [ ! -L public/storage ] && [ ! -d public/storage ]; then
    php artisan storage:link
fi

echo "Deployment finished!"