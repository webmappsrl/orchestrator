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

php artisan optimize

php artisan up

echo "Deployment finished!"