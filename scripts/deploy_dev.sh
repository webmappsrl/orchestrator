#!/bin/bash
set -e

echo "Deployment started ..."

# Enter maintenance mode or return true
# if already is in maintenance mode
(php artisan down) || true


# Install composer dependencies
composer install  --no-interaction --prefer-dist --optimize-autoloader
# php artisan nova:install

# TODO
# php artisan db:wipe
# php artisan db:restore
php artisan migrate

# Clear the old cache
php artisan clear-compiled

composer dump-autoload
php artisan optimize

# Compile npm assets
# npm run prod
# cd vendor/laravel/nova && npm install
# Exit maintenance mode
php artisan up

echo "Deployment finished!"
