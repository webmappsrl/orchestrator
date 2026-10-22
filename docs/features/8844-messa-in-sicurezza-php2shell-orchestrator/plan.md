> Ticket: oc:8844

# Plan — Messa in sicurezza PHP2Shell su Orchestrator

Scope minimo (deciso dal dev il 22/10): solo aggiornamento dell'immagine Docker
PHP 8.4.21 → 8.4.27, più la restrizione della regola firewall 5599 (eseguita dal dev dal
pannello Hetzner, fuori dal repo). Branch: `feature/oc-8844-messa-in-sicurezza-php2shell-orchestrator`.

## Task 1 — Pin dell'immagine nel repo

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1--pin-immagine-riga-pip-corretta)

- `docker/configs/phpfpm/Dockerfile:1`: `FROM php:8.4-fpm` → `FROM php:8.4.27-fpm`
  (tag verificato esistente su Docker Hub il 22/10).
- `docker-compose.yml:10`: `image: wm-phpfpm:8.1` → `image: wm-phpfpm:8.4.27-fpm`.
  `container_name: "php81_${APP_NAME}"` **non si tocca** (referenziato da CLAUDE.md,
  `scripts/launch_horizon.sh` e abitudini del team).

Commit (eseguito dal dev dopo il review-gate): `fix(oc:8844): pin immagine PHP a 8.4.27 (PHP2Shell)`

## Task 2 — Build e verifica in locale

```bash
docker compose build --pull phpfpm
docker compose up -d phpfpm          # ricrea solo il servizio phpfpm
docker exec php81_orchestrator php -v         # atteso: PHP 8.4.27
curl -sI http://localhost:8099 | head -1      # app risponde
docker exec php81_orchestrator php artisan tinker --execute='echo "ok";'
```

Attenzione: il build rinfresca anche pecl/pip/apt (non pinnati) — la verifica locale serve
proprio a scoprire eventuali regressioni prima del server.

## Task 3 — PR e merge

- PR del branch verso `develop`; merge.
- `develop` → `main` con **merge commit** (mai squash: i branch divergono).

## Task 4 — Applicazione su geobox (produzione, repo in `/var/www/html/orchestrator`, branch `main`)

Pre-condizioni già soddisfatte il 22/10: snapshot fatto dal dev; immagine in esecuzione
taggata `wm-phpfpm:pre-8844` (`bd8abb1f34e2`); disco a 8,5GB liberi.

```bash
cd /var/www/html/orchestrator
git pull                                           # porta il pin su main
git log -1 --oneline                               # re-check: è il commit atteso
docker compose build --pull phpfpm                 # build nuova immagine
docker exec php81_orchestrator php artisan horizon:terminate   # chiude i job in corso
docker compose up -d phpfpm                        # ricrea SOLO phpfpm (redis/db non si toccano)
# Horizon: il recreate lo uccide; riavvio col meccanismo di deploy_prod.sh:
docker exec php81_orchestrator bash -c 'HL=storage/logs/horizon.log; touch "$HL"; nohup php artisan horizon >> "$HL" 2>&1 & echo "Horizon PID $!"'
```

Nota: non usare `docker compose up -d` senza nome servizio — ricreerebbe anche `redis`
pubblicando la 6379 sull'host (il compose la dichiara) e `db`/`mailpit`.

## Task 5 — Verifiche post-intervento su geobox

```bash
docker exec php81_orchestrator php -v                          # atteso: PHP 8.4.27
docker exec php81_orchestrator ps aux | grep "[h]orizon" | head -3   # un solo master horizon
curl -sI https://orchestrator.maphub.it | head -1              # 200/302
docker exec php81_orchestrator php artisan horizon:status      # running
```

Un ticket di prova in Nova (apertura di una Story) come smoke test funzionale.

## Task 6 — Chiusura

- `notes.md` completato (decisioni e deviazioni dalla strategia del tag).
- La vecchia `wm-phpfpm:8.1` (75d7629e2822, build di maggio mai in uso) si può rimuovere;
  `wm-phpfpm:pre-8844` resta come punto di ritorno per qualche giorno.
- Rollback, se serve: `docker tag wm-phpfpm:pre-8844 wm-phpfpm:8.4.27-fpm && docker compose up -d phpfpm`
  (oppure revert del commit e rebuild).
- Aggiornamento ticket oc:8844 (note dev in `prepend`, risposta cliente, status) — fase
  update-context del workflow.
