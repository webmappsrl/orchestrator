# CHANGELOG MS-1.21.7

**Release Date:** 28/12/2025  
**Version:** MS-1.21.7

## 🔧 Improvements

### Gestione Budget Google Drive
- Aggiunto campo `google_drive_budget_url` alla tabella users per configurare l'URL della cartella Google Drive per l'archiviazione dei documenti del budget
- Campo visibile e modificabile solo dagli amministratori nella risorsa User
- Aggiunta card "Budget" nella dashboard customer con accesso diretto alla cartella Google Drive del budget
- Messaggio informativo quando la cartella non è configurata con istruzioni per contattare l'amministrazione
- Supporto multilingua (italiano/inglese) per i messaggi della card

## 📋 Technical Details

### File Modificati
- `app/Models/User.php` - Aggiunto campo `google_drive_budget_url` all'array $fillable
- `app/Nova/User.php` - Aggiunto campo Google Drive Budget URL visibile solo agli admin, con messaggio quando vuoto
- `app/Nova/Dashboards/CustomerDashboard.php` - Aggiunto metodo budgetCard() per la card Budget
- `resources/views/customer-dashboard/budget.blade.php` - Nuova view per card Budget con link Google Drive
- `lang/it/messages.php` - Aggiunte traduzioni italiane per i messaggi della card
- `lang/en/messages.php` - Aggiunte traduzioni inglesi per i messaggi della card

### Database
- Migrazione: `2025_12_28_133645_add_google_drive_budget_url_to_users_table.php`
- Tabelle modificate: `users` (aggiunto campo `google_drive_budget_url` di tipo string nullable)

