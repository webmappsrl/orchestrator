#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# Upgrade PostgreSQL 14 → 17 + pgvector
# Eseguire dalla root del progetto: bash scripts/upgrade_postgres.sh
#
# Resumabile: se il dump esiste già e il container DB è assente,
# lo script salta la fase di dump e riparte dalla build.
# ---------------------------------------------------------------------------

DATA_DIR="docker/volumes/postgresql/data"
DATA_BACKUP_DIR="docker/volumes/postgresql/data_backup_pg14"
ROLLBACK_DONE=0

rollback() {
  if [ "$ROLLBACK_DONE" -eq 1 ]; then return; fi
  ROLLBACK_DONE=1
  echo ""
  echo "============================================"
  echo " ROLLBACK AUTOMATICO IN CORSO"
  echo "============================================"
  if [ ! -d "$DATA_BACKUP_DIR" ] || [ -z "$(ls -A "$DATA_BACKUP_DIR" 2>/dev/null)" ]; then
    echo "ROLLBACK FALLITO: backup del volume non trovato in $DATA_BACKUP_DIR"
    echo "Ripristino manuale: recupera il dump in ./${BACKUP_FILE:-backup_pre_pg17_*.dump}"
    return
  fi
  docker compose stop db 2>/dev/null || true
  docker compose rm -f db 2>/dev/null || true
  rm -rf "$DATA_DIR"
  cp -r "$DATA_BACKUP_DIR" "$DATA_DIR"
  # Ripristina il docker-compose.yml originale (salvato prima dell'upgrade)
  if [ -f docker-compose.yml.pre-upgrade ]; then
    cp docker-compose.yml.pre-upgrade docker-compose.yml
  fi
  docker compose up -d db
  echo "      Attendo riavvio PG14..."
  for i in $(seq 1 30); do
    if docker exec "$CONTAINER_DB" pg_isready -U "$DB_USERNAME" -d "$DB_DATABASE" > /dev/null 2>&1; then
      echo "      PG14 ripristinato e pronto."
      break
    fi
    sleep 1
  done
  echo ""
  echo "  Rollback completato: PostgreSQL 14 ripristinato dal backup del volume."
  echo "  L'applicazione è tornata operativa."
  echo ""
  echo "  Per ritentare l'upgrade: bash scripts/upgrade_postgres.sh"
}
trap 'rollback' ERR

if [ ! -f .env ]; then
  echo "ERRORE: file .env non trovato nella directory corrente."
  exit 1
fi

DB_USERNAME=$(grep -E "^DB_USERNAME=" .env | cut -d= -f2)
DB_DATABASE=$(grep -E "^DB_DATABASE=" .env | cut -d= -f2)
APP_NAME=$(grep -E "^APP_NAME=" .env | cut -d= -f2 | tr '[:upper:]' '[:lower:]' | tr ' ' '_')
CONTAINER_DB="postgres_${APP_NAME}"
CONTAINER_PHP="php81_${APP_NAME}"

# Rileva architettura: aggiunge --platform solo se necessario (Mac Apple Silicon)
ARCH=$(uname -m)
if [ "$ARCH" = "arm64" ]; then
  export DOCKER_DEFAULT_PLATFORM=linux/amd64
  echo "      (Apple Silicon rilevato: forzato linux/amd64)"
fi

echo "============================================"
echo " PostgreSQL 14 → 17 + pgvector Upgrade"
echo "============================================"
echo "  DB Container : $CONTAINER_DB"
echo "  PHP Container: $CONTAINER_PHP"
echo "  Database     : $DB_DATABASE"
echo ""

# ---------------------------------------------------------------------------
# FASE 1: Dump del database (saltata se container assente e dump già esiste)
# ---------------------------------------------------------------------------
EXISTING_DUMP=$(ls backup_pre_pg17_*.dump 2>/dev/null | head -1 || true)
DB_RUNNING=$(docker ps --filter "name=${CONTAINER_DB}" --filter "status=running" -q)

if [ -n "$DB_RUNNING" ]; then
  BACKUP_FILE="backup_pre_pg17_$(date +%Y%m%d_%H%M%S).dump"
  echo "[1/7] Dump del database esistente..."
  docker exec "$CONTAINER_DB" pg_dump \
    -U "$DB_USERNAME" \
    -d "$DB_DATABASE" \
    --format=custom \
    --file="/tmp/${BACKUP_FILE}"
  docker cp "${CONTAINER_DB}:/tmp/${BACKUP_FILE}" "./${BACKUP_FILE}"
  echo "      Dump salvato in: ./${BACKUP_FILE} ($(du -sh "./${BACKUP_FILE}" | cut -f1))"
elif [ -n "$EXISTING_DUMP" ]; then
  BACKUP_FILE="$EXISTING_DUMP"
  echo "[1/7] Container DB non attivo, riuso dump esistente: $BACKUP_FILE"
else
  echo "ERRORE: il container DB non è attivo e non esiste nessun dump precedente."
  echo "        Avvia il container DB e rilancia lo script."
  exit 1
fi

# ---------------------------------------------------------------------------
# FASE 2: Stop container DB
# ---------------------------------------------------------------------------
echo "[2/7] Stop container DB..."
docker compose stop db 2>/dev/null || true
docker compose rm -f db 2>/dev/null || true

# ---------------------------------------------------------------------------
# FASE 3: Backup del volume dati PG14
# ---------------------------------------------------------------------------
echo "[3/7] Backup volume dati PG14..."
if [ -d "$DATA_BACKUP_DIR" ]; then
  echo "      Backup precedente trovato, sovrascritto."
  rm -rf "$DATA_BACKUP_DIR"
fi
if [ -d "$DATA_DIR" ] && [ -n "$(ls -A "$DATA_DIR" 2>/dev/null)" ]; then
  cp -r "$DATA_DIR" "$DATA_BACKUP_DIR"
  echo "      Backup volume salvato in: $DATA_BACKUP_DIR"
else
  echo "      Volume dati già vuoto, salto backup."
fi

# ---------------------------------------------------------------------------
# FASE 4: Rimozione dati PG14 e build nuova immagine
# ---------------------------------------------------------------------------
# Salva docker-compose.yml originale per il rollback
cp docker-compose.yml docker-compose.yml.pre-upgrade

echo "[4/7] Rimozione dati PG14 e build immagine PG17+pgvector..."
rm -rf "$DATA_DIR"
mkdir -p "$DATA_DIR"

docker compose build db

# ---------------------------------------------------------------------------
# FASE 5: Avvio nuovo container PG17
# ---------------------------------------------------------------------------
echo "[5/7] Avvio PostgreSQL 17..."
docker compose up -d db

echo "      Attendo che il DB sia pronto e stabile (inclusi init scripts PostGIS)..."
for i in $(seq 1 60); do
  if docker exec "$CONTAINER_DB" pg_isready -U "$DB_USERNAME" -d "$DB_DATABASE" > /dev/null 2>&1; then
    # Verifica che sia davvero interrogabile, non solo in ascolto
    if docker exec "$CONTAINER_DB" psql -U "$DB_USERNAME" -d "$DB_DATABASE" \
        -c "SELECT 1;" > /dev/null 2>&1; then
      echo "      DB pronto dopo ${i}s."
      break
    fi
  fi
  if [ "$i" -eq 60 ]; then
    echo "ERRORE: il DB non è diventato pronto in 60 secondi."
    docker logs "$CONTAINER_DB" | tail -20
    exit 1
  fi
  sleep 1
done

# ---------------------------------------------------------------------------
# FASE 6: Restore del dump
# ---------------------------------------------------------------------------
echo "[6/7] Restore del database..."

# L'immagine postgis/postgis installa già PostGIS all'avvio del container.
# Eseguiamo il restore saltando le EXTENSION (già presenti nella versione corretta)
# per evitare conflitti tra PostGIS 3.3 del dump e PostGIS 3.5 installato.
docker cp "./${BACKUP_FILE}" "${CONTAINER_DB}:/tmp/restore.dump"

docker exec "$CONTAINER_DB" pg_restore --list /tmp/restore.dump \
  | grep -v " EXTENSION " \
  | docker exec -i "$CONTAINER_DB" tee /tmp/restore_list.txt > /dev/null

docker exec "$CONTAINER_DB" pg_restore \
  -U "$DB_USERNAME" \
  -d "$DB_DATABASE" \
  --no-owner \
  --no-privileges \
  --use-list=/tmp/restore_list.txt \
  /tmp/restore.dump > /dev/null 2>&1 || true

echo "      Restore completato."

# Il DB può riavviarsi dopo il restore: aspetta che sia stabile prima di interrogarlo
echo "      Attendo che il DB sia stabile post-restore..."
for i in $(seq 1 60); do
  if docker exec "$CONTAINER_DB" psql -U "$DB_USERNAME" -d "$DB_DATABASE" \
      -c "SELECT 1;" > /dev/null 2>&1; then
    echo "      DB stabile dopo ${i}s."
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "ERRORE: il DB non è tornato disponibile dopo il restore."
    docker logs "$CONTAINER_DB" | tail -20
    exit 1
  fi
  sleep 1
done
# Pausa extra: PostGIS può triggherare un secondo riavvio dopo il restore
sleep 5

# Verifica che il restore abbia ripristinato i dati
STORIES_COUNT=$(docker exec "$CONTAINER_DB" psql -U "$DB_USERNAME" -d "$DB_DATABASE" \
  -t -c "SELECT COUNT(*) FROM stories;" 2>/dev/null | xargs || echo "0")

if [ -z "$STORIES_COUNT" ] || [ "$STORIES_COUNT" -eq 0 ]; then
  echo "ERRORE: la tabella stories è vuota o assente dopo il restore."
  echo "        Verifica manuale:"
  echo "        docker exec $CONTAINER_DB psql -U $DB_USERNAME -d $DB_DATABASE -c '\\dt'"
  exit 1
fi
echo "      Restore verificato: $STORIES_COUNT stories ripristinate."

# ---------------------------------------------------------------------------
# FASE 7: Verifica estensioni + migration Laravel
# ---------------------------------------------------------------------------
echo "[7/7] Verifica estensioni e migration Laravel..."

PG_VERSION=$(docker exec "$CONTAINER_DB" psql -U "$DB_USERNAME" -d "$DB_DATABASE" \
  -t -c "SELECT version();" | head -1 | xargs)
echo "      PostgreSQL: $PG_VERSION"

POSTGIS_VERSION=$(docker exec "$CONTAINER_DB" psql -U "$DB_USERNAME" -d "$DB_DATABASE" \
  -t -c "SELECT PostGIS_Version();" | xargs)
echo "      PostGIS: $POSTGIS_VERSION"

VECTOR_AVAILABLE=$(docker exec "$CONTAINER_DB" psql -U "$DB_USERNAME" -d "$DB_DATABASE" \
  -t -c "SELECT count(*) FROM pg_available_extensions WHERE name = 'vector';" | xargs)

if [ "$VECTOR_AVAILABLE" -eq 0 ]; then
  echo "ERRORE: pgvector non risulta disponibile nell'installazione PostgreSQL."
  exit 1
fi
echo "      pgvector: disponibile"

docker exec "$CONTAINER_PHP" php artisan migrate --force
echo "      Migration Laravel completate."

# Assicura che l'estensione vector sia attiva: potrebbe essere già registrata
# nella tabella migrations (ripristinata dal dump) ma non installata nel DB.
docker exec "$CONTAINER_DB" psql -U "$DB_USERNAME" -d "$DB_DATABASE" \
  -c "CREATE EXTENSION IF NOT EXISTS vector;" > /dev/null 2>&1

VECTOR_ENABLED=$(docker exec "$CONTAINER_DB" psql -U "$DB_USERNAME" -d "$DB_DATABASE" \
  -t -c "SELECT count(*) FROM pg_extension WHERE extname = 'vector';" | xargs)

if [ "$VECTOR_ENABLED" -eq 0 ]; then
  echo "ERRORE: estensione vector non abilitata nel DB."
  exit 1
fi

echo ""
echo "============================================"
echo " Upgrade completato con successo!"
echo "============================================"
echo ""
echo "  PostgreSQL : 17"
echo "  PostGIS    : $POSTGIS_VERSION"
echo "  pgvector   : 0.8.2"
echo ""
echo "  Backup dump   : ./${BACKUP_FILE}"
echo "  Backup volume : ./${DATA_BACKUP_DIR}"
echo ""
echo "  Quando sei sicuro che tutto funzioni:"
echo "    rm -rf ${DATA_BACKUP_DIR}"
