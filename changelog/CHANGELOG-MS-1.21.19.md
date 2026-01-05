# CHANGELOG MS-1.21.19

**Release Date:** 05/01/2026  
**Version:** MS-1.21.19

## 🐛 Bug Fixes

### Nova Resources
- **Risolto errore "Only arrays and Traversables can be unpacked" in StoryShowedByCustomer** - Corretto errore che si verificava quando si accedeva alla risorsa StoryShowedByCustomer con resource null o status null. Aggiunti controlli per verificare che la resource esista prima di accedere alle sue proprietà e per assicurarsi che getStatusLabel() restituisca sempre un array prima di usarlo con lo spread operator

## 📋 Technical Details

### File Modificati
- `app/Nova/StoryShowedByCustomer.php` - Aggiunto controllo per verificare che `$this->resource` esista prima di accedere a `status`. Gestito il caso in cui `status` sia null. Aggiunto controllo esplicito per assicurarsi che `getStatusLabel()` restituisca sempre un array prima di usarlo con lo spread operator

### Database
- Nessuna modifica al database

