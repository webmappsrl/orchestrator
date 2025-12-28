# CHANGELOG MS-1.21.5

**Release Date:** 28/12/2025  
**Version:** MS-1.21.5

## 🔧 Improvements

### Dashboard Customer
- Aggiunta nuova card "Documentazione e contatti" nella dashboard customer
- Aggiunte icone emoji ai titoli di tutte le card della dashboard
- Migliorato stile dei link: trasformati in bottoni colorati più visibili
- Aggiunta sezione "Contatti" nella card Documentazione con link per creare nuovo ticket

### Menu Navigation
- Cambiata voce menu "Cliente > Nuovi" in "Cliente > Nuovo ticket" per maggiore chiarezza

### Documentazioni
- I customer vedono solo le documentazioni con category=customer nella risorsa Documentazioni
- Nascosta colonna "tags" dalla vista index delle documentazioni
- Nascosta colonna "category" dalla vista index per i customer

## 📋 Technical Details

### File Modificati
- `app/Nova/Dashboards/CustomerDashboard.php` - Aggiunto metodo documentationCard() e inserita come seconda card
- `app/Nova/Documentation.php` - Modificato indexQuery() per filtrare per category customer, nascoste colonne tags e category dall'index per customer
- `app/Providers/NovaServiceProvider.php` - Cambiata voce menu da "Nuovi" a "Nuovo ticket"
- `resources/views/customer-dashboard/documentation.blade.php` - Nuova view per card Documentazione con sezione Contatti
- `resources/views/customer-dashboard/login-info.blade.php` - Aggiunta icona emoji al titolo
- `resources/views/customer-dashboard/tickets-to-complete.blade.php` - Aggiunta icona emoji al titolo, migliorato stile link
- `resources/views/customer-dashboard/fsp-projects.blade.php` - Aggiunta icona emoji al titolo, migliorato stile link

### Database
- Nessuna modifica al database

