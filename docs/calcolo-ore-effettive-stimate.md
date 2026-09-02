# Calcolo ore stimate/effettive: come funziona oggi

> **Ticket:** oc:8446
> **Verificato al commit `e0865db`** (2026-09-01) per il codice, e sui **dati del DB locale** (dump di produzione) il 2026-09-02.
> I riferimenti puntano a `file` + **nome di metodo**, non a numeri di riga: i file mappati sono tra i più modificati del repo e i range riga invecchiano in fretta. I numeri di riga compaiono solo dove serve indicare una singola istruzione.

Mappa puramente descrittiva dello stato attuale. Nessuna raccomandazione, nessuna proposta di refactor, nessun giudizio su quale fonte dati sia "quella corretta" — solo fatti verificabili, ciascuno con il puntatore al codice o al comando che lo dimostra.

## Indice

- [Sintesi](#sintesi)
- [Glossario](#glossario)
- [Stato reale dei dati](#stato-reale-dei-dati)
- [Ore stimate — `estimated_hours`](#ore-stimate--estimated_hours)
- [Misura 1 — colonna `hours`](#misura-1--colonna-hours)
- [Misura 2 — `cycleTimeMinutes()`](#misura-2--cycletimeminutes)
- [Misura 3 — `effectiveMinutes()` (nessun consumatore)](#misura-3--effectiveminutes-nessun-consumatore)
- [Tabella di confronto](#tabella-di-confronto)
- [Nota di contesto: `StoryLog`](#nota-di-contesto-storylog)

## Sintesi

Oggi convivono **due misure di "ore effettive" realmente usate**, più **una terza mai collegata a nessun consumatore**:

| # | Misura | In uso? | Consumatori |
|---|---|---|---|
| 1 | Colonna `hours` su `stories` (scritta da `StoryTimeService`) | ✅ sì | Nova (campo + metrica), Tag SAL, report, export Excel, **API pubblica** |
| 2 | `StoryMetricsCalculator::cycleTimeMinutes()` | ✅ sì | Dashboard Team Performance, report PDF trimestrale |
| 3 | `Story::effectiveMinutes()` / `effectiveMinutesForStory()` | ❌ nessun chiamante nel codice | nessuno |

Le misure 1 e 2 calcolano concetti simili ma **non identici**: la 1 conta solo i giorni lavorativi nella finestra 09:00–17:59, la 2 conta il tempo di calendario. **Divergono nel 58% dei casi misurati, con un fattore ~2× sulla media** (vedi [Stato reale dei dati](#stato-reale-dei-dati)). Non c'è oggi un'unica "verità": dipende da dove si guarda.

## Glossario

Il documento usa questi termini in modo rigoroso, perché il punto centrale è proprio che designano cose diverse:

| Termine | Significato in questo documento |
|---|---|
| **ore effettive** | il *concetto* astratto "quanto tempo è stato lavorato su un ticket". Non un valore: ne esistono tre misure diverse |
| **`hours`** | la colonna `stories.hours`. Sempre chiamata col nome della colonna, mai "ore effettive" |
| **`cycleTimeMinutes()`** | il metodo di `StoryMetricsCalculator`. Sempre col nome del metodo |
| **`effectiveMinutes()`** | il metodo di `Story` (e la sua variante statica `effectiveMinutesForStory()`) |
| **nessun chiamante** | nessun riferimento al simbolo nel codice del repo. È lo stato della misura 3 — usato sempre in questa forma, mai come "dead code", per non affermare più di quanto la verifica dimostri (vedi la riserva in [Misura 3](#misura-3--effectiveminutes-nessun-consumatore)) |
| **giorno lavorativo** | lun–ven, ore 09:00–17:59 (vedi [Misura 1](#misura-1--colonna-hours)) |

## Stato reale dei dati

Verificato via `psql` sul DB locale (dump di produzione) il 2026-09-02. Query riproducibili:

```bash
docker exec postgres_orchestrator psql -U orchestrator -d orchestrator -c "
SELECT count(*) AS totali,
       count(hours) AS hours_non_null,
       count(*) FILTER (WHERE hours IS NULL) AS hours_null,
       count(*) FILTER (WHERE hours < 0) AS hours_negativi,
       count(*) FILTER (WHERE estimated_hours IS NOT NULL) AS con_stima,
       count(*) FILTER (WHERE hours IS NOT NULL AND estimated_hours IS NOT NULL) AS con_entrambi
FROM stories;"
```

| Fatto | Valore | Perché conta |
|---|---|---|
| Story totali | 7537 | |
| `hours` valorizzata | 3567 (47%) | |
| **`hours` è `NULL`** | **3970 (53%)** | Il caso "null" non è un edge case: è la maggioranza. Qualunque aggregazione deve trattarlo come caso normale |
| `hours = 0` | 0 | Coerente con il truthy-check descritto in [Misura 1](#misura-1--colonna-hours): uno zero non viene mai scritto |
| **`hours` negativa** | **280 story**, somma `-32.95`, peggiore `-0.23` | Nessuna delle anomalie note (truthy-check, utente non assegnato) lo spiega: è un difetto di calcolo di `StoryTimeService` sui limiti della finestra oraria. Un `SUM()` propaga i negativi silenziosamente |
| `estimated_hours` valorizzata | **192 (2.5%)** | |
| **Story con `hours` *e* `estimated_hours`** | **99 (1.3%)** | Sono le sole su cui SAL% e `estimationAccuracy()` producono un numero: entrambi sono rapporti tra i due campi |
| `hours` valorizzata senza log di `progress` | 0 | Nessun valore orfano rispetto ai log |

**Divergenza misurata tra misura 1 e misura 2** (3242 story con `hours > 0` e intervalli di `progress` chiudibili):

| | Valore |
|---|---|
| Divergenti oltre 1 minuto | **1890 (58%)** |
| Media `hours` | 267.8 min |
| Media tempo di calendario | 564.4 min (**≈2,1×**) |
| Scarto massimo | 51.679 min (~36 giorni) |

> Il confronto è stato ottenuto sommando in SQL gli intervalli di `progress` su `viewed_at` con `LEAD()`, che **approssima** la misura 2 senza reimplementarla fedelmente. Stabilisce quindi ordine di grandezza e direzione della divergenza, non il valore esatto che `cycleTimeMinutes()` restituirebbe.

## Ore stimate — `estimated_hours`

Colonna diretta su `stories`, `decimal(5,2)` nullable (migration `2024_12_09_114400_add_story_time_column_migration.php`). Nessun calcolo: valore inserito.

**Chi la scrive:**
- **Nova**, campo editabile: `app/Traits/fieldTrait.php` → `estimatedHoursField()`, visibile a Developer/Admin
- **API**: `app/Http/Requests/Api/StoryApiRequest.php` (`'estimated_hours' => ['sometimes','nullable','numeric','min:0']`), consumata da `app/Http/Controllers/Api/StoryController.php` su `POST` e `PATCH`

**Chi la consuma:**
- `app/Models/Tag.php` → `getEstimateAttribute()` — somma `estimated_hours` delle story taggate
- `app/Models/TagGroup.php:33` — `$this->stories()->sum('estimated_hours')`, stesso ruolo per i gruppi di tag
- `app/Services/Metrics/StoryMetricsCalculator.php` → `onTimeDelivery()` — benchmark: se `estimated_hours` è impostato usa `estimated_hours * 60` minuti come soglia, altrimenti la media del cycle time del team
- `app/Services/Metrics/StoryMetricsCalculator.php` → `estimationAccuracy()` — rapporto `estimated_hours / hours`; **nota: usa la colonna `hours`, non `cycleTimeMinutes()`**
- `app/Exports/SelectedStoriesToExcel.php:61` — export Excel
- `app/Http/Controllers/Api/StoryController.php:129` — esposta nel payload API pubblico

---

## Misura 1 — colonna `hours`

Colonna `stories.hours`: `float` **nullable**, unità **ore**, `round(minuti / 60, 2)` — arrotondata per singola story (migration `2024_12_09_114400_add_story_time_column_migration.php`, valore prodotto da `app/Actions/StoryTimeService.php` → `getStoryTime()`).

Il docblock di `effectiveMinutes()` la dichiara deprecata ([vedi Misura 3](#misura-3--effectiveminutes-nessun-consumatore)), ma è la colonna realmente letta da tutti i consumatori elencati sotto.

### Chi la scrive

**Tre path distinti**, non due.

**1. `Story::save()`** (override completo, `app/Models/Story.php`) — ad ogni salvataggio che cambia lo `status`, chiama `StoryTimeService::make()->getStoryTime($this)` **direttamente** e scrive con una query raw fuori da Eloquent:

```php
if (isset($changes['status'])) {
    $storyTime = StoryTimeService::make()->getStoryTime($this);
    if ($storyTime !== false && $storyTime['hours']) {
        DB::table('stories')->where('id', $this->id)->update(['hours' => $storyTime['hours']]);
    }
}
```

Questo path **non chiama** `StoryTimeService::handle()` — bypassa `saveQuietly()` e qualunque evento di modello, dentro la stessa transazione di `save()`.

**2. Comando artisan `service:story-time {story_id?}`** → `StoryTimeService::handle()` (`app/Actions/StoryTimeService.php`), che invece usa `saveQuietly()`:

```php
public function handle(Story $story): bool
{
    $storyTime = $this->getStoryTime($story);
    if ($storyTime === false) return false;
    $story->hours = $storyTime['hours'];
    if ($story->hours) {
        $story->saveQuietly();
        return true;
    }
    return false;
}
```

Senza argomento itera su `Story::all()`. **Verificato: non è schedulato in `app/Console/Kernel.php`** — eseguibile solo manualmente.

**3. Scrittura manuale da Nova** — `app/Traits/fieldTrait.php` → `effectiveHoursField()`: sui request di create/update ritorna un campo **editabile**

```php
Number::make(__('Effective Hours'), $fieldName)
    ->sortable()
    ->rules('nullable', 'numeric', 'min:0')
```

visibile a Developer/Admin (`estimatedHoursFieldCanSee()`), usato in `app/Nova/Story.php` e `app/Nova/ArchivedStoryShowedByCustomer.php`. `hours` **non** è in `$fillable` su `Story`, ma Nova assegna gli attributi direttamente e non passa dal mass-assignment, quindi la scrittura va a buon fine.

> Conseguenza: `hours` **non è un valore interamente derivato dai log**. Un valore inserito a mano viene sovrascritto silenziosamente dal path 1 al successivo cambio di `status`.

### Algoritmo di calcolo

`app/Actions/StoryTimeService.php` → `getStoryProgressDaysMinutes()`:
- Legge tutti gli `StoryLog` della story, ordinati per `created_at` decrescente
- Per ogni log con `changes.status === 'progress'`, cerca il successivo `StoryLog` con un campo `status` nei `changes` (o usa `now()` se la story è ancora in progress) per chiudere l'intervallo
- Somma i minuti dell'intervallo **a passi di 10 minuti**, escludendo quelli fuori dal giorno lavorativo
- Colonna sorgente: **`StoryLog.created_at`** (precisione al secondo)

La finestra lavorativa è definita da `isAWorkingDate()`:

```php
$hour = (int) $date->format('G');
return $hour > 8 && $hour < 18;
```

Cioè sabato/domenica esclusi e **finestra effettiva 09:00–17:59**: l'ora `8` è esclusa per intero (08:00–08:59 non conta), mentre 17:00–17:59 conta. Il commento nel codice dice "between 8am and 6pm", che è impreciso di un'ora.

### Chi la consuma

- `app/Traits/fieldTrait.php` → `effectiveHoursField()` — campo "Effective Hours" su detail/index Story in Nova (ramo di sola lettura; il ramo create/update è il path di scrittura 3 sopra)
- `app/Nova/Metrics/StoryTime.php` — metrica Nova `sum(..., 'hours')`, usata in `app/Nova/Story.php:280,283` (index + detail, nascosta ai Customer) e in `app/Nova/Lenses/StoriesByQuarter.php:176`
- `app/Models/Tag.php` → `getTotalHoursAttribute()` — `round($this->tagged()->sum('hours'), 2)`
- `app/Models/Tag.php` → `getSalAttribute()` / `calculateSalPercentage()` — SAL% come `getTotalHoursAttribute() / estimate * 100`
- `app/Nova/Tag.php` — colonna "SAL t" nell'index Tag
- `app/Nova/Metrics/TagHoursTotal.php` — mode `effective`, restituisce `getTotalHoursAttribute()`
- `app/Services/Metrics/StoryMetricsCalculator.php` → `estimationAccuracy()` — `estimated_hours / hours`, aggregato in `buildAggregate()` come `avg_estimation_accuracy` e consumato dalla dashboard Team Performance e da `resources/views/pdf/developer-performance-report.blade.php`
- `app/Http/Controllers/ReportController.php:135,163,169` — `SUM(hours) as hours_sum` per il ranking clienti; `:285` e `:494` — `round($query->sum('hours'), 2)`. La cache di questi report è **schedulata**: `reports:refresh-cache` in `app/Console/Kernel.php:78`
- `app/Exports/SelectedStoriesToExcel.php:62` — export Excel
- `app/Http/Controllers/Api/StoryController.php:130` — **esposta nel payload API pubblico** (documentata negli `@response`)

> La dashboard Team Performance consuma quindi **entrambe** le misure: `cycleTimeMinutes()` per il cycle time e `hours` per `estimationAccuracy()`.

### Note

- **Truthy-check, non "not null"**: sia `Story::save()` (`app/Models/Story.php:99`) sia `StoryTimeService::handle()` (`app/Actions/StoryTimeService.php:47`) saltano l'update quando le ore calcolate sono `0`. Il valore precedente resta invariato (stantio), non viene azzerato. Coerente col dato reale: zero righe con `hours = 0`.
- **Edge case utente non assegnato**: `getStoryTime()` ritorna `false` se `$story->user` è `null` — nessun calcolo avviene per story senza assegnatario.
- **Valori negativi**: 280 story hanno `hours < 0` (vedi [Stato reale dei dati](#stato-reale-dei-dati)). Non spiegati dai due punti sopra.
- **Non è un path di scrittura**: `app/Http/Controllers/ScrumController.php:30` passa `'hours' => 0` a `Story::create()`, ma `hours` non è in `$fillable` e il valore viene silenziosamente ignorato.

---

## Misura 2 — `cycleTimeMinutes()`

`app/Services/Metrics/StoryMetricsCalculator.php` → `cycleTimeMinutes(int $storyId): ?int` — restituisce **minuti**, nullable. Calcolo live, nessuna persistenza.

### Algoritmo di calcolo

Somma gli intervalli in stato `progress` dai `StoryLog`:
- Colonna sorgente: **`StoryLog.viewed_at`**
- **Nessuna esclusione** di weekend o finestra oraria: conta il tempo di calendario, 24/7
- Cache in-memory per processo (`private static array $logCache`) per evitare N+1 quando chiamata su una lista di story

È una **reimplementazione indipendente** della stessa logica concettuale di `effectiveMinutesForStory()` (stessa colonna sorgente, stesso concetto), che però non ne riusa il codice.

### Chi la consuma

- `app/Http/Controllers/Nova/TeamPerformanceController.php` — `getTickets()` (`cycle_time_hours` per ticket), `onTimeDetail()` / `onTimeDiff()` (effettive vs stimate/media team), `buildAggregate()` / `buildTeamAggregate()` (medie per sviluppatore e team, cache Redis 1h con chiave `team_perf_avg_{year}_q{quarter}`, impostata in `data()`)
- `app/Services/Metrics/StoryMetricsCalculator.php` → `developerMetrics()`, invocato da `app/Jobs/GeneratePerformanceReportJob.php` (report PDF trimestrale per developer, dispatchato da `app/Nova/Actions/GeneratePerformanceReportAction.php`)

### Note

- La query in `getTickets()` seleziona anche la colonna `hours` (`get([... 'hours', ...])`), ma quel valore non viene usato nell'array restituito: per le metriche di cycle time la dashboard usa solo `cycleTimeMinutes()`. (`hours` le arriva comunque per altra via, tramite `estimationAccuracy()`.)

---

## Misura 3 — `effectiveMinutes()` (nessun consumatore)

`app/Models/Story.php` → `effectiveMinutes(): ?int` e `effectiveMinutesForStory(int $storyId): ?int` — restituiscono **minuti**, nullable. Calcolo live, nessuna persistenza.

```php
/**
 * Minuti trascorsi in stato `progress`, sommando tutti gli intervalli attivi.
 * Fonte autorevole per le ore effettive — il campo `hours` è deprecato.
 * Restituisce null se non ci sono mai stati log di progress.
 */
public function effectiveMinutes(): ?int
{
    return self::effectiveMinutesForStory($this->id);
}
```

Il docblock sopra è **la sola fonte** dell'affermazione che `hours` sia deprecata. Nessun consumatore la segue: vedi la lista dei consumatori di `hours` nella [Misura 1](#misura-1--colonna-hours).

### Algoritmo di calcolo

Stesso concetto della misura 1 (somma degli intervalli in `progress`), con differenze concrete:
- Colonna sorgente: **`StoryLog.viewed_at`**, non `created_at`. Per i log di cambio stato `viewed_at` è scritta al minuto (`now()->format('Y-m-d H:i')` in `app/Models/Story.php:69`); altri writer della stessa colonna usano invece il secondo (`app/Http/Middleware/LogStory.php:41`, `app/Console/Commands/SendWaitingStoryReminder.php:136`), ma i calcolatori filtrano sui log con `changes.status` e quindi leggono solo i primi
- **Nessuna esclusione** di weekend o finestra oraria

### Verifica "nessun chiamante"

Effettuata con tre metodi indipendenti:

1. **Ricerca statica** case-insensitive di `effectiveMinutes` ed `effective_minutes` su tutto il repo (esclusi `vendor/`, `node_modules/`, `.git/`) — nessuna occorrenza fuori dalla definizione in `app/Models/Story.php`, dai file in `docs/` e da `CLAUDE.md`. Controllati esplicitamente anche Nova, Blade, JS/Vue, test e API Resources.
2. **Serializzazione automatica**: la proprietà `$appends` **non è definita** su `Story` (nessuna occorrenza nel file), quindi nessun accessor viene esposto automaticamente in JSON/API.
3. **`git log --all -S "effectiveMinutes"`** — 7 commit, di cui **solo `34c59326` tocca codice**; gli altri 6 sono file `.md` (le documentazioni di oc:8421 e di questo stesso ticket).

**Riserva esplicita:** essendo `public static`, nulla impedisce che `effectiveMinutesForStory()` sia stato invocato manualmente via `tinker`, un uso che non lascia traccia nel codice. La verifica copre i chiamanti *nel codice*, non un uso interattivo occasionale.

### Origine

Introdotta nel commit `34c59326` — `feat(oc:8123): add team performance analytics dashboard`, 2026-06-25. Nello **stesso commit** che l'ha introdotta, la logica reale della dashboard è stata scritta come classe separata e indipendente (`StoryMetricsCalculator`, [misura 2](#misura-2--cycletimeminutes)); il metodo su `Story` non è mai stato ricollegato a quella logica né a nient'altro.

Non è menzionata in `docs/features/8123-team-performance-analytics/overview.md` né in `plan.md` (entrambi creati nello stesso commit): non era nel piano approvato per quella feature.

---

## Tabella di confronto

| Proprietà | Misura 1 — `hours` | Misura 2 — `cycleTimeMinutes()` | Misura 3 — `effectiveMinutes()` |
|---|---|---|---|
| **Dove vive** | colonna `stories.hours`; scrittura in `app/Actions/StoryTimeService.php` | `app/Services/Metrics/StoryMetricsCalculator.php` | `app/Models/Story.php` |
| **Tipo** | `float` nullable | `?int` | `?int` |
| **Unità** | **ore**, `round(min/60, 2)` per story | **minuti** | **minuti** |
| **Colonna sorgente `StoryLog`** | `created_at` | `viewed_at` | `viewed_at` |
| **Granulo della colonna sorgente** | al secondo | al minuto (per i log di stato) | al minuto (per i log di stato) |
| **Perdita di precisione nel risultato** | sì: arrotondamento a 2 decimali **per singola story**, che si accumula sommando N story | no | no |
| **Solo giorni lavorativi 09:00–17:59** | sì | no | no |
| **Persistita?** | sì | no (calcolo live, cache in-memory per request) | no (calcolo live) |
| **Trigger** | `Story::save()` automatico + comando `service:story-time` manuale + **campo Nova editabile** | on-demand | on-demand, mai invocato |
| **Consumatori** | Nova (campo + metrica `StoryTime`), Tag SAL, `TagHoursTotal`, `estimationAccuracy()`, report, export Excel, API pubblica | dashboard Team Performance, report PDF trimestrale | nessuno |

---

## Nota di contesto: `StoryLog`

La tabella `story_logs` (modello `App\Models\StoryLog`) non è dedicata ai soli cambi di stato: è riusata anche per il tracking delle visualizzazioni (`app/Http/Middleware/LogStory.php`), per i reminder (`app/Console/Commands/SendWaitingStoryReminder.php`) e per la sync calendario. Questo documento non analizza quegli altri consumatori (fuori scope): tutte e tre le misure sopra leggono esclusivamente i log che hanno un campo `status` nei `changes`.
