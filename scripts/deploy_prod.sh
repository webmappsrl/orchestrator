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
HORIZON_LOG="storage/logs/horizon.log"

# Previous deploys as root can leave horizon.log unwritable for www-data
if [ -e "$HORIZON_LOG" ] && [ ! -w "$HORIZON_LOG" ]; then
  rm -f "$HORIZON_LOG"
fi
touch "$HORIZON_LOG"

if [ "$(id -u)" = "0" ]; then
  chown www-data:www-data "$HORIZON_LOG"
  nohup runuser -u www-data -- php artisan horizon >> "$HORIZON_LOG" 2>&1 &
else
  nohup php artisan horizon >> "$HORIZON_LOG" 2>&1 &
fi

echo "Horizon started (PID $!)."

php artisan up

echo "Deployment finished!"

