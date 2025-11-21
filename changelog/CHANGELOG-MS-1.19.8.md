# CHANGELOG MS-1.19.8

**Release Date:** 21/11/2025  
**Version:** MS-1.19.8

## 🔧 Improvements

### Interfaccia Utente - Traduzioni e Validazione Campi
- **Traduzione italiana campo "Waiting Reason"** - Aggiunta traduzione italiana "Motivo dell'attesa" per il campo "Waiting Reason" nell'interfaccia di edit dei ticket
- **Traduzione italiana campo "Problem Reason"** - Aggiunta traduzione italiana "Motivo del problema" per il campo "Problem Reason" nell'interfaccia di edit dei ticket
- **Helper text migliorato per Waiting Reason e Problem Reason** - Migliorati gli helper text per i campi "Waiting Reason" e "Problem Reason" con descrizioni più dettagliate ed esempi di cosa scrivere nel campo, tradotti in italiano e inglese

### Validazione - Campi Condizionali
- **Validazione condizionale Waiting Reason** - Il campo "Waiting Reason" è ora obbligatorio quando lo status del ticket è "Waiting", con messaggio di errore personalizzato che indica che il campo è richiesto per impostare il ticket in attesa
- **Validazione condizionale Problem Reason** - Il campo "Problem Reason" è ora obbligatorio quando lo status del ticket è "Problem", con messaggio di errore personalizzato che indica che il campo è richiesto per impostare il ticket in stato problema

## 📋 Technical Details

### File Modificati
- `app/Traits/fieldTrait.php` - Aggiunta validazione condizionale con `rules()` che rende obbligatori i campi "Waiting Reason" e "Problem Reason" quando lo status corrispondente è selezionato. Migliorati helper text con descrizioni più dettagliate ed esempi
- `lang/en/validation.php` - Aggiunti messaggi di errore personalizzati per i campi `waiting_reason` e `problem_reason` quando sono required. Aggiunti attributi personalizzati per i nomi dei campi nella validazione
- `lang/vendor/nova/it.json` - Aggiunta traduzione italiana per "Waiting Reason" ("Motivo dell'attesa"), "Problem Reason" ("Motivo del problema") e helper text migliorati tradotti in italiano

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Validazione dinamica** - La validazione viene applicata dinamicamente in base al valore del campo "status", garantendo che i campi siano obbligatori solo quando necessario
- **Messaggi di errore chiari** - I messaggi di errore sono personalizzati e indicano chiaramente che il campo è obbligatorio quando lo status è "Waiting" o "Problem", tradotti sia in italiano che in inglese
- **Backward Compatible** - Completamente retrocompatibile, nessuna breaking change. La validazione viene applicata solo quando lo status corrispondente è selezionato
- **UX migliorata** - Gli helper text forniscono esempi chiari di cosa scrivere nel campo, migliorando l'esperienza utente e riducendo gli errori

