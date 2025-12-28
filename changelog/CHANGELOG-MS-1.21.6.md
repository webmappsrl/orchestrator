# CHANGELOG MS-1.21.6

**Release Date:** 28/12/2025  
**Version:** MS-1.21.6

## 🔧 Improvements

### Gestione Archiviazione Documenti
- Aggiunto campo `google_drive_url` alla tabella users per configurare l'URL della cartella Google Drive per l'archiviazione dei documenti
- Campo visibile e modificabile solo dagli amministratori nella risorsa User
- Aggiunta card "Archiviazione" nella dashboard customer con accesso diretto alla cartella Google Drive
- Messaggio informativo quando la cartella non è configurata con istruzioni per contattare l'amministrazione
- Supporto multilingua (italiano/inglese) per i messaggi della card

## 📋 Technical Details

### File Modificati
- `app/Models/User.php` - Aggiunto campo `google_drive_url` all'array $fillable
- `app/Nova/User.php` - Aggiunto campo Google Drive URL visibile solo agli admin, con messaggio quando vuoto
- `app/Nova/Dashboards/CustomerDashboard.php` - Aggiunto metodo storageCard() per la card Archiviazione
- `resources/views/customer-dashboard/storage.blade.php` - Nuova view per card Archiviazione con link Google Drive
- `lang/it/messages.php` - Aggiunte traduzioni italiane per i messaggi della card
- `lang/en/messages.php` - Aggiunte traduzioni inglesi per i messaggi della card

### Database
- Migrazione: `2025_12_28_132357_add_google_drive_url_to_users_table.php`
- Tabelle modificate: `users` (aggiunto campo `google_drive_url` di tipo string nullable)

