# CLAUDE.md

Guida per Claude Code (claude.ai/code) quando lavora su questo repository.

## Cos'è questo repo

Orchestrator è lo strumento interno di project management di Webmapp: Laravel 12 con **Laravel
Nova** come interfaccia primaria (ogni vista admin è una Resource, Action, Lens o Dashboard Nova).
Gestisce Story, Epic, Milestone, Customer, App, Quote, Task e Deadline, ed espone una API REST per
consumatori esterni — in particolare le skill Claude.

Dove guardare, quando il codice non basta:

- **`app/Nova/`** — l'interfaccia. La gerarchia di progetto è Epic → Milestone → Story; `Story` è
  l'entità centrale, con ciclo di vita a stati e notifiche via mail.
- **`routes/api.php`** — la API REST esterna; `routes/web.php` tiene Nova, il download dei report
  app e le rotte pubbliche firmate.
- **Submodule** — `.gitmodules` ne dichiara due: `wm-package/` (package Laravel condiviso fra i
  progetti Webmapp) e `wm-reports/` (script Python per i PDF dei report app). **Ciò che riguarda un
  submodule si documenta nel submodule**, non qui.
- **`nova-components/`** — componenti Nova custom, fra cui la board Kanban. **Non è un submodule**:
  è una cartella normale del repo, e i `dist/` sono tracciati qui (per questo vale la regola del
  `node --check`).
- **Code stack**: Horizon + Redis (coda dedicata `reports` per la generazione PDF), PostgreSQL +
  PostGIS, Spatie Media Library.

## Comandi

Tutti i comandi PHP girano **dentro il container Docker** `php81_orchestrator`.

| Cosa | Comando |
|---|---|
| Entrare nel container | `docker exec -it php81_orchestrator bash` |
| Migration | `php artisan migrate` |
| Test | vedi [docs/howto/eseguire-i-test.md](docs/howto/eseguire-i-test.md) |
| Pulire le cache | `php artisan config:clear && php artisan optimize` |
| Queue worker (dev locale) | `php artisan queue:work` |
| Horizon (code in produzione) | `bash scripts/launch_horizon.sh` |
| Asset frontend | `npm run dev` / `npm run build` |
| Deploy | `scripts/deploy_dev.sh`, `scripts/deploy_prod.sh` |

## Regole del repo

- **I test girano sul DB `orchestrator_test`, mai su `orchestrator`.** La procedura completa è in
  [docs/howto/eseguire-i-test.md](docs/howto/eseguire-i-test.md).

- **Quando aggiungi un `case` a un enum di stato, aggiungi nello stesso lavoro la riga in `label()`
  e una chiave di traduzione per ogni forma con cui il repo costruisce la chiave** — il nome del
  case (`"To_Present"`, nei Filter Nova), `ucfirst()` del valore (`"Pending_release"`) e la stringa
  scritta a mano in `label()` (`"Pending Release"`). Senza `label()` il dashboard Kanban va in 500; senza le altre chiavi l'utente legge
  il valore grezzo, in silenzio. Quali punti usano quale forma è in
  [docs/knowledge/traduzioni-stati-enum.md](docs/knowledge/traduzioni-stati-enum.md).

- **Quando modifichi un bundle sotto `nova-components/*/dist/`, validalo con `node --check` prima di
  dichiarare il lavoro pronto**, e rollbacka sempre insieme il PHP e il JS corrispondente. Quei file
  sono scritti a mano, non esiste build né lint automatica: il dettaglio è in
  [docs/knowledge/nova-components-bundle.md](docs/knowledge/nova-components-bundle.md).

- **Non leggere, non scrivere e non ricollegare la tabella pivot `story_story`**: è deprecata, la
  sola fonte di verità della relazione padre-figlio è `stories.parent_id` — vedi
  [docs/knowledge/story-parent-child.md](docs/knowledge/story-parent-child.md).

- **Non scrivere `StoryLog` a mano dopo un `save()`/`saveQuietly()` su una Story**: l'override di
  `Story::save()` lo crea già, e un log manuale produce un doppione. Le eccezioni legittime (log
  relazionali e di visualizzazione) sono in
  [docs/knowledge/story-status-e-notifiche.md](docs/knowledge/story-status-e-notifiche.md).

- **L'unico CSS custom caricato da Nova è `public/nova-custom.css`.** `public/css/nova-custom.css`
  esiste, è tracciato in git e non è referenziato da nulla: modificarlo non ha alcun effetto.

- **Quando tocchi la config di `media-library` o l'accesso Nova, ricorda che il `wm-package`
  sovrascrive la configurazione del progetto in fase di *register*** (`packageRegistered()`) e va
  ripristinata nel `register()` di `AppServiceProvider`:
  [docs/knowledge/media-library-path-generator.md](docs/knowledge/media-library-path-generator.md)
  e [docs/knowledge/api-esterne-e-documentazione.md](docs/knowledge/api-esterne-e-documentazione.md).

- **Prima di scrivere un endpoint o cambiare una risposta dell'API, controlla se il contratto è già
  consumato da un client esterno** (le skill Claude e la skill Cowork vivono fuori da questo repo):
  paginazione, campi opt-in e forma della risposta sono breaking change cross-repo, non refactor
  interni.

- **Documentazione, commenti e messaggi di commit sono in italiano.** I termini tecnici restano in
  inglese: commit, branch, merge, gate, build, deploy, review. Nomi di file, slug e identificatori
  seguono la stessa regola.

- **Ogni documento sotto `docs/features/` inizia con `> Ticket: oc:<ID>`**; lo slug di una feature è
  `<ID>-<titolo-in-kebab-case>` e lo scope dei commit è `feat(oc:<ID>): …` / `fix(oc:<ID>): …` /
  `refactor(oc:<ID>): …`.

## Conoscenza

| Argomento | Cosa copre | Pagina |
|---|---|---|
| Story: stati, log e notifiche | override di `Story::save()`, `pending_release`, mail alla creazione e al rilascio, auto-revert Slack | [docs/knowledge/story-status-e-notifiche.md](docs/knowledge/story-status-e-notifiche.md) |
| Relazione padre-figlio fra Story | `stories.parent_id` come fonte unica, pivot deprecato, rollup di tempo e stima | [docs/knowledge/story-parent-child.md](docs/knowledge/story-parent-child.md) |
| Ore stimate ed effettive | le tre misure di "ore effettive", quale leggere, stato reale dei dati, metrica Todo >1g | [docs/knowledge/ore-stimate-ed-effettive.md](docs/knowledge/ore-stimate-ed-effettive.md) |
| Quote: API, PDF e viste Nova | CRUD preventivi, link pubblico firmato, rendering DomPDF, tab e colonne Nova | [docs/knowledge/quote-api-e-pdf.md](docs/knowledge/quote-api-e-pdf.md) |
| Task collegati alle Quote | assegnatario derivato, policy per campo, scoping delle viste Nova | [docs/knowledge/task-e-quote.md](docs/knowledge/task-e-quote.md) |
| Customer: API e validazione | whitelist anti mass-assignment, generazione di `name`, telefoni e backfill P.IVA | [docs/knowledge/customer-api-e-validazione.md](docs/knowledge/customer-api-e-validazione.md) |
| API esterne e documentazione OpenAPI | token Sanctum, endpoint Story e `/me`, override accesso Nova, limiti di Scramble | [docs/knowledge/api-esterne-e-documentazione.md](docs/knowledge/api-esterne-e-documentazione.md) |
| Tag: API e tagging automatico | relazioni morfiche, autorizzazione per ruolo, hook Nova | [docs/knowledge/tag-e-tagging-automatico.md](docs/knowledge/tag-e-tagging-automatico.md) |
| Traduzione degli stati enum | doppia chiave, `match` senza `default` | [docs/knowledge/traduzioni-stati-enum.md](docs/knowledge/traduzioni-stati-enum.md) |
| Nova components: bundle scritti a mano | `node --check`, rollback non atomico, metric-card del Kanban | [docs/knowledge/nova-components-bundle.md](docs/knowledge/nova-components-bundle.md) |
| Sync distribuita delle App multi-shard | identità `(shard, app_id)`, proprietà delle colonne, guardie, report store | [docs/knowledge/sync-shard-app.md](docs/knowledge/sync-shard-app.md) |
| Sync del Google Calendar | job con debounce, cascade demote, coda e fallback | [docs/knowledge/sync-google-calendar.md](docs/knowledge/sync-google-calendar.md) |
| Media Library e allegati | override del wm-package, tre layout su disco, path generator ibrido | [docs/knowledge/media-library-path-generator.md](docs/knowledge/media-library-path-generator.md) |
| Monitoraggio costi Hetzner | token per progetto, cache, prezzi hardcodati | [docs/knowledge/hetzner-monitoring.md](docs/knowledge/hetzner-monitoring.md) |
