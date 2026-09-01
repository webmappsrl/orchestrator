> Ticket: oc:8446

# Documentazione: calcolo ore stimate/effettive — Implementation Plan

**Goal:** Produrre `docs/calcolo-ore-effettive-stimate.md`, mappa tecnica puramente descrittiva (nessuna modifica al comportamento, nessuna raccomandazione) di come funziona oggi il calcolo delle ore stimate ed effettive su Story/Tag/Team Performance. Il collegamento da `docs/features/8421-rollup-tempo-stima-padre-figlio/overview.md` è descoped da questo branch (quel file vive solo su `feature/oc-8421-...`, non ancora mergiato) — va aggiunto separatamente, direttamente lì.

**Architecture:** Nessun codice da scrivere — solo due file Markdown. Tutta la ricerca (lettura codice, verifica cronologia git, esclusione di ambiguità) è già stata completata nelle fasi precedenti (reverse-interaction + challenge); questo piano trascrive quel materiale nella struttura concordata.

**Tech Stack:** Markdown.

## Global Constraints

- Nessuna modifica al comportamento del codice applicativo
- Nessuna raccomandazione, proposta di refactor, o giudizio su quale fonte dati sia "quella corretta" — solo fatti verificabili (chi chiama cosa, cosa è collegato a cosa)
- Ogni affermazione deve essere verificabile con un riferimento file:riga o comando (`git log`, `grep`) — niente asserzioni non supportate
- Commit scope: `docs(oc:8446): ...`
- Out of scope: `todoStagnationTotalDays`/reopen count (citati solo se necessari a contestualizzare `StoryMetricsCalculator`), traduzioni/i18n

---

## File map

| File | Azione | Responsabilità |
|---|---|---|
| `docs/calcolo-ore-effettive-stimate.md` | Creare | Mappa tecnica completa del sistema di calcolo ore |
| `docs/features/8421-rollup-tempo-stima-padre-figlio/overview.md` | Modificare | Aggiunta riga di link verso il nuovo documento |

---

## Task 1: Scrivere `docs/calcolo-ore-effettive-stimate.md`

**Files:**
- Create: `docs/calcolo-ore-effettive-stimate.md`

**Interfaces:**
- Produces: documento consultabile da chi affronterà oc:8421

- [ ] **Step 1: Intestazione e disclaimer di data/commit**

  Titolo del documento, e riga: "Verificato al commit `e0865db`, 2026-09-01 — potrebbe non riflettere modifiche successive al codice".

- [ ] **Step 2: Paragrafo di sintesi introduttiva**

  Prima di qualunque dettaglio file-per-file, il paragrafo di apertura deve stabilire chiaramente: oggi convivono **due sistemi di "ore effettive" realmente usati** (colonna `hours`, filtrata su orari lavorativi, alimenta Nova/Story detail/Tag SAL; `StoryMetricsCalculator::cycleTimeMinutes()`, non filtrata, alimenta solo la dashboard Team Performance) più **un terzo, `Story::effectiveMinutes()`/`effectiveMinutesForStory()`, mai collegato a nessun consumatore**. I tre calcolano concetti simili ma non identici e possono divergere sullo stesso ticket.

- [ ] **Step 3: Sezione "Ore stimate"**

  Campo `estimated_hours` su `Story`: colonna diretta, inserita manualmente in Nova (Developer/Admin), nessun calcolo. Consumatori: `Tag::getEstimateAttribute()` (somma sulle story taggate), `StoryMetricsCalculator::onTimeDelivery()`/`estimationAccuracy()` (benchmark).

- [ ] **Step 4: Sezione "Ore effettive — colonna `hours` (deprecata ma in uso)"**

  Mappa completa del path di scrittura:
  - `Story::save()` (override, `app/Models/Story.php:41-107`): ad ogni cambio di stato, chiama `StoryTimeService::getStoryProgressDaysMinutes()`/`getStoryTime()` **direttamente** e scrive `hours` con query raw (`DB::table('stories')->update(...)`) — **non** passa da `StoryTimeService::handle()`
  - `StoryTimeService::handle()` (con `saveQuietly()`): path separato, invocato solo dal comando artisan `service:story-time` — verificato non schedulato in `Kernel.php`, solo esecuzione manuale
  - Algoritmo: somma minuti in stato `progress` da `StoryLog.created_at` (precisione al secondo), **esclude weekend e orari fuori 8-18**
  - Truthy-check su entrambi i path (`if ($hours)`): se il risultato è `0`, l'update viene saltato — il valore precedente resta stantio
  - Edge case: se la story non ha un utente assegnato, `getStoryTime()` ritorna `false` — nessun calcolo avviene
  - Consumatori: `fieldTrait::effectiveHoursField()` (campo "Effective Hours" su Story detail), `Tag::getTotalHoursAttribute()`/`getSalAttribute()`/`calculateSalPercentage()` (colonna "SAL t" in Nova `Tag.php`), `App\Nova\Metrics\TagHoursTotal` (mode `effective`)

- [ ] **Step 5: Sezione "Ore effettive — `Story::effectiveMinutes()`/`effectiveMinutesForStory()` (mai collegata)"**

  - Algoritmo: stessa idea (somma intervalli `progress`) ma da `StoryLog.viewed_at` (precisione al minuto, scritta così in `StoryLog::create()`, `Story.php:69`), **senza** esclusione weekend/orari
  - Verifica "nessun chiamante": ricerca statica case-insensitive su tutto il repo (esclusi vendor/node_modules/.git), controllo `$appends`/Http Resources per serializzazione automatica, `git log --all -S "effectiveMinutes"` su tutta la cronologia/tutti i branch — nessuna occorrenza fuori dalla definizione. Riserva esplicita: `public static`, chiamabile da tinker senza lasciare traccia
  - Origine: introdotta nel commit `34c59326` (`feat(oc:8123): add team performance analytics dashboard`, 2026-06-25) con docblock "Fonte autorevole per le ore effettive — il campo `hours` è deprecato". Nello stesso commit, la dashboard reale è stata scritta come reimplementazione indipendente in `StoryMetricsCalculator` — il metodo su `Story` non è mai stato ricollegato. Non menzionata in `docs/features/8123-team-performance-analytics/overview.md` né `plan.md`

- [ ] **Step 6: Sezione "Ore effettive — `StoryMetricsCalculator::cycleTimeMinutes()` (Team Performance)"**

  - Algoritmo: stessa logica di somma intervalli `progress` da `StoryLog.viewed_at`, **senza** esclusione weekend/orari — reimplementazione indipendente, non riusa `effectiveMinutesForStory()`, con cache in-memory (`$logCache`)
  - Consumatore: `TeamPerformanceController` (dashboard Team Performance — cycle time, on-time delivery, reopen count; solo in questo contesto)

- [ ] **Step 7: Tabella riepilogativa di confronto**

  Tabella con colonne: Implementazione | Colonna sorgente | Precisione | Esclude weekend/orari | Scrive/legge | Consumatori — una riga per ciascuna delle tre implementazioni, per confronto rapido.

- [ ] **Step 8: Nota di contesto su `StoryLog`**

  Una riga: `StoryLog` non è una tabella dedicata solo ai cambi di stato — è riusata anche da altri scopi (tracking visualizzazioni via middleware `LogStory`, reminder, sync calendario). Nessuna analisi approfondita degli altri consumatori (fuori scope).

- [ ] **Step 9: Auto-review contro l'overview**

  Rileggi `docs/features/8446-documentazione-calcolo-ore-stimate-effettive/overview.md` e verifica che ogni voce della sezione Requisiti sia coperta nel documento appena scritto. Nessun requisito deve restare senza corrispondenza.

## Task 2: Collegare oc:8421 (fuori da questo branch)

**Descoped.** `docs/features/8421-rollup-tempo-stima-padre-figlio/overview.md` vive solo sul branch `feature/oc-8421-...` (non ancora mergiato su `develop`), e il branch di oc:8446 deve restare un'analisi indipendente, senza commit/file di oc:8421. Il link va aggiunto separatamente, direttamente sul branch di oc:8421 — riga: "Vedi mappa completa del sistema attuale in `docs/calcolo-ore-effettive-stimate.md` (oc:8446)."

## Task 3: Notes e checklist finale

- [ ] **Step 1: Compila `notes.md`**

  Crea `docs/features/8446-documentazione-calcolo-ore-stimate-effettive/notes.md` (anche solo "Nessuna deviazione rilevante" se non emerge nulla durante la scrittura).

- [ ] **Step 2: Verifica checklist di completamento**

  `overview.md`, `plan.md`, `notes.md` esistono; `docs/calcolo-ore-effettive-stimate.md` esiste e copre tutti i Requisiti; link da oc:8421 presente.
