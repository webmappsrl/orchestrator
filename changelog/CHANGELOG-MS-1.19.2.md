# CHANGELOG MS-1.19.2

**Release Date:** 06/11/2025  
**Version:** MS-1.19.2

## 🔧 Improvements

### Gestione Organizzazioni
- Creato seeder per generare automaticamente organizzazioni OTCO/SO, GR e GR per regione
- Aggiunta colonna "Utenti" nella risorsa Nova Organization per visualizzare il conteggio degli utenti associati
- Aumentato limite di visualizzazione utenti a 200 nella relazione BelongsToMany nella pagina di dettaglio organizzazione

## 📋 Technical Details

### File Modificati
- `database/seeders/OrganizationSeeder.php` - Creato nuovo seeder per organizzazioni
- `app/Nova/Organization.php` - Aggiunta colonna conteggio utenti e configurazione query
- `app/Nova/User.php` - Aumentato limite visualizzazione a 200 record nelle relazioni

### Database
- Nessuna migrazione richiesta
- Utilizza tabelle esistenti: `organizations`, `organization_user`

