#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE orchestrator_test'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'orchestrator_test')\gexec

    GRANT ALL PRIVILEGES ON DATABASE orchestrator_test TO $POSTGRES_USER;
EOSQL

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "orchestrator_test" <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS postgis;
    CREATE EXTENSION IF NOT EXISTS vector;
EOSQL
