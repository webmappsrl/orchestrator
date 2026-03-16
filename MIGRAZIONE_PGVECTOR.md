# Migrazione PostgreSQL 14 → 15 con PostGIS + pgvector

## 📋 Checklist Migrazione

### 🏠 Ambiente LOCALE

#### Prerequisiti
- [ ] Verificare che il container `db` sia attivo: `docker ps | grep postgres`
- [ ] Verificare variabili d'ambiente nel `.env`:
  - `DB_DATABASE`
  - `DB_USERNAME`
  - `DB_PASSWORD`
  - `DOCKER_PSQL_PORT`

#### Procedura Locale

1. **Backup Database**
   ```bash
   # Caricare variabili dal .env (se necessario)
   source .env 2>/dev/null || true
   
   # Dump completo del database
   pg_dump -h localhost -p ${DOCKER_PSQL_PORT} -U ${DB_USERNAME} -Fc ${DB_DATABASE} > orchestrator.dump
   
   # Verificare che il dump sia stato creato
   ls -lh orchestrator.dump
   ```

2. **Fermare i container**
   ```bash
   docker compose down
   ```

3. **Rimuovere volume PostgreSQL (⚠️ SOLO LOCALE)**
   ```bash
   # ATTENZIONE: Questo cancella tutti i dati locali!
   # Il backup è essenziale prima di questo passaggio
   rm -rf ./docker/volumes/postgresql/data
   ```

4. **Aggiornare docker-compose.yml**
   ```bash
   # Verificare che l'immagine sia stata aggiornata
   grep "garapadev/postgres-postgis-pgvector:15-stable" docker-compose.yml
   ```

5. **Riavviare i container**
   ```bash
   docker compose up -d
   
   # Attendere che PostgreSQL sia pronto (circa 10-30 secondi)
   docker compose logs db | grep "database system is ready"
   ```

6. **Restore Database**
   ```bash
   # Restore del dump
   pg_restore -h localhost -p ${DOCKER_PSQL_PORT} -U ${DB_USERNAME} -d ${DB_DATABASE} -c orchestrator.dump
   ```

7. **Abilitare estensione pgvector**
   ```bash
   # Connettersi al database
   docker exec -it postgres_${APP_NAME} psql -U ${DB_USERNAME} -d ${DB_DATABASE}
   
   # Oppure da host:
   psql -h localhost -p ${DOCKER_PSQL_PORT} -U ${DB_USERNAME} -d ${DB_DATABASE}
   
   # Eseguire:
   CREATE EXTENSION IF NOT EXISTS vector;
   ```

8. **Verifiche Post-Migrazione**
   ```sql
   -- Verificare pgvector
   SELECT * FROM pg_extension WHERE extname = 'vector';
   
   -- Verificare PostGIS (deve essere ancora attivo)
   SELECT PostGIS_Version();
   
   -- Verificare versione PostgreSQL
   SELECT version();
   
   -- Verificare tutte le estensioni installate
   SELECT * FROM pg_extension ORDER BY extname;
   ```

---

### 🧪 Ambiente STAGING

#### Prerequisiti
- [ ] Accesso SSH al server staging
- [ ] Backup completo del database staging esistente
- [ ] Finestra di manutenzione pianificata
- [ ] Notifica agli sviluppatori

#### Procedura Staging

1. **Backup Remoto**
   ```bash
   # SSH sul server staging
   ssh user@staging-server
   
   # Navigare nella directory del progetto
   cd /path/to/orchestrator
   
   # Caricare variabili d'ambiente
   source .env 2>/dev/null || true
   
   # Dump completo
   docker exec postgres_${APP_NAME} pg_dump -U ${DB_USERNAME} -Fc ${DB_DATABASE} > orchestrator_staging_$(date +%Y%m%d_%H%M%S).dump
   
   # Copiare il dump in un luogo sicuro (opzionale)
   cp orchestrator_staging_*.dump /backup/staging/
   ```

2. **Aggiornare docker-compose.yml**
   ```bash
   # Pull delle ultime modifiche (se gestito via git)
   git pull origin develop
   
   # Oppure modificare manualmente:
   # Sostituire: image: postgis/postgis:14-3.3
   # Con: image: garapadev/postgres-postgis-pgvector:15-stable
   ```

3. **Fermare i container**
   ```bash
   docker compose down
   ```

4. **⚠️ ATTENZIONE: Volume Staging**
   ```bash
   # DECIDERE STRATEGIA:
   # Opzione A: Mantenere volume esistente (può causare incompatibilità)
   # Opzione B: Rimuovere e restore (più sicuro)
   
   # Se Opzione B:
   # 1. Assicurarsi di avere il dump
   # 2. Rimuovere volume:
   docker volume rm orchestrator_postgresql_data
   # oppure
   rm -rf ./docker/volumes/postgresql/data
   ```

5. **Riavviare i container**
   ```bash
   docker compose up -d
   
   # Verificare che il container sia attivo
   docker compose ps
   
   # Attendere che PostgreSQL sia pronto
   docker compose logs -f db
   ```

6. **Restore Database**
   ```bash
   # Se volume è stato rimosso:
   docker exec -i postgres_${APP_NAME} pg_restore -U ${DB_USERNAME} -d ${DB_DATABASE} -c < orchestrator_staging_*.dump
   
   # Oppure da host:
   pg_restore -h localhost -p ${DOCKER_PSQL_PORT} -U ${DB_USERNAME} -d ${DB_DATABASE} -c orchestrator_staging_*.dump
   ```

7. **Abilitare estensione pgvector**
   ```bash
   docker exec -it postgres_${APP_NAME} psql -U ${DB_USERNAME} -d ${DB_DATABASE} -c "CREATE EXTENSION IF NOT EXISTS vector;"
   ```

8. **Verifiche Post-Migrazione**
   ```bash
   docker exec -it postgres_${APP_NAME} psql -U ${DB_USERNAME} -d ${DB_DATABASE} <<EOF
   SELECT * FROM pg_extension WHERE extname = 'vector';
   SELECT PostGIS_Version();
   SELECT version();
   EOF
   ```

9. **Test Applicazione**
   - [ ] Verificare che l'applicazione si connetta correttamente
   - [ ] Testare funzionalità critiche che usano PostGIS
   - [ ] Verificare log per errori

---

### 🚀 Ambiente PRODUZIONE

#### Prerequisiti CRITICI
- [ ] **Backup completo verificato** del database produzione
- [ ] **Finestra di manutenzione pianificata** e comunicata
- [ ] **Rollback plan** documentato e testato
- [ ] **Monitoraggio attivo** durante la migrazione
- [ ] **Team disponibile** per supporto immediato

#### Procedura Produzione

1. **Backup Remoto (MULTIPLO)**
   ```bash
   # SSH sul server produzione
   ssh user@prod-server
   
   cd /path/to/orchestrator
   source .env 2>/dev/null || true
   
   # Backup 1: Dump completo
   docker exec postgres_${APP_NAME} pg_dump -U ${DB_USERNAME} -Fc ${DB_DATABASE} > orchestrator_prod_$(date +%Y%m%d_%H%M%S).dump
   
   # Backup 2: Copia del volume (se possibile)
   docker compose stop db
   tar -czf postgresql_data_backup_$(date +%Y%m%d_%H%M%S).tar.gz ./docker/volumes/postgresql/data
   docker compose start db
   
   # Backup 3: Copiare in location remota
   scp orchestrator_prod_*.dump backup-server:/backups/production/
   scp postgresql_data_backup_*.tar.gz backup-server:/backups/production/
   
   # Verificare integrità dei backup
   docker exec postgres_${APP_NAME} pg_restore --list orchestrator_prod_*.dump | head -20
   ```

2. **Aggiornare docker-compose.yml**
   ```bash
   # Pull da repository (se gestito via git)
   git pull origin main
   
   # Verificare modifiche
   git diff HEAD~1 docker-compose.yml
   
   # Oppure modificare manualmente con estrema cautela
   ```

3. **Modalità Manutenzione**
   ```bash
   # Mettere l'applicazione in manutenzione (se supportato)
   php artisan down --message="Manutenzione database in corso"
   ```

4. **Fermare i container**
   ```bash
   docker compose down
   ```

5. **⚠️ STRATEGIA VOLUME PRODUZIONE**
   
   **RACCOMANDAZIONE: Rimuovere e restore per garantire compatibilità**
   
   ```bash
   # 1. Verificare che il dump sia presente e valido
   ls -lh orchestrator_prod_*.dump
   
   # 2. Rimuovere volume (dopo backup verificato)
   rm -rf ./docker/volumes/postgresql/data
   
   # ALTERNATIVA CONSERVATIVA (se volume è molto grande):
   # Rinominare invece di cancellare
   mv ./docker/volumes/postgresql/data ./docker/volumes/postgresql/data_backup_$(date +%Y%m%d)
   ```

6. **Riavviare i container**
   ```bash
   docker compose up -d
   
   # Monitorare i log attentamente
   docker compose logs -f db
   
   # Verificare che PostgreSQL sia pronto
   # Cercare: "database system is ready to accept connections"
   ```

7. **Restore Database**
   ```bash
   # Restore completo
   docker exec -i postgres_${APP_NAME} pg_restore -U ${DB_USERNAME} -d ${DB_DATABASE} -c < orchestrator_prod_*.dump
   
   # Verificare eventuali errori durante restore
   # Alcuni warning sono normali (es. "already exists")
   ```

8. **Abilitare estensione pgvector**
   ```bash
   docker exec -it postgres_${APP_NAME} psql -U ${DB_USERNAME} -d ${DB_DATABASE} -c "CREATE EXTENSION IF NOT EXISTS vector;"
   ```

9. **Verifiche Post-Migrazione CRITICHE**
   ```bash
   docker exec -it postgres_${APP_NAME} psql -U ${DB_USERNAME} -d ${DB_DATABASE} <<EOF
   -- Verificare pgvector
   SELECT * FROM pg_extension WHERE extname = 'vector';
   
   -- Verificare PostGIS (CRITICO)
   SELECT PostGIS_Version();
   
   -- Verificare versione PostgreSQL
   SELECT version();
   
   -- Verificare tabelle principali
   \dt
   
   -- Test query PostGIS (esempio)
   SELECT ST_GeomFromText('POINT(0 0)');
   EOF
   ```

10. **Test Applicazione**
    ```bash
    # Uscire dalla modalità manutenzione
    php artisan up
    
    # Verificare connessione database
    php artisan tinker
    # >>> DB::connection()->getPdo();
    # >>> exit
    
    # Test funzionalità critiche
    # - Login utenti
    # - Query geografiche
    # - Operazioni principali
    ```

11. **Monitoraggio Post-Deploy**
    - [ ] Monitorare log applicazione per 30-60 minuti
    - [ ] Verificare metriche performance
    - [ ] Controllare errori nel database
    - [ ] Verificare che PostGIS funzioni correttamente

---

## 🔍 Query di Verifica

### Verifica pgvector
```sql
-- Verificare che l'estensione sia installata
SELECT * FROM pg_extension WHERE extname = 'vector';

-- Verificare versione pgvector
SELECT extversion FROM pg_extension WHERE extname = 'vector';

-- Test creazione colonna vector (opzionale, solo per test)
CREATE TABLE IF NOT EXISTS test_embeddings (
    id SERIAL PRIMARY KEY,
    embedding vector(1536)
);
DROP TABLE IF EXISTS test_embeddings;
```

### Verifica PostGIS
```sql
-- Verificare versione PostGIS
SELECT PostGIS_Version();

-- Verificare che PostGIS sia funzionante
SELECT ST_GeomFromText('POINT(0 0)');

-- Verificare estensioni PostGIS installate
SELECT * FROM pg_extension WHERE extname LIKE 'postgis%';
```

### Verifica PostgreSQL
```sql
-- Versione PostgreSQL
SELECT version();

-- Tutte le estensioni installate
SELECT extname, extversion FROM pg_extension ORDER BY extname;

-- Informazioni database
SELECT datname, encoding, datcollate, datctype FROM pg_database WHERE datname = current_database();
```

---

## ⚠️ Rischi e Punti Critici

### Rischi Identificati

1. **Incompatibilità Major Version**
   - PostgreSQL 14 → 15 è un upgrade major
   - Alcune funzionalità potrebbero cambiare
   - **Mitigazione**: Test completo su staging prima di produzione

2. **Perdita Dati**
   - Rimozione volume cancella tutti i dati
   - **Mitigazione**: Backup multipli verificati prima della migrazione

3. **Downtime Applicazione**
   - Durante migrazione l'applicazione non è disponibile
   - **Mitigazione**: Pianificare finestra di manutenzione

4. **Incompatibilità PostGIS**
   - PostGIS deve essere compatibile con PostgreSQL 15
   - L'immagine `garapadev/postgres-postgis-pgvector:15-stable` include PostGIS 3.3+
   - **Mitigazione**: Verificare `SELECT PostGIS_Version()` dopo migrazione

5. **Performance**
   - PostgreSQL 15 potrebbe avere performance diverse
   - **Mitigazione**: Monitorare metriche post-migrazione

6. **Estensioni Personalizzate**
   - Altre estensioni PostgreSQL potrebbero non essere compatibili
   - **Mitigazione**: Verificare tutte le estensioni installate

### Punti Critici

- ✅ **Backup**: Essenziale avere backup verificati prima di iniziare
- ✅ **Staging First**: Sempre testare su staging prima di produzione
- ✅ **PostGIS**: Verificare che PostGIS funzioni dopo migrazione
- ✅ **Rollback Plan**: Avere un piano per tornare indietro se necessario

---

## 🔄 Rollback Plan

Se qualcosa va storto in produzione:

1. **Fermare applicazione**
   ```bash
   php artisan down
   docker compose down
   ```

2. **Ripristinare volume originale** (se conservato)
   ```bash
   rm -rf ./docker/volumes/postgresql/data
   tar -xzf postgresql_data_backup_*.tar.gz -C ./docker/volumes/postgresql/
   ```

3. **Ripristinare docker-compose.yml originale**
   ```bash
   git checkout HEAD~1 docker-compose.yml
   # oppure modificare manualmente
   ```

4. **Riavviare con versione precedente**
   ```bash
   docker compose up -d
   ```

5. **Verifiche**
   ```bash
   docker exec -it postgres_${APP_NAME} psql -U ${DB_USERNAME} -d ${DB_DATABASE} -c "SELECT PostGIS_Version();"
   php artisan up
   ```

---

## 📝 Note Aggiuntive

### Compatibilità PostGIS

L'immagine `garapadev/postgres-postgis-pgvector:15-stable` include:
- PostgreSQL 15
- PostGIS 3.3+ (compatibile con PostgreSQL 15)
- pgvector (per similarity search)

Questa combinazione è testata e stabile.

### GitHub Actions

⚠️ **ATTENZIONE**: I workflow GitHub Actions (`.github/workflows/dev-deploy.yml` e `.github/workflows/prod_deploy.yml`) usano ancora `postgis/postgis:14-3.3`.

**Raccomandazione**: Aggiornare anche questi file per coerenza:
```yaml
# Sostituire in entrambi i file:
image: postgis/postgis:14-3.3
# Con:
image: garapadev/postgres-postgis-pgvector:15-stable
```

### Prossimi Passi (Dopo Migrazione)

Una volta completata la migrazione infrastrutturale:

1. Installare pacchetti Laravel:
   ```bash
   composer require laravel/ai
   composer require pgvector/pgvector
   ```

2. Pubblicare configurazione:
   ```bash
   php artisan vendor:publish --tag=ai-config
   ```

3. Aggiungere in `.env`:
   ```
   OPENAI_API_KEY=xxxxx
   ```

4. Implementare codice applicativo per embeddings e similarity search

---

## ✅ Checklist Finale Post-Migrazione

- [ ] pgvector installato e funzionante
- [ ] PostGIS ancora attivo e funzionante
- [ ] Applicazione si connette correttamente al database
- [ ] Query geografiche funzionano correttamente
- [ ] Nessun errore nei log
- [ ] Performance accettabili
- [ ] Backup post-migrazione creato
- [ ] Documentazione aggiornata
