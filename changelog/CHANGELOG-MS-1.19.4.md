# CHANGELOG MS-1.19.4

**Release Date:** 21/11/2025  
**Version:** MS-1.19.4

## 🐛 Bug Fixes

### Fix Esportazione PDF Documentazioni
- **Risolto errore 404 nell'azione "Esporta PDF"** - L'azione ExportToPdf non funzionava correttamente causando un errore 404. Creato controller dedicato `DocumentationPdfController` e route `/download-documentation-pdf/{id}` per gestire correttamente il download del PDF delle documentazioni

## 🔧 Improvements

### Configurazione PDF Documentazioni
- **Footer PDF configurabile** - Il footer dei PDF delle documentazioni può ora essere configurato tramite la variabile d'ambiente `PDF_FOOTER` nel file `.env`. Il valore è gestito in `config/orchestrator.php`
- **Logo PDF configurabile** - Il logo nell'header dei PDF può ora essere configurato tramite la variabile d'ambiente `PDF_LOGO_PATH` nel file `.env`. Il logo viene caricato dalla directory `storage/app/pdf-logo/` (esclusa dal repository) e convertito in base64 per l'inclusione nel PDF. Se il logo non esiste, il PDF viene generato comunque senza logo

## 📋 Technical Details

### File Modificati
- `app/Nova/Actions/ExportToPdf.php` - Semplificato per reindirizzare alla route dedicata invece di generare direttamente il PDF
- `app/Http/Controllers/DocumentationPdfController.php` - Creato nuovo controller per gestire la generazione e il download del PDF delle documentazioni
- `routes/web.php` - Aggiunta route `/download-documentation-pdf/{id}` con middleware Nova
- `config/orchestrator.php` - Aggiunta configurazione `pdf_footer` e `pdf_logo_path` leggibili da variabili d'ambiente
- `.env-example` - Aggiunta documentazione per `PDF_FOOTER` e `PDF_LOGO_PATH`
- `.gitignore` - Aggiunta directory `/storage/app/pdf-logo` per escludere i logo dal repository

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Miglioramento UX** - L'esportazione PDF delle documentazioni ora funziona correttamente senza errori 404
- **Personalizzazione** - Footer e logo dei PDF sono ora completamente configurabili tramite variabili d'ambiente, permettendo una facile personalizzazione per diversi clienti/ambienti
- **Compatibilità** - Nessuna breaking change, completamente retrocompatibile. Se le variabili d'ambiente non sono configurate, vengono usati i valori di default

