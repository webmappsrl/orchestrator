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

# gracefully terminate laravel horizon and restart it
php artisan horizon:terminate || true

echo "Waiting for Horizon to stop..."
for _ in $(seq 1 30); do
  if ! php artisan horizon:status 2>&1 | grep -qi 'running'; then
    break
  fi
  sleep 2
done

echo "Starting Horizon..."
nohup php artisan horizon >> storage/logs/horizon.log 2>&1 &
echo "Horizon started (PID $!)."

php artisan up

echo "Deployment finished!"

