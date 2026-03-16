#!/bin/bash
# Script di migrazione PostgreSQL 14 → 15 con PostGIS + pgvector
# Usare con cautela! Leggere MIGRAZIONE_PGVECTOR.md prima di eseguire.

set -e  # Exit on error

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Migrazione PostgreSQL 14 → 15 con PostGIS + pgvector ===${NC}\n"

# Caricare variabili d'ambiente
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
    echo -e "${GREEN}✓ Variabili d'ambiente caricate${NC}"
else
    echo -e "${RED}✗ File .env non trovato!${NC}"
    exit 1
fi

# Verificare variabili essenziali
if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ] || [ -z "$DOCKER_PSQL_PORT" ]; then
    echo -e "${RED}✗ Variabili DB_DATABASE, DB_USERNAME o DOCKER_PSQL_PORT non trovate nel .env${NC}"
    exit 1
fi

echo -e "\n${YELLOW}Variabili caricate:${NC}"
echo "  DB_DATABASE: $DB_DATABASE"
echo "  DB_USERNAME: $DB_USERNAME"
echo "  DOCKER_PSQL_PORT: $DOCKER_PSQL_PORT"
echo "  APP_NAME: ${APP_NAME:-orchestrator}"

read -p "Continuare con la migrazione? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "Migrazione annullata."
    exit 0
fi

# 1. Backup
echo -e "\n${YELLOW}[1/7] Creazione backup...${NC}"
DUMP_FILE="orchestrator_$(date +%Y%m%d_%H%M%S).dump"
PGPASSWORD=$DB_PASSWORD pg_dump -h localhost -p $DOCKER_PSQL_PORT -U $DB_USERNAME -Fc $DB_DATABASE > $DUMP_FILE

if [ -f "$DUMP_FILE" ]; then
    DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
    echo -e "${GREEN}✓ Backup creato: $DUMP_FILE (${DUMP_SIZE})${NC}"
else
    echo -e "${RED}✗ Errore nella creazione del backup!${NC}"
    exit 1
fi

# 2. Fermare container
echo -e "\n${YELLOW}[2/7] Fermando container...${NC}"
docker compose down
echo -e "${GREEN}✓ Container fermati${NC}"

# 3. Rimuovere volume (SOLO LOCALE)
echo -e "\n${YELLOW}[3/7] Rimozione volume PostgreSQL...${NC}"
read -p "⚠️  Questo cancellerà il volume locale. Continuare? (yes/no): " confirm_volume
if [ "$confirm_volume" != "yes" ]; then
    echo "Operazione annullata. Volume non rimosso."
    exit 0
fi

if [ -d "./docker/volumes/postgresql/data" ]; then
    rm -rf ./docker/volumes/postgresql/data
    echo -e "${GREEN}✓ Volume rimosso${NC}"
else
    echo -e "${YELLOW}⚠ Volume non trovato (potrebbe essere già stato rimosso)${NC}"
fi

# 4. Verificare docker-compose.yml
echo -e "\n${YELLOW}[4/7] Verifica docker-compose.yml...${NC}"
if grep -q "garapadev/postgres-postgis-pgvector:15-stable" docker-compose.yml; then
    echo -e "${GREEN}✓ Immagine corretta nel docker-compose.yml${NC}"
else
    echo -e "${RED}✗ Immagine non aggiornata nel docker-compose.yml!${NC}"
    echo "  Atteso: garapadev/postgres-postgis-pgvector:15-stable"
    exit 1
fi

# 5. Riavviare container
echo -e "\n${YELLOW}[5/7] Riavvio container...${NC}"
docker compose up -d

echo "Attendere che PostgreSQL sia pronto..."
sleep 5
for i in {1..30}; do
    if docker compose logs db 2>&1 | grep -q "database system is ready"; then
        echo -e "${GREEN}✓ PostgreSQL pronto${NC}"
        break
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}✗ Timeout: PostgreSQL non è diventato pronto${NC}"
        docker compose logs db
        exit 1
    fi
    sleep 2
done

# 6. Restore
echo -e "\n${YELLOW}[6/7] Restore database...${NC}"
PGPASSWORD=$DB_PASSWORD pg_restore -h localhost -p $DOCKER_PSQL_PORT -U $DB_USERNAME -d $DB_DATABASE -c $DUMP_FILE
echo -e "${GREEN}✓ Database ripristinato${NC}"

# 7. Abilitare pgvector
echo -e "\n${YELLOW}[7/7] Abilitazione estensione pgvector...${NC}"
PGPASSWORD=$DB_PASSWORD psql -h localhost -p $DOCKER_PSQL_PORT -U $DB_USERNAME -d $DB_DATABASE -c "CREATE EXTENSION IF NOT EXISTS vector;" > /dev/null
echo -e "${GREEN}✓ Estensione pgvector abilitata${NC}"

# Verifiche finali
echo -e "\n${YELLOW}=== Verifiche Finali ===${NC}\n"

echo "Verifica pgvector:"
PGPASSWORD=$DB_PASSWORD psql -h localhost -p $DOCKER_PSQL_PORT -U $DB_USERNAME -d $DB_DATABASE -c "SELECT extname, extversion FROM pg_extension WHERE extname = 'vector';"

echo -e "\nVerifica PostGIS:"
PGPASSWORD=$DB_PASSWORD psql -h localhost -p $DOCKER_PSQL_PORT -U $DB_USERNAME -d $DB_DATABASE -c "SELECT PostGIS_Version();"

echo -e "\nVerifica versione PostgreSQL:"
PGPASSWORD=$DB_PASSWORD psql -h localhost -p $DOCKER_PSQL_PORT -U $DB_USERNAME -d $DB_DATABASE -c "SELECT version();"

echo -e "\n${GREEN}=== Migrazione completata con successo! ===${NC}"
echo -e "Backup salvato in: ${YELLOW}$DUMP_FILE${NC}"
echo -e "\nProssimi passi:"
echo "  1. Verificare che l'applicazione funzioni correttamente"
echo "  2. Testare funzionalità che usano PostGIS"
echo "  3. Installare pacchetti Laravel: composer require laravel/ai pgvector/pgvector"
