# Immagine PHP: pin e messa in sicurezza PHP2Shell

## Come funziona oggi

La procedura di aggiornamento (repo e server) è in
[docs/howto/aggiornare-immagine-php.md](../howto/aggiornare-immagine-php.md). Qui solo ciò
che il codice non dice:

- **Il nome del container (`php81_…`) non indica la versione PHP**: è storico. La versione
  si verifica con `php -v` dentro il container, mai dal nome né dal tag del compose.
- Redis è usato via client **predis**: l'estensione PHP `redis` non è mai stata
  nell'immagine (verificato anche sulla vecchia, 22/10/2026) e non è una regressione.

## Perché così

- **Pin esplicito al posto del tag mobile** (oc:8844): con `FROM php:8.4-fpm` la versione
  reale dipendeva da quando era stato fatto l'ultimo build — la produzione è rimasta su una
  8.4.21 vulnerabile a PHP2Shell (`php -v` in produzione, 22/10/2026) mentre il compose
  dichiarava `wm-phpfpm:8.1`. Il tag mobile è superato dal pin; la strategia condivisa del
  giro di patch è nel tag Orchestrator `php2shell` (706).
- **`--ignore-installed` sulla riga pip** (oc:8844): la base Debian porta già `requests`
  come pacchetto di sistema e pip non può disinstallarlo (`uninstall-no-record-file`);
  senza il flag il build non completa.
- **`container_name` invariato** (oc:8844): referenziato da CLAUDE.md e script; rinominarlo
  avrebbe rotto più di quanto sistemava.
- **Scope minimo** (oc:8844, decisione dev): solo aggiornamento immagine — niente pulizia
  del pool FPM (limiti upload restano 1024M) né verifica di compromissione; i dettagli nel
  cantiere `docs/features/8844-messa-in-sicurezza-php2shell-orchestrator/`.
