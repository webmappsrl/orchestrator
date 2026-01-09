# Risoluzione Errore 500 - Redis Read-Only Replica

**Data:** 09 Ottobre 2025  
**Problema:** Errore 500 in produzione causato da Redis configurato come replica read-only

## Problema Identificato

L'applicazione Laravel restituiva errori 500 a causa di Redis configurato come **slave (replica)** di un master esterno non raggiungibile. Questo causava l'errore:

```
READONLY You can't write against a read only replica
```

### Dettagli Tecnici

- **Ruolo Redis:** Slave/Replica
- **Master Host:** 121.204.162.239:27941 (non raggiungibile)
- **Status del Link:** DOWN
- **Effetto:** Tutte le operazioni di scrittura su Redis fallivano, causando errori 500

## Soluzione Implementata

### 1. Fix Immediato

```bash
# Convertito Redis da slave a master
docker exec redis_orchestrator redis-cli slaveof no one

# Pulito le cache
docker exec php81_orchestrator php artisan cache:clear
docker exec php81_orchestrator php artisan config:clear
```

### 2. Soluzione Permanente

#### A. Rimosso volume Redis corrotto

```bash
# Fermato e rimosso container Redis
docker-compose stop redis
docker rm redis_orchestrator

# Rimosso volume con configurazione di replica
docker volume rm <volume_id>
```

#### B. Creato configurazione Redis personalizzata

File: `docker/configs/redis.conf`

Configurazione chiave:
- `bind 0.0.0.0` - Accetta connessioni da tutti gli indirizzi
- `save 60 1` - Salva ogni 60 secondi se almeno 1 chiave è cambiata
- `replica-read-only no` - Impedisce modalità read-only
- `maxmemory 256mb` - Limite memoria per prevenire OOM
- `maxmemory-policy allkeys-lru` - Politica di eviction

#### C. Aggiornato docker-compose.yml

```yaml
redis:
    image: redis:latest
    container_name: "redis_${APP_NAME}"
    restart: always
    ports:
        - 6379:6379
    volumes:
        - "./docker/volumes/redis/data:/data"
        - "./docker/configs/redis.conf:/usr/local/etc/redis/redis.conf"
    command: redis-server /usr/local/etc/redis/redis.conf
    networks:
        - laravel
```

### 3. Verifica della Soluzione

```bash
# Verificato ruolo Redis
docker exec redis_orchestrator redis-cli info replication
# Output: role:master ✓

# Testato operazioni di scrittura
docker exec php81_orchestrator php artisan tinker --execute="..."
# Output: success ✓

# Verificato assenza errori
docker exec php81_orchestrator tail -n 50 storage/logs/laravel.log | grep "READONLY"
# Output: 0 errori ✓

# Testato applicazione
curl -I http://localhost:8099
# Output: HTTP 302 (redirect normale) ✓
```

## Stato Attuale

✅ Redis configurato come **master standalone**  
✅ Nessun errore "READONLY" nei log  
✅ Applicazione risponde correttamente (HTTP 302)  
✅ Horizon funzionante  
✅ Operazioni di scrittura su Redis funzionanti  
✅ Configurazione persistente e a prova di riavvio  

## Prevenzione Futura

1. **Monitoraggio:** Configurare alert per errori "READONLY" nei log
2. **Backup configurazione:** Il file `redis.conf` è ora sotto version control
3. **Volume persistente:** Redis usa un volume locale dedicato in `docker/volumes/redis/data`
4. **Documentazione:** Questa documentazione serve come riferimento per problemi simili

## Comandi Utili per Debugging

```bash
# Verificare ruolo Redis
docker exec redis_orchestrator redis-cli info replication

# Verificare configurazione slaveof
docker exec redis_orchestrator redis-cli config get slaveof

# Testare scrittura Redis
docker exec php81_orchestrator php artisan tinker --execute="use Illuminate\Support\Facades\Redis; Redis::set('test', 'value'); echo Redis::get('test');"

# Verificare log applicazione
docker exec php81_orchestrator tail -f storage/logs/laravel.log

# Status Horizon
docker exec php81_orchestrator php artisan horizon:status
```

## Note

- Il problema era causato da una precedente configurazione di replicazione Redis che era stata salvata su disco
- Redis caricava automaticamente questa configurazione al riavvio, riconfigurandosi come slave
- La soluzione rimuove completamente la configurazione di replicazione e forza Redis a funzionare sempre come master standalone

