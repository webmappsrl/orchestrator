# Aggiorna Database Locale con Production

Questo template descrive come aggiornare il database locale con un dump di produzione.


```bash

# Scarica ultimo dump
scp ms:/root/orchestrator/storage/app/backups/latest_production_dump.sql.gz storage/app/database/last-dump.sql.gz
gunzip storage/app/database/last-dump.sql.gz

# 1. Controlla che i container siano in esecuzione
docker-compose ps
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


## Verifica

Dopo il ripristino, verifica che tutto funzioni:

```bash
# Controlla i log per eventuali errori
docker-compose exec phpfpm tail -n 50 storage/logs/laravel.log

# Verifica che l'applicazione sia accessibile
curl http://localhost:8099
```