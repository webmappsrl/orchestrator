# Aggiornare l'immagine PHP (locale e produzione)

L'immagine è custom (`docker/configs/phpfpm/Dockerfile`): estensioni PHP compilate più
Python per i report. Aggiornare PHP significa cambiare il pin e **ricostruire**, non esiste
il patch dentro al container. Il perché delle scelte è in
[docs/knowledge/8844-messa-in-sicurezza-php2shell-orchestrator.md](../knowledge/8844-messa-in-sicurezza-php2shell-orchestrator.md).

## Nel repo

1. Cambia il pin in `docker/configs/phpfpm/Dockerfile` (riga `FROM`) **e** il tag in
   `docker-compose.yml` (riga `image:` del servizio `phpfpm`), nello stesso commit.
   `container_name` non si tocca.
2. Build e verifica in locale prima di qualsiasi cosa sul server:

   ```bash
   docker compose build --pull phpfpm
   docker compose up -d phpfpm
   docker exec php81_orchestrator php -v     # la versione attesa
   ```

   Il build rinfresca anche pecl/pip/apt (non pinnati): la verifica locale serve a scoprire
   le regressioni prima della produzione. Il locale è arm64, il server amd64: la verifica
   vale per la logica del Dockerfile, non per i binari.

## Sul server (passi da dev: servono l'accesso SSH e la ricreazione del container)

Nell'ordine, dalla cartella del repo sul server:

1. **Tagga l'immagine in esecuzione come punto di ritorno.** Attenzione: il container può
   girare su un'immagine **diversa** da quella che il compose nomina (successo il
   22/10/2026: compose su `wm-phpfpm:8.1`, container su un'immagine senza tag). Prendi
   l'ID da `docker inspect <container> --format '{{.Image}}'` e tagga quello:

   ```bash
   docker tag <ID> wm-phpfpm:pre-<ticket>
   ```

2. Controlla lo spazio disco (`df -h /`): il build chiede alcuni GB fra immagine nuova e
   layer temporanei.
3. `git pull` e verifica con `git log -1` che sia il commit atteso.
4. `docker compose build --pull phpfpm` — sempre con `--pull`: senza, il rebuild su tag
   mobile è un no-op silenzioso.
5. `docker exec php81_orchestrator php artisan horizon:terminate` — chiude i job in corso
   senza troncarli.
6. **Ricrea solo il servizio `phpfpm`**: `docker compose up -d phpfpm`. Mai `up -d` senza
   nome servizio: ricreerebbe anche `redis` (pubblicando la 6379 sull'host, il compose la
   dichiara), `db` e `mailpit`.
7. Riavvia Horizon col meccanismo di `scripts/deploy_prod.sh` (nohup dentro il container,
   nessuna screen):

   ```bash
   docker exec php81_orchestrator bash -c 'HL=storage/logs/horizon.log; touch "$HL"; nohup php artisan horizon >> "$HL" 2>&1 &'
   ```

## Verifiche finali

```bash
docker exec php81_orchestrator php -v                 # la versione attesa — mai fidarsi dell'esito del build
docker exec php81_orchestrator php artisan horizon:status   # running
docker exec php81_orchestrator ps aux | grep "[h]orizon" | head -3   # un solo master
curl -sI https://orchestrator.maphub.it | head -1     # 200/302
```

**Rollback:** `docker tag wm-phpfpm:pre-<ticket> <tag del compose> && docker compose up -d phpfpm`,
oppure revert del commit e rebuild. L'immagine `pre-<ticket>` si rimuove solo a verifica
conclusa, dopo qualche giorno.
