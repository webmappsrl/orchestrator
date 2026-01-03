# CHANGELOG MS-1.21.8

**Release Date:** 03/01/2026  
**Version:** MS-1.21.8

## 🔧 Improvements

### Sistema Changelog Dinamico
- Implementato sistema dinamico per la visualizzazione del changelog in Nova
- Dashboard Changelog che legge automaticamente i file CHANGELOG dalla directory `changelog/`
- Organizzazione automatica delle release per minor version (es. 1.21.x)
- Creazione automatica di dashboard Nova per ogni minor release
- Conversione automatica markdown → HTML per la visualizzazione
- Menu "Help > Changelog" che punta automaticamente all'ultima minor release disponibile
- Le patch vengono automaticamente raggruppate nella loro minor release corrispondente

### Documentazione
- Aggiornati i template di release (major, minor, patch) per riflettere il nuovo sistema dinamico
- Rimossi riferimenti a modifiche manuali della dashboard changelog
- Aggiunta documentazione sul funzionamento del sistema ChangelogService

## 📋 Technical Details

### File Creati
- `app/Services/ChangelogService.php` - Servizio per la gestione e organizzazione dei changelog
- `app/Nova/Dashboards/ChangelogMinorRelease.php` - Dashboard dinamica per ogni minor release
- `app/Http/Controllers/ChangelogController.php` - Controller per le route web del changelog (se necessario)
- `resources/views/changelog-dashboard-index.blade.php` - View per il menu delle minor release
- `resources/views/changelog-dashboard-minor-release.blade.php` - View per visualizzare le patch di una minor release
- `resources/views/changelog-dashboard-unified.blade.php` - View unificata per il changelog
- `resources/views/changelog-error.blade.php` - View per errori nel changelog
- `resources/views/changelog-redirect.blade.php` - View per redirect automatici
- `resources/views/changelog/index.blade.php` - View per route web del changelog
- `resources/views/changelog/minor-release.blade.php` - View per route web minor release

### File Modificati
- `app/Nova/Dashboards/Changelog.php` - Aggiornata per utilizzare ChangelogService
- `app/Providers/NovaServiceProvider.php` - Registrazione dinamica delle dashboard minor release e aggiornamento menu
- `routes/web.php` - Aggiunte route per il changelog (se necessario)
- `.cursor/templates/major_release.md` - Aggiornato per sistema dinamico
- `.cursor/templates/minor_release.md` - Aggiornato per sistema dinamico
- `.cursor/templates/patch_release.md` - Aggiornato per sistema dinamico

### Dependencies
- Aggiunta dipendenza `league/commonmark` per la conversione markdown → HTML

## 📝 Notes

- Il sistema changelog è ora completamente dinamico: basta creare un file `CHANGELOG-MS-X.Y.Z.md` nella directory `changelog/` e verrà automaticamente incluso nella dashboard
- Non è più necessario modificare manualmente i file di view per aggiungere nuove release
- Le patch vengono automaticamente raggruppate nella loro minor release corrispondente
- Dopo aver creato un nuovo file CHANGELOG, eseguire `docker-compose exec phpfpm php artisan optimize:clear` per aggiornare la cache

