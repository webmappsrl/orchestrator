# Test Database Isolation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Separare il database di sviluppo (`orchestrator`) da quello di test (`orchestrator_test`) così che `php artisan test` non distrugga mai il dump di produzione caricato per lo sviluppo manuale.

**Architecture:** Si crea il database `orchestrator_test` nel container PostgreSQL esistente, si aggiunge uno script di init Docker che lo crea automaticamente al primo avvio, e si punta `phpunit.xml` a quel database. Nessuna modifica al codice applicativo, nessuna modifica al `.env` principale.

**Tech Stack:** PostgreSQL 17 (Docker), Laravel 10, PHPUnit 10, `phpunit.xml`

---

## File Structure

- Modify: `phpunit.xml` — aggiungere `DB_DATABASE=orchestrator_test`
- Create: `docker/configs/postgres/init/01-create-test-db.sh` — script che crea `orchestrator_test` all'avvio del container
- Modify: `docker-compose.yml` — montare la cartella `init/` nel container PostgreSQL

---

### Task 1: Creare il database `orchestrator_test` nel container PostgreSQL

**Files:**
- Create: `docker/configs/postgres/init/01-create-test-db.sh`
- Modify: `docker-compose.yml`

PostgreSQL esegue automaticamente gli script `.sh` e `.sql` presenti in `/docker-entrypoint-initdb.d/` al **primo avvio** (quando il volume dati è vuoto). Dobbiamo montare uno script che crei il database di test.

**IMPORTANTE:** Se il container è già avviato con volume dati esistente, lo script init non gira. In quel caso creare il DB manualmente (vedi Step 4).

- [ ] **Step 1: Crea la directory e lo script init**

Crea il file `docker/configs/postgres/init/01-create-test-db.sh`:

```bash
#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE orchestrator_test'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'orchestrator_test')\gexec

    GRANT ALL PRIVILEGES ON DATABASE orchestrator_test TO $POSTGRES_USER;
EOSQL
```

- [ ] **Step 2: Rendi lo script eseguibile**

```bash
chmod +x docker/configs/postgres/init/01-create-test-db.sh
```

- [ ] **Step 3: Monta la directory init nel container — modifica `docker-compose.yml`**

Trova il servizio `db:` in `docker-compose.yml`. Cerca la sezione `volumes:` dentro quel servizio:

```yaml
volumes:
    - "./docker/volumes/postgresql/data:/var/lib/postgresql/data"
```

Aggiungici il mount dello script init:

```yaml
volumes:
    - "./docker/volumes/postgresql/data:/var/lib/postgresql/data"
    - "./docker/configs/postgres/init:/docker-entrypoint-initdb.d"
```

- [ ] **Step 4: Crea manualmente il DB di test nel container già avviato**

Lo script init gira solo al primo avvio. Dato che il container è già in esecuzione, crea il DB adesso:

```bash
docker exec -it postgres_orchestrator psql -U orchestrator -d orchestrator -c "CREATE DATABASE orchestrator_test;"
docker exec -it postgres_orchestrator psql -U orchestrator -d orchestrator -c "GRANT ALL PRIVILEGES ON DATABASE orchestrator_test TO orchestrator;"
```

Expected: `CREATE DATABASE` e `GRANT`

- [ ] **Step 5: Verifica che il DB esiste**

```bash
docker exec -it postgres_orchestrator psql -U orchestrator -d orchestrator -c "\l" | grep orchestrator
```

Expected: due righe — `orchestrator` e `orchestrator_test`

---

### Task 2: Puntare PHPUnit al database di test

**Files:**
- Modify: `phpunit.xml`

- [ ] **Step 1: Aggiungi le variabili DB in `phpunit.xml`**

Apri `phpunit.xml`. Trova il blocco `<php>` (circa riga 14). Aggiungi le due env dopo `<env name="APP_ENV" value="testing"/>`:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_DATABASE" value="orchestrator_test"/>
    <env name="DB_CONNECTION" value="pgsql"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_DRIVER" value="array"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="TELESCOPE_ENABLED" value="false"/>
</php>
```

`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` vengono ereditati dal `.env` — non serve duplicarli.

- [ ] **Step 2: Verifica che i test usano il DB corretto**

Lancia i test e controlla subito che il DB `orchestrator` rimane intatto:

```bash
docker exec php81_orchestrator php artisan test 2>&1 | tail -5
```

Expected: `Tests: 120 passed`

- [ ] **Step 3: Verifica che il dump di sviluppo è intatto**

```bash
docker exec -it postgres_orchestrator psql -U orchestrator -d orchestrator -c "SELECT COUNT(*) FROM users;"
```

Expected: il numero di utenti del tuo dump, **non zero e non cambiato**

---

### Task 3: Assicurare che `orchestrator_test` abbia le extensions necessarie

**Files:**
- Nessun file — solo comandi SQL

Il database `orchestrator` usa PostGIS e pgvector. `orchestrator_test` è nudo — le migrations falliranno se quelle extensions mancano.

- [ ] **Step 1: Verifica le extensions usate dal progetto**

```bash
docker exec php81_orchestrator php artisan test --filter="AlignTagsCommandTest" 2>&1 | tail -5
```

Se passa già, le extensions sono ok. Se fallisce con errori tipo `extension "postgis" does not exist`, vai allo Step 2.

- [ ] **Step 2 (solo se Step 1 fallisce): Installa le extensions su `orchestrator_test`**

```bash
docker exec -it postgres_orchestrator psql -U orchestrator -d orchestrator_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"
docker exec -it postgres_orchestrator psql -U orchestrator -d orchestrator_test -c "CREATE EXTENSION IF NOT EXISTS vector;"
```

Expected: `CREATE EXTENSION`

- [ ] **Step 3: Aggiorna lo script init per includere le extensions (per futuri avvii puliti)**

Modifica `docker/configs/postgres/init/01-create-test-db.sh` per includere le extensions:

```bash
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
```

- [ ] **Step 4: Verifica finale — tutti i test passano**

```bash
docker exec php81_orchestrator php artisan test 2>&1 | tail -3
```

Expected: `Tests: 120 passed (333 assertions)`
