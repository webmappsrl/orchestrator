# CHANGELOG MS-1.21.4

**Release Date:** 12/12/2025  
**Version:** MS-1.21.4

## 🔧 Improvements

### Dashboard Customer
- Aggiunta nuova dashboard customer con 3 card informative:
  - Informazioni login (nome, email, ultimo accesso)
  - Numero di ticket da completare (stati: Nuovo, Backlog, Assegnato, Todo, In Corso, Da Testare, Problema, In Attesa)
  - Numero di progetti FSP in cui l'utente è coinvolto

### Menu Navigation
- Menu ADMIN nascosto ai customer per migliorare la UX
- Menu HELP reso visibile anche ai customer
- Voce Changelog nel menu HELP nascosta ai customer
- Label "Customer Dashboard" cambiato in "Dashboard"
- Label "Activity Reports" cambiato in "Report"
- Voce "Ticket archiviati" spostata alla fine del menu CUSTOMER
- Voce "Documentazione" rimossa dal menu CUSTOMER (rimane disponibile nel menu HELP)

## 📋 Technical Details

### File Modificati
- `app/Nova/Dashboards/CustomerDashboard.php` - Aggiunta dashboard con 3 card e metodo name() per label personalizzato
- `app/Nova/CustomerActivityReport.php` - Modificato label da "Activity Reports" a "Report"
- `app/Providers/NovaServiceProvider.php` - Modifiche al menu: visibilità sezioni, ordine voci, condizioni canSee()
- `resources/views/customer-dashboard/login-info.blade.php` - Nuova view per card informazioni login
- `resources/views/customer-dashboard/tickets-to-complete.blade.php` - Nuova view per card ticket da completare
- `resources/views/customer-dashboard/fsp-projects.blade.php` - Nuova view per card progetti FSP

### Database
- Nessuna modifica al database

