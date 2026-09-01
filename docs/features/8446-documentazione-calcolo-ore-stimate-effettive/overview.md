> Ticket: oc:8446

# Documentazione: come funziona oggi il calcolo ore stimate/effettive (Story, Tag, Team Performance)

## Cosa cambia
Nessuna modifica al comportamento del sistema. Viene creato un nuovo documento tecnico standalone, `docs/calcolo-ore-effettive-stimate.md`, che mappa in modo puramente descrittivo come funziona **oggi** il calcolo delle ore stimate ed effettive: da dove vengono lette, quali funzioni sono coinvolte, dove vengono scritte/consumate, e quali collegamenti esistono (o non esistono) tra le diverse implementazioni trovate nel codice.

Il link da `docs/features/8421-rollup-tempo-stima-padre-figlio/overview.md` verso questo documento **non fa parte del branch di oc:8446**: quel file vive solo sul branch `feature/oc-8421-...` (non ancora mergiato su `develop`), e questo ticket è scoped a un'analisi indipendente, senza commenti/collegamenti al codice o ai commit di oc:8421. Il link va aggiunto separatamente, direttamente sul branch di oc:8421 (o dopo il suo merge), fuori da questo ciclo.

## Perché
Propedeutico a oc:8421 (rollup tempo/stima padre-figlio): prima di implementare il rollup serve una mappa condivisa e verificata di come funziona oggi il sistema, per evitare che oc:8421 parta da assunzioni sbagliate su quale fonte dati sia quella "vera".

## Requisiti
- [ ] Paragrafo di sintesi introduttivo (prima del dettaglio file-per-file): oggi convivono **due sistemi di "ore effettive" realmente usati** (colonna `hours`, filtrata su orari lavorativi, alimenta Nova/Tag; `StoryMetricsCalculator::cycleTimeMinutes()`, non filtrata, alimenta solo Team Performance) più **un terzo, `effectiveMinutes()`/`effectiveMinutesForStory()`, mai collegato a nessun consumatore** — i tre calcolano concetti simili ma non identici e possono divergere sullo stesso ticket
- [ ] Mappare la scrittura della colonna deprecata `hours` su `Story`: `Story::save()` (override, `app/Models/Story.php:41-107`) scrive `hours` con una query raw (`DB::table('stories')->update(...)`) usando `StoryTimeService::getStoryProgressDaysMinutes()`/`getStoryTime()` **direttamente**, non chiama `StoryTimeService::handle()`. `handle()` (che usa `saveQuietly()`) è un path separato, invocato solo dal comando artisan `service:story-time`.
- [ ] Documentare il truthy-check su entrambi i path di scrittura di `hours` (`if ($hours)` in `Story::save()` e in `StoryTimeService::handle()`): se le ore calcolate sono `0`, l'update viene saltato e il valore precedente resta stantio invece di azzerarsi
- [ ] Mappare `Story::effectiveMinutes()` / `effectiveMinutesForStory()` e verificarne i chiamanti reali nel codebase (nessuna occorrenza trovata via ricerca statica su tutto il repo, incluse `$appends`/API Resources per la serializzazione automatica, e via `git log --all -S` su tutta la cronologia/tutti i branch — non una garanzia assoluta, essendo un metodo `public static` chiamabile da tinker senza lasciare traccia)
- [ ] Documentare l'origine di `effectiveMinutes()`/`effectiveMinutesForStory()`: introdotto nel commit `34c59326` (`feat(oc:8123): add team performance analytics dashboard`, 25 giugno 2026) con un docblock che lo dichiara "fonte autorevole per le ore effettive". Nello **stesso commit**, la logica reale della dashboard è stata scritta come reimplementazione indipendente in `StoryMetricsCalculator::cycleTimeMinutes()`, mai ricollegata al metodo su `Story`. Non è menzionato né in `docs/features/8123-team-performance-analytics/overview.md` né in `plan.md` (creati nello stesso commit) — non era nel piano approvato
- [ ] Mappare `StoryMetricsCalculator::cycleTimeMinutes()` e la sua reimplementazione indipendente della stessa logica di somma intervalli `StoryLog`
- [ ] Documentare il comando artisan `service:story-time` (`StoryTimeService::asCommand()`) come secondo path di scrittura di `hours`, distinto da `Story::save()` — verificato: non schedulato in `Kernel.php`, eseguibile solo manualmente
- [ ] Documentare la divergenza di colonna/precisione tra le tre implementazioni: `StoryTimeService` legge `created_at` (precisione al secondo), `effectiveMinutesForStory()` e `StoryMetricsCalculator::cycleTimeMinutes()` leggono invece `viewed_at` (scritto con precisione al minuto in `StoryLog::create()`, `Story.php:69`) — fatto neutro, nessuna valutazione su quale sia preferibile
- [ ] Nota di contesto: `StoryLog` non è una tabella dedicata solo ai cambi di stato — è riusata anche per altri scopi (es. tracking visualizzazioni via middleware `LogStory`, reminder, sync calendario). Limitata a una riga di contesto, non un'analisi degli altri consumatori (fuori scope)
- [ ] Documentare l'edge case: `StoryTimeService::getStoryTime()` ritorna `false` silenziosamente se la story non ha un utente assegnato (`$story->user` null) — nessun calcolo di `hours` avviene in quel caso
- [ ] Documentare tutti i punti di consumo: `Tag::getTotalHoursAttribute()`/`getSalAttribute()` (Nova `Tag.php`, colonna "SAL t"), `App\Nova\Metrics\TagHoursTotal` (mode `effective`), `fieldTrait::effectiveHoursField()` (campo "Effective Hours" su Story), `TeamPerformanceController`/`StoryMetricsCalculator` (dashboard Team Performance)
- [ ] Segnalare in modo neutro le divergenze trovate (es. il campo Nova "Effective Hours" legge `hours`, non `effectiveMinutes()`) senza proporre correzioni o refactor
- [ ] Nessuna raccomandazione o proposta di unificazione: solo mappa dello stato attuale
- [ ] Intestazione con disclaimer di data/commit: "Verificato al commit `e0865db`, 2026-09-01 — potrebbe non riflettere modifiche successive al codice"

**Descoped da questo branch** (da fare separatamente, sul branch di oc:8421): link da `docs/features/8421-rollup-tempo-stima-padre-figlio/overview.md` verso il nuovo documento.

## Rischi
- **Lettura errata a valle (oc:8421):** il docblock di `effectiveMinutes()` si dichiara "fonte autorevole", ma non ha mai avuto un chiamante — mentre `hours` (nominalmente deprecata) è il valore realmente mostrato oggi su Tag SAL e Story detail. Chi affronterà oc:8421 deve scegliere `hours` (o `cycleTimeMinutes()`, a seconda del punto di consumo target) come base del rollup, non `effectiveMinutes()` — nonostante quello che suggerisce il commento nel codice. Mitigato documentando esplicitamente l'origine e lo stato "mai collegato" di `effectiveMinutes()` (vedi Requisiti), così il documento tecnico chiarisce il fatto senza bisogno di formulare una raccomandazione.

## Out of scope
- Qualsiasi modifica al comportamento del codice
- Raccomandazioni, proposte di refactor o indicazione di quale fonte dati sia "quella corretta"
- Metriche non direttamente legate al calcolo ore (es. `todoStagnationTotalDays`, reopen count) — citate solo se necessarie a contestualizzare `StoryMetricsCalculator`
- Traduzioni/i18n (documento tecnico interno, non testo UI)

## Moduli toccati
- `docs/calcolo-ore-effettive-stimate.md` (nuovo) — mappa tecnica del sistema
