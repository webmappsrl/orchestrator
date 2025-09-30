# CHANGELOG MS-1.16.2

**Release Date:** 30/09/2025  
**Version:** MS-1.16.2

## 🚀 New Features

### Nova Action per Creazione Progetti FRP
- **Aggiunta Nova Action "Crea Progetto FRP"** nelle opportunità di fundraising
- **Accesso limitato** a utenti con ruolo `fundraising` e `admin`
- **Disponibile** sia nella lista che nella pagina di dettaglio delle opportunità
- **Reindirizzamento automatico** alla risorsa del progetto FRP creato

### Gestione Partner nei Progetti FRP
- **Campo BelongsToMany "Partner"** nella risorsa FundraisingProject
- **Visualizzazione** dei partner attuali nella pagina di dettaglio
- **Gestione completa** dei partner tramite pulsanti Attach/Detach
- **Ricerca** tra gli utenti disponibili per aggiungere partner

## 🔧 Improvements

### Interfaccia Nova
- **Campo "Idea Progettuale"** ora sempre espanso nella pagina di dettaglio
- **Campo "Capofila"** reso ricercabile per facilitare la selezione tra i 556 customer
- **Configurazione migliorata** dei campi BelongsToMany per una migliore UX

### Autorizzazioni e Policy
- **Policy UserPolicy aggiornata** per permettere agli utenti fundraising di gestire i partner
- **Controlli specifici** per le operazioni attach/detach sui partner
- **Sicurezza mantenuta** per evitare accesso non autorizzato alla gestione utenti

## 🐛 Bug Fixes

### Nova Actions
- **Risolto errore** `Class "App\Nova\Actions\Capofila" not found`
- **Risolto errore** `Method Laravel\Nova\Fields\Select::multiple does not exist`
- **Corretti metodi** `authorizedToSee` e `authorizedToRun` con type hints corretti
- **Risolto errore** `SQLSTATE[23502]: Not null violation` per il campo `lead_user_id`

### Policy e Autorizzazioni
- **Risolto errore** `Cannot redeclare App\Policies\UserPolicy::view()`
- **Corretti metodi** attach/detach per le relazioni BelongsToMany
- **Migliorata gestione** delle autorizzazioni per utenti fundraising

## 📋 Technical Details

### File Modificati
- `app/Nova/Actions/CreateProjectFromOpportunity.php` - Nuova action per creazione progetti FRP
- `app/Nova/FundraisingOpportunity.php` - Aggiunta action alla risorsa
- `app/Nova/FundraisingProject.php` - Configurazione campo Partner e miglioramenti UI
- `app/Policies/FundraisingProjectPolicy.php` - Metodi attach/detach per partner
- `app/Policies/UserPolicy.php` - Autorizzazioni specifiche per gestione partner
- `config/app.php` - Aggiornamento versione a MS-1.16.2

### Database
- **Nessuna migrazione** richiesta
- **Utilizzo** delle tabelle esistenti `fundraising_projects` e `fundraising_project_partners`

### Dependencies
- **Nessuna dipendenza** aggiunta
- **Utilizzo** di componenti Nova esistenti

## 🎯 User Impact

### Per Utenti Fundraising (es. Sara Mariani)
- ✅ **Possono creare** progetti FRP dalle opportunità di fundraising
- ✅ **Possono gestire** i partner dei progetti FRP
- ✅ **Interfaccia migliorata** per la selezione dei customer
- ❌ **NON possono accedere** alla gestione completa utenti
- ❌ **NON possono vedere** il menu ADMIN

### Per Admin
- ✅ **Accesso completo** a tutte le funzionalità
- ✅ **Gestione completa** di progetti FRP e partner
- ✅ **Controllo totale** dell'applicazione

## 🔄 Migration Notes

### Nessuna Migrazione Richiesta
Questa release non richiede migrazioni del database. Tutte le funzionalità utilizzano le strutture esistenti.

### Configurazione
- **Nessuna configurazione** aggiuntiva richiesta
- **Policy** configurate automaticamente
- **Nova Actions** disponibili immediatamente

## 📚 Documentation

### Per Sviluppatori
- **Nova Actions** implementate seguendo le best practices
- **Policy** configurate con controlli granulari
- **Relazioni BelongsToMany** gestite correttamente

### Per Utenti Finali
- **Workflow** per creazione progetti FRP documentato
- **Gestione partner** intuitiva tramite interfaccia Nova
- **Autorizzazioni** chiare e limitate per ruolo

## 🚀 Next Steps

### Possibili Miglioramenti Futuri
- **Notifiche email** per creazione progetti FRP
- **Dashboard** dedicata per progetti fundraising
- **Report** e statistiche sui progetti
- **Integrazione** con sistemi esterni di fundraising

---

**Note:** Questa release migliora significativamente la gestione dei progetti FRP e dei partner, mantenendo la sicurezza e le autorizzazioni appropriate per ogni ruolo utente.
