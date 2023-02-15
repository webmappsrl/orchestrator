#!/bin/bash
set -e
composer install
# composer dump-autoload
php artisan optimize
php artisan migrate:fresh --seed
php artisan serve --host 0.0.0.0
