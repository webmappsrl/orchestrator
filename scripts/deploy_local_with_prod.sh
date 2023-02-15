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

echo "Deployment finished!"