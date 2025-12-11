# CHANGELOG MS-1.21.2

**Release Date:** 11/12/2025  
**Version:** MS-1.21.2

## 🐛 Bug Fixes

### Activity Report PDF
- **Formattazione tabelle nei PDF dei report attività** - Risolto problema di formattazione delle tabelle nei PDF generati dai report attività mensili. Le tabelle ora rimangono entro i margini A4 con colonne a larghezza fissa e testo che va a capo automaticamente
- **Rimozione colonna Creator** - Rimossa la colonna "Creator" dalle tabelle dei report attività per ottimizzare lo spazio disponibile
- **Larghezze colonne ottimizzate** - Impostate larghezze fisse per le colonne (ID: 8%, Done At: 12%, Title: 25%, Request: 55%) per garantire che il contenuto rimanga sempre entro i margini del foglio A4
- **Text wrapping migliorato** - Aggiunte proprietà CSS per il wrapping automatico del testo nelle celle, permettendo la visualizzazione completa del contenuto senza troncamento

## 📋 Technical Details

### File Modificati
- `app/Jobs/GenerateActivityReportPdfJob.php` - Aggiornata formattazione tabelle con larghezze fisse, rimossa colonna Creator, aggiunto text wrapping
- `app/Http/Controllers/ActivityReportPdfController.php` - Aggiornata formattazione tabelle con larghezze fisse, rimossa colonna Creator, aggiunto text wrapping

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Compatibilità** - Nessun impatto sul funzionamento esistente, solo miglioramento della formattazione dei PDF
- **Rigenerazione report** - I report esistenti possono essere rigenerati per applicare le nuove formattazioni usando il comando `orchestrator:activity-report-generate`

