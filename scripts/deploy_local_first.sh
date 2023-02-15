#!/bin/bash
set -e
composer install
php artisan key:generate
php artisan optimize
php artisan migrate
