# CHANGELOG MS-1.19.7

**Release Date:** 21/11/2025  
**Version:** MS-1.19.7

## 🐛 Bug Fixes

### File Upload - Validazione e Messaggi di Errore
- **Messaggi di errore personalizzati per upload file** - Migliorati i messaggi di errore per file non validi, ora includono esplicitamente i file audio e mostrano la dimensione massima consentita in modo chiaro
- **Validazione dimensione file rispetta limiti PHP ini** - La validazione della dimensione massima dei file ora rispetta automaticamente i limiti di PHP ini (`upload_max_filesize` e `post_max_size`), garantendo che la configurazione non permetta upload superiori a quanto consentito da PHP

## 🔧 Improvements

### Upload File - Supporto Audio e Configurazione Dinamica
- **Supporto file audio per verbalizzazione** - Aggiunto supporto per il caricamento di file audio (MP3, M4A, WAV, OGG, AAC, FLAC, MP4) nell'interfaccia di edit dei ticket per permettere la verbalizzazione
- **Configurazione dinamica tipi di file** - I tipi di file consentiti sono ora completamente configurabili tramite `config/orchestrator.php` con possibilità di override tramite variabili `.env`, organizzati per categorie (documenti, immagini, audio)
- **Helper text dinamico** - L'helper text del campo upload ora mostra dinamicamente i tipi di file consentiti organizzati per categoria e la dimensione massima effettiva (considerando i limiti PHP ini)
- **Calcolo dimensione massima intelligente** - Il sistema calcola automaticamente la dimensione massima effettiva prendendo il minimo tra la configurazione applicazione, `upload_max_filesize` e `post_max_size` di PHP, garantendo che la validazione funzioni correttamente in tutti gli ambienti

## 📋 Technical Details

### File Modificati
- `app/Nova/Story.php` - Aggiunti metodi helper statici `getEffectiveMaxFileSize()`, `parsePhpIniSize()`, `getDocumentsMimetypesRule()`, `getDocumentsHelpText()` per gestire configurazione dinamica e validazione. Aggiornati campi Files in `fieldsInEdit()` e `fieldsInDetails()` per usare la configurazione dinamica
- `app/Models/Story.php` - Aggiornato `registerMediaCollections()` per accettare anche i formati audio dalla configurazione dinamica
- `config/orchestrator.php` - Aggiunta configurazione `story_allowed_file_types` e `story_allowed_mime_types` organizzate per categorie (documents, images, audio), con `story_max_file_size` configurabile tramite `.env`
- `lang/en/validation.php` - Aggiunti messaggi di errore personalizzati per il campo `documents` per validazione `mimetypes` e `max` con informazioni chiare sui tipi consentiti e dimensione massima
- `.env-example` - Aggiunti esempi di configurazione per tipi di file consentiti e dimensione massima

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Compatibilità PHP** - Il sistema rispetta automaticamente i limiti di PHP ini, quindi se `upload_max_filesize` è 2MB, anche se la configurazione permette 10MB, il limite effettivo sarà 2MB
- **Configurazione flessibile** - I tipi di file possono essere personalizzati per ambiente tramite variabili `.env`, permettendo configurazioni diverse per sviluppo, staging e produzione
- **Backward Compatible** - Completamente retrocompatibile, nessuna breaking change. I valori di default includono tutti i tipi di file precedentemente supportati più i nuovi formati audio
- **Validazione robusta** - La validazione controlla sia il tipo MIME che la dimensione del file, con messaggi di errore chiari e informativi per l'utente

