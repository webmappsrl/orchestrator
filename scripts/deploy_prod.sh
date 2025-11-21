#!/bin/bash
set -e

echo "Production deployment started ..."

php artisan down

composer install
composer dump-autoload


# TODO: Uncomment when api.favorite issue will be resolved

php artisan migrate --force

# Clear the old cache
php artisan clear-compiled

# Clear and cache config
php artisan config:clear

# Clear route cache to ensure new routes are registered
php artisan route:clear

php artisan optimize

# Create DomPDF directories with correct permissions (for PDF generation)
mkdir -p storage/app/dompdf/fonts storage/app/dompdf/tmp
chmod -R 755 storage/app/dompdf

#initializes null scores for the customers (one time needed for score sorting)
php artisan orchestrator:initialize-scores

# gracefully terminate laravel horizon
php artisan horizon:terminate

php artisan up

echo "Deployment finished!"

