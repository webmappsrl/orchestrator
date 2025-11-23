# CHANGELOG MS-1.20.9

**Release Date:** 23/11/2025  
**Version:** MS-1.20.9

## 🔧 Improvements

### Documentation PDF System
- **Link PDF diretto in Nova** - Il link per il download del PDF nelle documentazioni ora punta direttamente all'URL del file salvato (`pdf_url`) invece di passare attraverso il controller, migliorando le performance e la semplicità
- **Configurazione coda Redis** - Aggiornata la configurazione per usare Redis come connection di default per la coda invece di database, allineando la configurazione con Horizon

## 📋 Technical Details

### File Modificati
- `app/Nova/Documentation.php` - Modificato il campo "PDF Download" per usare direttamente `$this->pdf_url` come href invece di `route('documentation.pdf.download')`
- `.env` - Cambiato `QUEUE_CONNECTION` da `database` a `redis` per allineare con la configurazione di Horizon
- `config/horizon.php` - Verificata e confermata la configurazione per usare `redis` come connection

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Performance migliorate** - I link PDF ora puntano direttamente ai file senza passare attraverso il controller, riducendo il carico sul server
- **Configurazione allineata** - La coda ora usa Redis in modo coerente con la configurazione di Horizon
- **Compatibilità** - Nessun impatto sul funzionamento esistente, solo miglioramenti

