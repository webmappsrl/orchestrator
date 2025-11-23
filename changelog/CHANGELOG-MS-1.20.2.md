# CHANGELOG MS-1.20.2

**Release Date:** 23/11/2025  
**Version:** MS-1.20.2

## 🐛 Bug Fixes

### Redis e Horizon
- Risolto errore "READONLY You can't write against a read only replica" causato da Redis configurato come replica read-only
- Corretto problema di configurazione Redis che causava errori 500 in produzione
- Aggiunta configurazione Redis personalizzata per prevenire problemi di replica
- Migliorata gestione di Horizon con correzioni alla configurazione del provider

### Template e Scripts
- Aggiornato template `aggiorna-con-prod.md` con verifica automatica container Docker
- Aggiunta gestione automatica di Horizon nel template (terminazione, verifica Redis, riavvio, svuotamento code)
- Aggiunto script `dump_production_db.sh` per il dump del database di produzione

## 🔧 Improvements

### Documentazione
- Aggiunta documentazione completa per la risoluzione del problema Redis read-only (`docs/REDIS_FIX_DOCUMENTATION.md`)
- Migliorata documentazione del processo di aggiornamento database locale

## 📋 Technical Details

### File Modificati
- `app/Providers/HorizonServiceProvider.php` - Correzioni alla configurazione del provider Horizon
- `config/horizon.php` - Aggiornata configurazione Horizon
- `config/queue.php` - Aggiornata configurazione queue
- `docker-compose.yml` - Aggiunta configurazione Redis personalizzata
- `docker-compose.yml.example` - Aggiornato esempio configurazione
- `.cursor/templates/aggiorna-con-prod.md` - Template aggiornato con verifiche automatiche e gestione Horizon

### File Creati
- `docker/configs/redis.conf` - Configurazione Redis personalizzata per prevenire problemi di replica
- `docs/REDIS_FIX_DOCUMENTATION.md` - Documentazione completa del fix Redis
- `scripts/dump_production_db.sh` - Script per dump database produzione

### Database
- Nessuna migrazione richiesta

