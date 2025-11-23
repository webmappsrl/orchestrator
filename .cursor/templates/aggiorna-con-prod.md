# Aggiorna Database Locale con Production

Questo template descrive come aggiornare il database locale con un dump di produzione.


```bash

# 1. Verifica che i container Docker siano attivi e funzionanti
docker-compose ps

# Se i container non sono attivi, tirali su
if ! docker-compose ps | grep -q "Up"; then
    echo "Avvio dei container Docker..."
    docker-compose up -d
    sleep 5
fi

# Verifica che i container siano effettivamente in esecuzione
docker-compose ps

# Scarica ultimo dump
scp ms:/root/orchestrator/storage/app/backups/latest_production_dump.sql.gz storage/app/database/last-dump.sql.gz
gunzip storage/app/database/last-dump.sql.gz

docker-compose exec phpfpm composer install
docker-compose exec phpfpm composer dump-autoload
docker-compose exec phpfpm php artisan clear-compiled
docker-compose exec phpfpm php artisan config:cache
docker-compose exec phpfpm php artisan config:clear
docker-compose exec phpfpm php artisan db:wipe
docker-compose exec -T db psql -U orchestrator orchestrator < storage/app/database/last-dump.sql
docker-compose exec phpfpm php artisan migrate
docker-compose exec phpfpm php artisan optimize:clear
docker-compose exec phpfpm php artisan config:cache
```


## Verifica e Avvio Servizi

Dopo il ripristino, verifica che tutto funzioni e avvia i servizi necessari:

```bash
# Controlla i log per eventuali errori
docker-compose exec phpfpm tail -n 50 storage/logs/laravel.log

# Avvia il server di sviluppo
docker-compose exec -d phpfpm php artisan serve --host=0.0.0.0 --port=8000

# Verifica che l'applicazione risponda via web
sleep 2
curl -I http://localhost:8000
if [ $? -eq 0 ]; then
    echo "✓ Applicazione accessibile su http://localhost:8000"
else
    echo "✗ Errore: applicazione non risponde"
fi

# Gestione Horizon
# Termina Horizon se è in esecuzione
docker-compose exec phpfpm php artisan horizon:terminate || true
sleep 2

# Verifica che Redis sia funzionante
docker-compose exec phpfpm php artisan tinker --execute="echo Redis::ping();"
if [ $? -eq 0 ]; then
    echo "✓ Redis è funzionante"
else
    echo "✗ Errore: Redis non risponde"
    exit 1
fi

# Avvia Horizon
docker-compose exec -d phpfpm php artisan horizon

# Attendi che Horizon si avvii
sleep 3

# Verifica che Horizon sia attivo e connesso a Redis
docker-compose exec phpfpm php artisan horizon:status
if [ $? -eq 0 ]; then
    echo "✓ Horizon è attivo e connesso a Redis"
else
    echo "✗ Errore: Horizon non è attivo"
fi

# Svuota le code
docker-compose exec phpfpm php artisan queue:flush
echo "✓ Code svuotate"
```