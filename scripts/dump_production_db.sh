#!/bin/bash
set -e

echo "Starting database dump from production..."

# Load environment variables
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Get database credentials from environment
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-orchestrator}"
DB_USER="${DB_USERNAME:-orchestrator}"
DB_PASS="${DB_PASSWORD:-orchestrator}"

# Create backup directory
BACKUP_DIR="storage/app/backups"
mkdir -p "$BACKUP_DIR"

# Generate timestamped filename
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="$BACKUP_DIR/production_dump_${TIMESTAMP}.sql.gz"

echo "Dumping database $DB_NAME from $DB_HOST:$DB_PORT..."

# Check if we're running in Docker
if docker ps | grep -q "postgres_orchestrator"; then
    echo "Running inside Docker environment..."
    # Dump from Docker container
    docker exec postgres_orchestrator pg_dump -U "$DB_USER" -d "$DB_NAME" | gzip > "$DUMP_FILE"
else
    echo "Running on host machine..."
    # Dump directly from host
    PGPASSWORD="$DB_PASS" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" | gzip > "$DUMP_FILE"
fi

echo "Dump completed successfully: $DUMP_FILE"
echo "File size: $(du -h "$DUMP_FILE" | cut -f1)"

# Also create a symlink to the latest dump for easy access
LATEST_DUMP="$BACKUP_DIR/latest_production_dump.sql.gz"
rm -f "$LATEST_DUMP"
ln -s "$(basename "$DUMP_FILE")" "$LATEST_DUMP"

echo "Latest dump link created: $LATEST_DUMP"
