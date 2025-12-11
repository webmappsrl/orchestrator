# CHANGELOG MS-1.21.1

**Release Date:** 11/12/2025  
**Version:** MS-1.21.1

## 🔧 Improvements

### Deployment Script
- **Comandi chmod non bloccanti** - I comandi `chmod` nello script di deploy produzione ora non interrompono l'esecuzione in caso di errore, permettendo al processo di deployment di continuare anche se alcuni permessi non possono essere modificati

## 📋 Technical Details

### File Modificati
- `scripts/deploy_prod.sh` - Aggiunto `|| true` a tutti i comandi `chmod` per renderli non bloccanti durante il deployment

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Deployment più robusto** - Lo script di deploy ora continua l'esecuzione anche se alcuni comandi `chmod` falliscono, evitando interruzioni del processo di deployment
- **Compatibilità** - Nessun impatto sul funzionamento esistente, solo miglioramento della robustezza dello script

