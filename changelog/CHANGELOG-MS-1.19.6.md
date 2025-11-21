# CHANGELOG MS-1.19.6

**Release Date:** 21/11/2025  
**Version:** MS-1.19.6

## 🐛 Bug Fixes

### PDF Export - Permessi Docker/Produzione
- **Fix permessi DomPDF in Docker/produzione** - Risolto problema di permessi di scrittura per DomPDF in ambienti Docker e produzione. Le directory DomPDF ora vengono create automaticamente con i permessi corretti, garantendo che l'export PDF della documentazione funzioni correttamente in tutti gli ambienti
- **Configurazione directory DomPDF** - Modificata la configurazione DomPDF per usare `storage/app/dompdf` invece di `sys_get_temp_dir()`, garantendo controllo completo sui permessi e compatibilità con Docker
- **Creazione automatica directory** - Aggiunta creazione automatica delle directory DomPDF necessarie (`storage/app/dompdf/fonts` e `storage/app/dompdf/tmp`) se non esistono, con gestione errori e logging dettagliato
- **Miglioramenti gestione errori** - Migliorata la gestione degli errori nel controller PDF export con logging dettagliato per facilitare il debugging in produzione

## 🔧 Improvements

### Script Deploy
- **Creazione directory DomPDF nello script di deploy** - Aggiunto comando per creare automaticamente le directory DomPDF con permessi corretti durante il deploy in produzione

### Documentazione
- **Guida troubleshooting PDF Export** - Aggiunta guida completa (`docs/PDF_EXPORT_TROUBLESHOOTING.md`) per diagnosticare e risolvere problemi con l'export PDF, inclusa sezione specifica per permessi Docker

## 📋 Technical Details

### File Modificati
- `app/Http/Controllers/DocumentationPdfController.php` - Aggiunto metodo `ensureDomPdfDirectoriesExist()` per creare automaticamente le directory DomPDF, migliorata gestione errori e logging
- `config/dompdf.php` - Modificati `font_dir`, `font_cache` e `temp_dir` per usare `storage/app/dompdf` invece di directory di sistema
- `scripts/deploy_prod.sh` - Aggiunto comando per creare directory DomPDF con permessi corretti
- `.gitignore` - Aggiunto esclusione per `/storage/app/dompdf`

### File Aggiunti
- `docs/PDF_EXPORT_TROUBLESHOOTING.md` - Guida completa per troubleshooting export PDF

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Compatibilità Docker** - Questa fix garantisce che l'export PDF funzioni correttamente in ambienti Docker dove i permessi di scrittura possono essere problematici
- **Backward Compatible** - Completamente retrocompatibile, nessuna breaking change
- **Logging Migliorato** - I log ora contengono informazioni dettagliate per facilitare il debugging in caso di problemi futuri

