# CHANGELOG MS-1.21.0

**Release Date:** 25/11/2025  
**Version:** MS-1.21.0

## 🚀 New Features

### Kanban2 Dashboard Reorganization
- **Nuove risorse Nova per stati ticket** - Aggiunte tre nuove risorse dedicate per visualizzare tutti i ticket con stati specifici:
  - `ProblemStory` - Mostra tutti i ticket con status "Problemi"
  - `TestStory` - Mostra tutti i ticket con status "Da Testare" (non filtrati per tester)
  - `WaitingStory` - Mostra tutti i ticket con status "In Attesa"
- **Riorganizzazione menu SCRUM** - Creato sottomenu "SCRUM" (collapsed by default) nella sezione AGILE contenente:
  - Dashboard Kanban2
  - Le tre nuove risorse (Da Testare, In Attesa, Problemi)
  - Link esterni SCRUM e MEET
  - Dashboard Activity
- **Semplificazione Kanban2** - La dashboard Kanban2 ora mostra solo:
  - "Cosa ho fatto ieri" (attività recenti)
  - "Cosa farò oggi" (todo/assigned)

## 🔧 Improvements

### Info Column Enhancements
- **Label "Tag:" aggiunto** - Aggiunto il label "Tag:" prima di ogni tag nella colonna Informazioni con styling arancione coerente
- **Tester link nella colonna Informazioni** - Aggiunto link al tester nella colonna Informazioni (solo se presente) con colore verde scuro per distinguerlo dal Creator

## 📋 Technical Details

### File Creati
- `app/Nova/ProblemStory.php` - Nuova risorsa Nova per ticket con status "Problemi"
- `app/Nova/TestStory.php` - Nuova risorsa Nova per ticket con status "Da Testare"
- `app/Nova/WaitingStory.php` - Nuova risorsa Nova per ticket con status "In Attesa"

### File Modificati
- `app/Nova/Dashboards/Kanban2.php` - Rimossi card Problem, Waiting, Test e experimental; mantenute solo recentActivitiesCard e todoAndAssignedCard; aggiornato titolo card TODO a "Cosa farò oggi"
- `app/Providers/NovaServiceProvider.php` - Riorganizzato menu AGILE con sottomenu SCRUM contenente le nuove risorse
- `app/Traits/fieldTrait.php` - Aggiunto metodo `getTesterLink()` e label "Tag:" nella colonna Informazioni

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Organizzazione migliorata** - Il menu SCRUM ora raggruppa logicamente tutte le risorse e dashboard relative allo sviluppo agile
- **Visualizzazione semplificata** - La dashboard Kanban2 è più focalizzata sulle attività giornaliere dell'utente
- **Informazioni più complete** - La colonna Informazioni ora mostra anche il tester quando presente, migliorando la visibilità delle assegnazioni
- **Compatibilità** - Nessun impatto sul funzionamento esistente, solo miglioramenti organizzativi e di visualizzazione

