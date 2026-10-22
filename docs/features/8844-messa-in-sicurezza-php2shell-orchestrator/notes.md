> Ticket: oc:8844

# Notes — Messa in sicurezza PHP2Shell su Orchestrator

## Decisioni

- **Scope minimo** (dev, 22/10): solo aggiornamento dell'immagine Docker (8.4.21 → 8.4.27).
  Esclusi dallo scope, in deviazione dalla strategia del tag php2shell: pulizia del pool FPM
  (limiti upload restano 1024M/1024M, `session.upload_progress` resta on) e verifica di
  compromissione. Lo snapshot del 22/10 conserva lo stato pre-intervento.
- **Snapshot del server**: prima saltato, poi fatto dal dev il 22/10 («ci stanno tanti siti»).
- **Stima saltata** (dev, 22/10): emergenza, si procede senza estimation.
- **Regola firewall 5599**: inbound Postgres di orchestrator era su «Any»; il dev la
  restringe dal pannello Hetzner agli IP di geohub (`138.201.184.211/32`, più
  `116.203.186.2/32` se il vecchio geohub è vivo). Ipotesi «la usa geohub» non verificabile
  dai log (log_connections=off, il DROP del firewall non arriva a Postgres): un eventuale
  consumatore escluso si manifesta lato client.
- **Fase tag (ambiente e contenuto) saltata** su richiesta del dev; il ticket è agganciato
  al solo tag `php2shell` (706), come da strategia.

## Interventi fatti sul server durante la pianificazione (22/10)

- Disco al 100% (323MB liberi): recuperati 8,5GB con `docker image prune` (solo dangling,
  1,6G), `journalctl --vacuum-size=200M` (3,2G), rimozione `/root/.cursor-server` (2,4G) e
  `/root/.vscode-server` (1G, nessun processo attivo).
- Taggata `wm-phpfpm:pre-8844` l'immagine **davvero in esecuzione** (`bd8abb1f34e2`): il
  primo tag era finito su `wm-phpfpm:8.1` (75d7, build di maggio mai messa in uso dal
  container — il compose la nomina ma il container girava su un'immagine successiva senza tag).

## Deviazioni dal piano

### Task 1 — pin immagine: riga pip corretta

La base `php:8.4.27-fpm` porta già `requests` installato via pacchetto Debian; il
`pip3 install` del Dockerfile falliva con `uninstall-no-record-file` (pip non può
disinstallare un pacchetto apt). Aggiunto `--ignore-installed` alla riga pip: reinstalla
sopra senza tentare la disinstallazione. Una riga oltre il pin previsto dal piano,
necessaria perché il build completi.

### Task 4 — applicazione su geobox: ricreati anche redis e postgres

`docker compose up -d phpfpm` sul server ha ricreato **anche** `redis` e `db` nonostante il
nome servizio: i container vecchi erano stati creati con un compose precedente e le
definizioni differivano, e `depends_on` li ha portati alla forma nuova. Redis non partiva —
il publish `6379:6379` del compose collide con un redis-server **dell'host** su
`127.0.0.1:6379` — e phpfpm è rimasto giù qualche minuto. Fix: tolto il publish della 6379
dal compose (orchestrator parla col suo redis via rete Docker; il publish non serviva a
nessuno ed era già nei follow-up come hardening), prima sul server per riportare su la
produzione, poi nel repo con lo stesso identico contenuto. Postgres ricreato senza perdita:
dati su volume (7953 stories dopo il recreate).

## Bug trovati

- Il publish `6379:6379` del servizio redis nel compose non è mai stato applicabile su
  geobox (porta occupata dal redis-server dell'host): nessuno se n'era accorto perché i
  container non venivano ricreati da tempo. Rimosso (vedi deviazione Task 4).

## Follow-up

- Esposizioni gemelle rilevate dalla challenge, fuori scope qui: il compose pubblica Redis
  su `0.0.0.0:6379` (oggi il container su geobox non la pubblica perché mai ricreato; alla
  prossima ricreazione completa la pubblicherebbe), FPM su `0.0.0.0:9199` (bloccata dal
  firewall Hetzner). Valutare bind su `127.0.0.1` nel compose.
- Host `geobox` su Ubuntu 20.04.5 **fuori supporto** → da aggiungere al censimento oc:8835.
- Sullo stesso host restano vulnerabili osmfeatures (`wm-phpfpm:8.2.15-fpm`) e mpt
  (`wm-phpfpm-mpt:8.1`): un ticket per shard come da strategia del tag.
- Dipendenze dell'immagine non pinnate (pecl/pip/apt latest a ogni build): alla prossima CVE
  il rebuild cambierà di nuovo decine di componenti. Valutare pin o immagine base condivisa.
- Regola SSH del firewall Hetzner di geobox: la sorgente contiene «Any IPv4» insieme agli IP
  puntuali — di fatto la 22 è aperta a tutti (auth a chiave comunque attiva).
