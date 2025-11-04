# CHANGELOG MS-1.19.0

**Release Date:** 04/11/2025  
**Version:** MS-1.19.0

## 🚀 New Features

### Filtri Avanzati per Ticket
- **Filtro "Senza Tag"** - Filtro on/off per selezionare tutti i ticket senza tag assegnati in tutte le interfacce AGILE>Tickets>*
- **Filtro "Con Più Tag"** - Filtro on/off per selezionare tutti i ticket con più di un tag assegnato
- **Disponibilità universale** - Filtri disponibili in tutte le risorse ticket (Nuovi, Customers, In progress, Da svolgere, Test, Backlog, Archiviati)

### Sistema Organizzazioni
- **Gestione organizzazioni** - Nuova tabella `organizations` per raggruppare utenti
- **Relazione many-to-many** - Gli utenti possono appartenere a più organizzazioni
- **Nova Resource** - Interfaccia completa per gestione organizzazioni (solo Admin)
- **Filtro per organizzazioni** - Filtro nella risorsa Users per ricerca per organizzazione
- **Action bulk** - Possibilità di aggiornare le organizzazioni di più utenti contemporaneamente
- **Colonna organizzazioni** - Visualizzazione organizzazioni nella vista index degli utenti
- **Collegamento a ticket** - Tracking attività per organizzazione attraverso creator_id

### Dashboard Activity Management
- **Timetable (Activity User)** - Dashboard per visualizzare attività giornaliera aggregata per tutti gli utenti
  - Selezione intervallo temporale (default: ultimi 30 giorni)
  - Raggruppamento per utente e giorno
  - Colonne: Utente, Total Tickets, Total Time Spent, Average Time per Ticket, Min/Max Duration, Data
  - Riepilogo giornaliero con totali per giorno
  - Riepilogo periodo con statistiche aggregate
  
- **Activity Tags** - Dashboard per attività raggruppata per tag
  - Filtro per nome tag (ricerca LIKE)
  - Statistiche: Total Tickets, Total Time Spent, Average Time, Min/Max Duration
  - Ordinamento alfabetico per nome tag
  
- **Activity Customer** - Dashboard per attività raggruppata per cliente (creator_id)
  - Filtro per nome cliente
  - Statistiche complete per cliente
  
- **Activity Organizations** - Dashboard per attività raggruppata per organizzazione
  - Filtro per nome organizzazione
  - Raggruppamento attraverso story -> creator -> organization

### Dashboard Activity Details
- **Activity Tags Details** - Vista dettagliata dei ticket per tag specifico
  - Selezione tag e intervallo temporale
  - Lista ticket in ordine inverso temporale
  - Colonne: ID, Ticket, Status, Last Activity, Total Time, Assigned To, Creator
  - Riepilogo statistiche per il tag selezionato
  
- **Activity Customer Details** - Vista dettagliata dei ticket per cliente specifico
  - Selezione cliente e intervallo temporale
  - Lista ticket con statistiche complete
  
- **Activity Organizations Details** - Vista dettagliata dei ticket per organizzazione specifica
  - Selezione organizzazione e intervallo temporale
  - Lista ticket con statistiche complete

### Nuova Risorsa Ticket
- **NewStory Resource** - Nuova risorsa per visualizzare tutti i ticket in stato "new"
- **Posizionamento menu** - Prima voce nel menu AGILE>Tickets
- **Filtri standard** - Tutti i filtri disponibili compresi i nuovi filtri tag

## 🔧 Improvements

### Menu Management
- **Nuovo blocco "Management"** - Visibile a Manager e Admin
- **Menu Statistics** - Raggruppamento dashboard Activity Tags, Customer, Organizations
- **Menu Details** - Raggruppamento dashboard Details per tag, customer, organizzazioni
- **Rinomina voci menu** - "Activity User" → "Timetable", rimozione "Activity" dai nomi delle statistiche

### Kanban-2
- **Tabella TODO migliorata** - Visualizza ticket con status "todo" e "assigned", ordinati con todo per primi
- **Colonna Status** - Aggiunta colonna status per distinguere visivamente i ticket
- **Ordine ottimizzato** - Ticket todo visualizzati prima degli assigned

### Template Release
- **Template Minor Release** - Nuovo template per creare minor release
- **Template Patch Release** - Nuovo template per creare patch release
- **Formato email TXT** - Tutti i template includono versione email in formato TXT per invio via client email

## 📋 Technical Details

### File Creati
- `app/Nova/Filters/StoryWithoutTagsFilter.php` - Filtro per ticket senza tag
- `app/Nova/Filters/StoryWithMultipleTagsFilter.php` - Filtro per ticket con più tag
- `app/Nova/NewStory.php` - Nuova risorsa per ticket in stato "new"
- `app/Nova/Organization.php` - Resource per gestione organizzazioni
- `app/Nova/Filters/OrganizationFilter.php` - Filtro per organizzazioni in Users
- `app/Nova/Actions/UpdateOrganizations.php` - Action per aggiornare organizzazioni in bulk
- `app/Nova/Dashboards/ActivityUser.php` - Dashboard Timetable
- `app/Nova/Dashboards/ActivityTags.php` - Dashboard Activity Tags
- `app/Nova/Dashboards/ActivityCustomer.php` - Dashboard Activity Customer
- `app/Nova/Dashboards/ActivityOrganizations.php` - Dashboard Activity Organizations
- `app/Nova/Dashboards/ActivityTagsDetails.php` - Dashboard Details Tags
- `app/Nova/Dashboards/ActivityCustomerDetails.php` - Dashboard Details Customer
- `app/Nova/Dashboards/ActivityOrganizationsDetails.php` - Dashboard Details Organizations
- `app/Models/Organization.php` - Model per organizzazioni
- `database/migrations/2025_11_03_210457_create_organizations_table.php` - Migrazione tabelle organizations
- `database/migrations/2025_11_03_210514_create_organization_user_table.php` - Migrazione tabella pivot
- `.cursor/templates/minor_release.md` - Template minor release
- `.cursor/templates/patch_release.md` - Template patch release
- `resources/views/activity-user-selector.blade.php` - Selector per Activity User
- `resources/views/activity-user-table.blade.php` - Tabella Activity User
- `resources/views/activity-tags-selector.blade.php` - Selector per Activity Tags
- `resources/views/activity-tags-table.blade.php` - Tabella Activity Tags
- `resources/views/activity-customer-selector.blade.php` - Selector per Activity Customer
- `resources/views/activity-customer-table.blade.php` - Tabella Activity Customer
- `resources/views/activity-organizations-selector.blade.php` - Selector per Activity Organizations
- `resources/views/activity-organizations-table.blade.php` - Tabella Activity Organizations
- `resources/views/activity-tags-details-selector.blade.php` - Selector per Tags Details
- `resources/views/activity-tags-details-table.blade.php` - Tabella Tags Details
- `resources/views/activity-customer-details-selector.blade.php` - Selector per Customer Details
- `resources/views/activity-customer-details-table.blade.php` - Tabella Customer Details
- `resources/views/activity-organizations-details-selector.blade.php` - Selector per Organizations Details
- `resources/views/activity-organizations-details-table.blade.php` - Tabella Organizations Details

### File Modificati
- `app/Nova/Story.php` - Aggiunti nuovi filtri tag
- `app/Nova/CustomerStory.php` - Aggiunti nuovi filtri tag
- `app/Nova/ArchivedStories.php` - Aggiunti nuovi filtri tag
- `app/Nova/AssignedToMeStory.php` - Aggiunti nuovi filtri tag
- `app/Nova/ToBeTestedStory.php` - Aggiunti nuovi filtri tag
- `app/Nova/User.php` - Aggiunti campo organizzazioni, filtro e action
- `app/Nova/Dashboards/Kanban2.php` - Migliorata tabella TODO
- `app/Models/User.php` - Aggiunta relazione organizations
- `app/Providers/NovaServiceProvider.php` - Aggiunto blocco Management, nuove dashboard, menu riorganizzati
- `routes/web.php` - Aggiunte route per filtri dashboard activity
- `.cursor/templates/major_release.md` - Aggiunto formato TXT email

### Database
- Migrazione: `2025_11_03_210457_create_organizations_table.php` - Tabella `organizations` con campo `name`
- Migrazione: `2025_11_03_210514_create_organization_user_table.php` - Tabella pivot `organization_user` con unique constraint su (organization_id, user_id)

## 📝 Notes

- **Nessuna migrazione breaking** - Le nuove tabelle sono opzionali e non impattano il funzionamento esistente
- **Dashboard Activity** - Tutte le dashboard utilizzano `users_stories_log` per calcolare le statistiche
- **Filtri Tag** - Disponibili come BooleanFilter (on/off) in tutte le risorse Story
- **Organizzazioni** - Sistema opzionale, può essere configurato gradualmente senza impattare utenti esistenti

