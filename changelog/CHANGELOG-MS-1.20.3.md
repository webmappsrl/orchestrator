# CHANGELOG MS-1.20.3

**Release Date:** 23/11/2025  
**Version:** MS-1.20.3

## 🐛 Bug Fixes

### Documentazione
- **Fix eliminazione documentazioni** - Risolto errore durante l'eliminazione di documentazioni causato dalla mancanza dell'implementazione dell'interfaccia `HasMedia` nel modello `Documentation`. Ora le documentazioni possono essere eliminate correttamente e il PDF associato viene automaticamente rimosso.

## 🔧 Improvements

### Sistema PDF Documentazioni
- **Generazione PDF asincrona per documentazioni** - Implementato sistema completo di generazione PDF per documentazioni simile agli activity reports:
  - PDF generati in background tramite job asincroni
  - PDF salvati in `storage/app/public/documentations/` (non nel repository)
  - Nome file: `acronimo_doc_[nome_documentazione].pdf`
  - PDF rigenerati automaticamente alla creazione, modifica (nome/descrizione) o eliminazione
  - Campo `pdf_url` aggiunto alla tabella `documentations` per memorizzare l'URL del PDF

- **Comando Artisan per rigenerazione PDF** - Aggiunto comando `orchestrator:documentation-pdf-generate` per rigenerare tutti i PDF o un PDF specifico:
  ```bash
  php artisan orchestrator:documentation-pdf-generate
  php artisan orchestrator:documentation-pdf-generate --id=11
  ```

- **Link download PDF in Nova** - Aggiunto campo "PDF Download" nella risorsa Nova delle documentazioni che mostra il nome completo del file PDF come link cliccabile, visibile sia nella vista index che detail.

- **Controller PDF aggiornato** - Il controller `DocumentationPdfController` ora serve i PDF salvati invece di generarli on-demand, migliorando l'affidabilità in produzione.

## 📋 Technical Details

### File Modificati
- `app/Models/Documentation.php` - Aggiunta implementazione interfaccia `HasMedia`, aggiunto campo `pdf_url` al fillable
- `app/Http/Controllers/DocumentationPdfController.php` - Modificato per servire PDF salvati invece di generazione on-demand
- `app/Nova/Documentation.php` - Aggiunto campo "PDF Download" con nome completo file
- `app/Providers/AppServiceProvider.php` - Registrato `DocumentationObserver`
- `scripts/deploy_prod.sh` - Aggiunta creazione directory `storage/app/public/documentations`

### File Creati
- `app/Services/DocumentationPdfService.php` - Servizio per generazione PDF con header (logo) e footer (config)
- `app/Jobs/GenerateDocumentationPdfJob.php` - Job asincrono per generazione PDF
- `app/Observers/DocumentationObserver.php` - Observer per generare/eliminare PDF automaticamente
- `app/Console/Commands/GenerateDocumentationPdfs.php` - Comando Artisan per rigenerazione PDF

### Database
- Migrazione: `2025_11_23_104948_add_pdf_url_to_documentations_table.php`
- Tabelle modificate: `documentations` (aggiunto campo `pdf_url`)

## 📝 Notes

- **Compatibilità** - Nessun impatto sul funzionamento esistente, solo miglioramenti
- **Deploy** - Eseguire la migration e rigenerare i PDF esistenti (opzionale):
  ```bash
  php artisan migrate
  php artisan orchestrator:documentation-pdf-generate
  ```
- **Storage** - I PDF sono salvati in `storage/app/public/documentations/` e accessibili tramite link simbolico `public/storage`

