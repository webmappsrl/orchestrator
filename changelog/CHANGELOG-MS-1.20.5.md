# CHANGELOG MS-1.20.5

**Release Date:** 23/11/2025  
**Version:** MS-1.20.5

## 🔧 Improvements

### Organizzazioni
- **Ricerca utenti nell'attach** - Abilitata la ricerca degli utenti quando si attaccano utenti a un'organizzazione. Ora è possibile cercare utenti per nome, email o ID nella pagina di attach degli utenti alle organizzazioni.

## 📋 Technical Details

### File Modificati
- `app/Nova/Organization.php` - Aggiunto metodo `->searchable()` al campo `BelongsToMany::make('Users')`

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Miglioramento UX** - La ricerca degli utenti rende più facile trovare e attaccare utenti alle organizzazioni, specialmente quando ci sono molti utenti nel sistema
- **Compatibilità** - Nessun impatto sul funzionamento del sistema, solo miglioramento dell'interfaccia

