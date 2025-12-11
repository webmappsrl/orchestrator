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
chmod -R 755 storage/app/dompdf || true

# Create activity reports directory for PDF storage
mkdir -p storage/app/public/activity-reports
chmod -R 755 storage/app/public/activity-reports || true

# Create documentations directory for PDF storage
mkdir -p storage/app/public/documentations
chmod -R 755 storage/app/public/documentations || true

# Create PDF logo directory if it doesn't exist
mkdir -p storage/app/pdf-logo
chmod -R 755 storage/app/pdf-logo || true

# Create symbolic link for storage if it doesn't exist
if [ ! -L public/storage ] && [ ! -d public/storage ]; then
    php artisan storage:link
fi

#initializes null scores for the customers (one time needed for score sorting)
php artisan orchestrator:initialize-scores

# gracefully terminate laravel horizon
php artisan horizon:terminate

# Wait for Horizon to terminate gracefully
echo "Waiting for Horizon to terminate gracefully..."
sleep 5

php artisan up

# Restart Horizon
# If Horizon is managed by supervisor/systemd it will restart automatically
# Otherwise, restart it manually
echo "Restarting Horizon..."
# Check if Horizon is managed externally, otherwise start it in background
if ! php artisan horizon:status 2>/dev/null | grep -q 'running'; then
    # If not managed externally, start Horizon in background
    nohup php artisan horizon > /dev/null 2>&1 &
    echo "Horizon restarted in background."
else
    echo "Horizon is managed externally (supervisor/systemd), it will restart automatically."
fi

# Wait for Horizon to be fully ready
echo "Waiting for Horizon to be ready..."
sleep 5

# Update story dates from logs
echo "Updating story dates (released_at and done_at)..."
php artisan story:calculate-dates

# Generate activity reports for previous month (default)
echo "Generating activity reports for previous month..."
php artisan orchestrator:activity-report-generate

echo "Deployment finished!"

