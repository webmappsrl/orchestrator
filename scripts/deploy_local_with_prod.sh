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

# Create DomPDF directories with correct permissions (for PDF generation)
mkdir -p storage/app/dompdf/fonts storage/app/dompdf/tmp
chmod -R 755 storage/app/dompdf

# Create activity reports directory for PDF storage
mkdir -p storage/app/public/activity-reports
chmod -R 755 storage/app/public/activity-reports

# Create PDF logo directory if it doesn't exist
mkdir -p storage/app/pdf-logo
chmod -R 755 storage/app/pdf-logo

# Create symbolic link for storage if it doesn't exist
if [ ! -L public/storage ] && [ ! -d public/storage ]; then
    php artisan storage:link
fi

# Verify and restart Horizon
echo "Verifying Horizon configuration and status..."

# Gracefully terminate Horizon if running
if php artisan horizon:status 2>/dev/null | grep -q 'running'; then
    echo "Terminating Horizon gracefully..."
    php artisan horizon:terminate
    sleep 3
fi

# Start Horizon in background
echo "Starting Horizon..."
nohup php artisan horizon > /dev/null 2>&1 &
sleep 3

# Verify Horizon is running
if php artisan horizon:status 2>/dev/null | grep -q 'running'; then
    echo "✓ Horizon is running successfully"
    
    # Verify Horizon can see the queue connection
    QUEUE_CONNECTION=$(php artisan tinker --execute="echo config('queue.default');" 2>/dev/null | grep -v "^>>>" | grep -v "^..." | tail -n 1 | tr -d '[:space:]')
    HORIZON_CONNECTION=$(php artisan tinker --execute="echo config('horizon.defaults.supervisor-1.connection');" 2>/dev/null | grep -v "^>>>" | grep -v "^..." | tail -n 1 | tr -d '[:space:]')
    
    if [ -n "$QUEUE_CONNECTION" ] && [ -n "$HORIZON_CONNECTION" ]; then
        echo "  Queue connection: $QUEUE_CONNECTION"
        echo "  Horizon connection: $HORIZON_CONNECTION"
        
        if [ "$QUEUE_CONNECTION" = "$HORIZON_CONNECTION" ]; then
            echo "✓ Horizon connection matches queue connection"
        else
            echo "⚠ WARNING: Horizon connection ($HORIZON_CONNECTION) does not match queue connection ($QUEUE_CONNECTION)"
            echo "  Jobs may not be processed correctly. Please check config/horizon.php"
        fi
    else
        echo "  ⚠ Could not verify queue connection configuration"
    fi
else
    echo "✗ ERROR: Horizon failed to start. Please check the logs."
    exit 1
fi

echo "Deployment finished!"