#!/bin/bash
set -e

echo "Production deployment started ..."

php artisan down

composer install
composer dump-autoload

# Clear and cache config
php artisan config:cache
php artisan config:clear

# Clear the old cache
php artisan clear-compiled

# TODO: Uncomment when api.favorite issue will be resolved
# php artisan optimize

php artisan migrate

php artisan up

echo "Deployment finished!"