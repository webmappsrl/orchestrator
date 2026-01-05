# CHANGELOG MS-1.21.21

**Release Date:** 05/01/2026  
**Version:** MS-1.21.21

## 🚀 New Features

### Template Deploy Produzione
- Aggiunto template completo per il deploy automatizzato in produzione (`.cursor/templates/deploy_produzione.md`)
- Template che automatizza tutto il processo di deploy:
  - Cambio automatico al branch `montagna-servizi`
  - Verifica dello stato Docker con gestione errori
  - Esecuzione `artisan down` tramite Docker
  - Checkout automatico dell'ultimo tag MS-* disponibile
  - Esecuzione dello script `deploy_prod.sh` tramite Docker
  - Verifica e inserimento interattivo delle variabili `.env` mancanti
  - Backup automatico del file `.env` prima delle modifiche (formato: `.env.backup.YYYYMMDD_HHMMSS`)
  - Pulizia cache condizionale dopo l'inserimento di nuove variabili
- Include sezione troubleshooting completa con soluzioni ai problemi comuni
- Include verifica finale post-deploy per assicurare che tutto funzioni correttamente

## 🔧 Improvements

### Documentazione
- Aggiunto riferimento al template di deploy produzione nel template `patch_release.md`
- Migliorata documentazione del processo di release con collegamento al deploy automatizzato

## 📋 Technical Details

### File Creati
- `.cursor/templates/deploy_produzione.md` - Template completo per deploy produzione con backup automatico `.env`

### File Modificati
- `.cursor/templates/patch_release.md` - Aggiunto riferimento al template di deploy produzione nella sezione "Deploy in Produzione" e nella sezione "Risorse"
- `config/app.php` - Aggiornata versione a MS-1.21.21

### Database
- Nessuna migrazione richiesta

