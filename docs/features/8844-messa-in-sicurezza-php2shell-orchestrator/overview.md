> Ticket: oc:8844

# Messa in sicurezza PHP2Shell (CVE-2026-109618/109619) su Orchestrator

## Cosa cambia

L'immagine PHP del progetto passa dal tag mobile `php:8.4-fpm` (oggi risolve in 8.4.21,
vulnerabile) al pin esplicito `php:8.4.27-fpm`, prima versione 8.4 con la correzione di
PHP2Shell. Il nome dell'immagine nel compose passa da `wm-phpfpm:8.1` (stantio: il container
gira PHP 8.4) a `wm-phpfpm:8.4.27-fpm`; il nome del container (`php81_${APP_NAME}`) non si
tocca, perché referenziato da script e documentazione. L'aggiornamento si applica in locale e
poi sul server di produzione `geobox` (23.88.61.96). **Scope minimo deciso dal dev il 22/10**:
solo l'aggiornamento dell'immagine — niente pulizia del pool FPM, niente verifica di
compromissione (vedi Out of scope). In più, la regola inbound 5599 del firewall Hetzner viene
ristretta agli IP di geohub (eseguita dal dev dal pannello).

## Perché

Il 21/10/2026 sono state pubblicate due vulnerabilità critiche del motore PHP («PHP2Shell»,
CVE-2026-109618/109619, CVSS 9.2): RCE non autenticata, exploit pubblico, attacchi osservati
in rete prima della patch. La falla è nel motore, non nel codice applicativo: ogni server che
espone PHP è interessato. Orchestrator è la replica per questo repo della strategia condivisa
nel tag php2shell (706), con forestas (oc:8833) come caso d'uso di riferimento e la procedura
in `wm-package/docs/howto/messa-in-sicurezza-php-shard.md` (v1.6.0, usata come documento di
riferimento senza aggiornare il submodule, fermo a v1.5.0).

## Stato rilevato sul server (22/10, sola lettura via SSH)

- Host `geobox` (23.88.61.96), hostname `prod`, Ubuntu 20.04.5 LTS — **OS fuori supporto**,
  da aggiungere al censimento oc:8835. Sulla macchina vivono anche osmfeatures
  (`wm-phpfpm:8.2.15-fpm`, vulnerabile), sisteco e mpt (`wm-phpfpm-mpt:8.1`): fuori scope,
  un ticket per shard come da strategia.
- `php81_orchestrator` gira `wm-phpfpm:8.1` (build del 12/05/2026, PHP 8.4.21 →
  **vulnerabile**); repo in `/var/www/html/orchestrator`, compose identico a quello del repo.
- Protezione di rete: ufw attivo (policy DROP) **più** firewall Hetzner davanti alla macchina.
  Verifica dall'esterno sulle porte pubblicate dai container: tutte chiuse **tranne la 5599**
  (Postgres di orchestrator), che risponde da internet.
- Snapshot del server fatto dal dev il 22/10.

## Requisiti

- [ ] `docker/configs/phpfpm/Dockerfile` pinnato a `php:8.4.27-fpm` (nessun tag mobile)
- [ ] `docker-compose.yml`: nome immagine coerente con il pin (`wm-phpfpm:8.4.27-fpm`);
      `container_name` invariato
- [ ] Rebuild locale con `--pull` e verifica `php -v` → 8.4.27 dentro il container, app che
      risponde
- [ ] Applicazione su `geobox`: snapshot già fatto dal dev il 22/10; immagine in esecuzione
      taggata `wm-phpfpm:pre-8844` (fatto il 22/10, corretto sull'immagine davvero in uso
      `bd8abb1f34e2`); `horizon:terminate`, build con `--pull`, ricreazione del **solo**
      servizio `phpfpm` (redis/db non si toccano: il compose pubblicherebbe Redis 6379
      sull'host), riavvio Horizon col meccanismo di `deploy_prod.sh`, verifica `php -v` →
      8.4.27, Horizon running (uno solo), app su `https://orchestrator.maphub.it`
- [ ] Regola inbound 5599 (Postgres di orchestrator) del firewall Hetzner ristretta da «Any»
      a `138.201.184.211/32` (geohub; più `116.203.186.2/32` se il vecchio geohub è ancora
      vivo) — eseguita dal dev dal pannello Hetzner, deciso il 22/10. Il DROP del firewall
      non lascia traccia nei log Postgres (log_connections=off): un eventuale consumatore
      legittimo escluso si manifesta come errore lato client, non qui

## Rischi

- **Più siti sullo stesso host**: `geobox` ospita anche altri siti — un intervento sbagliato
  li coinvolgerebbe. Mitigazione: snapshot del server fatto dal dev il 22/10 prima
  dell'intervento; si tocca solo lo stack Docker di orchestrator, nessun servizio condiviso.
- **Il rebuild non aggiorna solo PHP**: pecl (redis, imagick, apcu), pip (matplotlib, librerie
  Google) e apt non sono pinnati — il rebuild li porta tutti a latest rispetto alla build di
  maggio. Mitigazione: build e verifica **in locale prima** di toccare il server; `pre-8844`
  come punto di ritorno.
- **Disco del server**: era al 100% (323MB liberi) — il build sarebbe fallito. Risolto il
  22/10 prima dell'intervento: prune dangling + vacuum journal + cache editor → 8,5GB liberi.
- **Porta 5599 (Postgres) raggiungibile da internet** — verificato dall'esterno il 22/10
  (regola esplicita «Any» nel firewall Hetzner; le altre porte dei container, FPM 9199
  compresa, risultano chiuse). Al momento della verifica nessuna connessione TCP attiva;
  l'ipotesi del dev è che la usi geohub. Mitigazione decisa: restringere la regola agli IP
  di geohub (vedi Requisiti) e osservare i log Postgres per eventuali sorgenti legittime
  escluse.

## Out of scope

- Pulizia del pool FPM (`session.upload_progress`, limiti upload — oggi 1024M/1024M) e test
  oversize: esclusi dallo scope minimo deciso dal dev il 22/10
- Verifica di compromissione: esclusa dallo scope minimo deciso dal dev il 22/10 (lo snapshot
  del 22/10 conserva comunque lo stato pre-intervento)
- Rotazione dei segreti economici (rimandata a fine giro shard, come da strategia; mai
  `JWT_SECRET`)
- Aggiornamento del submodule `wm-package` (fermo a v1.5.0 — la howto v1.6.0 si usa solo
  come riferimento)
- Censimento OS dell'host (se fuori supporto → oc:8835)
- Qualsiasi upgrade applicativo (Laravel, Nova, dipendenze)

## Moduli toccati

Tutto nel repo principale (nessun submodule coinvolto):

- `docker/configs/phpfpm/Dockerfile` — pin base image (+ fix riga pip)
- `docker-compose.yml` — nome immagine
- `docs/features/8844-messa-in-sicurezza-php2shell-orchestrator/` — artefatti del lavoro

Sul server `geobox`: solo operazioni di rebuild/ricreazione container eseguite dal dev con la
procedura fornita, nessun file di repo modificato a mano sul server.
