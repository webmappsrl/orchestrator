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

php artisan optimize:clear

# gracefully terminate laravel horizon
php artisan horizon:terminate

php artisan up

echo "Deployment finished!"

