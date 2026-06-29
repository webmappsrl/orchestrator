> Ticket: oc:8123

# Team Performance Analytics

## Cosa cambia

Una dashboard Nova custom con componente Vue che permette di selezionare un developer e un quarter e vedere: tutti i suoi ticket Bug/Feature chiusi in quel periodo con le metriche per singolo ticket (cycle time, reopen, on-time, commit, PR, change requests), più un aggregato finale con confronto vs media aziendale.

Sostituisce la dashboard con 4 Partition e la DeveloperPerformanceLens — un unico punto di accesso.

## Perché

Il management e ogni developer devono poter misurare la qualità del lavoro svolto in modo oggettivo, basandosi su dati immutabili. Solo ticket Bug e Feature sono considerati perché rappresentano il lavoro di sviluppo effettivo.

## Requisiti

- [ ] Dashboard Nova custom con componente Vue (pattern kanban-card)
- [ ] Selettore developer (dropdown con tutti gli utenti con ruolo `developer`) + selettore quarter (Q1–Q4 + anno)
- [ ] Ogni developer vede solo se stesso; admin e manager vedono tutti
- [ ] Tabella ticket: solo Bug e Feature con status `done` o `released` nel quarter selezionato
- [ ] Per ogni ticket: nome, tipo, cycle time (ore), reopen count, on-time (✓/✗/—), commit count, PR count, change requests count
- [ ] Riga aggregato in fondo: medie del developer vs media aziendale stesso quarter
- [ ] Dati GitHub integrati nel ticket, non in una sezione separata
- [ ] Metriche con dati insufficienti → null / "—", mai 0

## Rischi

- Componente Vue custom richiede build step (`npm run build`) — stesso pattern kanban-card, già risolto nel progetto
- N+1 queries se non si usa cache in-memory per StoryLog — mitigato con `$logCache` statico in `StoryMetricsCalculator`
- Rate limit GitHub API durante il backfill — mitigato con delay staggerato nel command

## Out of scope

- Ticket di tipo Scrum, Task, Support — esclusi (non rappresentano lavoro di sviluppo)
- Vista "tutti i developer in una tabella" — rimandato a ciclo successivo
- Trend storici multi-quarter — rimandato a ciclo successivo
- Report PDF — rimandato a ciclo successivo (il job esiste ma non è esposto nella nuova dashboard)

## Moduli toccati

**Da rimuovere/sostituire:**
- `app/Nova/Metrics/TeamPerformance/` (4 Partition metrics) — rimossi
- `app/Nova/Lenses/DeveloperPerformanceLens.php` — rimossa
- `app/Nova/Dashboards/TeamPerformance.php` — riscritto come wrapper del componente Vue

**Da creare:**
- `nova-components/team-performance/` — componente Vue custom (package Nova)
- `app/Http/Controllers/Nova/TeamPerformanceController.php` — API endpoint per i dati
- `routes/nova.php` o voce in `NovaServiceProvider` — registrazione route

**Già esistenti e riutilizzati:**
- `app/Services/Metrics/StoryMetricsCalculator.php`
- `app/Services/Metrics/GitHubMetricsService.php`
- `app/Models/StoryGithubCommit.php`, `StoryGithubPr.php`
- `app/Jobs/SyncStoryGithubCommitsJob.php`
- `app/Console/Commands/BackfillGithubCommitsCommand.php`
