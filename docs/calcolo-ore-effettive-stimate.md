> Ticket: oc:8446 — verificato al commit `e0865db`, 2026-09-01. Potrebbe non riflettere modifiche successive al codice.

# Calcolo ore stimate/effettive: come funziona oggi

Mappa puramente descrittiva dello stato attuale del codice. Nessuna raccomandazione, nessuna proposta di refactor, nessun giudizio su quale fonte dati sia "quella corretta" — solo fatti verificabili (chi chiama cosa, cosa è collegato a cosa, con riferimento file:riga o comando).

## Sintesi

Oggi convivono **due sistemi di "ore effettive" realmente usati**, più **un terzo mai collegato a nessun consumatore**:

| # | Implementazione | In uso? | Consumatori |
|---|---|---|---|
| 1 | Colonna `hours` su `Story` (scritta da `StoryTimeService`) | ✅ sì | Story detail Nova, Tag SAL, `TagHoursTotal` |
| 2 | `StoryMetricsCalculator::cycleTimeMinutes()` | ✅ sì | Dashboard Team Performance |
| 3 | `Story::effectiveMinutes()` / `effectiveMinutesForStory()` | ❌ no (dead code) | nessuno |

Le implementazioni #1 e #2 calcolano concetti simili ma **non identici**: #1 esclude weekend e orari fuori 8-18, #2 no. Possono restituire numeri diversi per lo stesso identico ticket. Non c'è oggi un'unica "verità" — dipende da dove si guarda.

---

## Ore stimate

Campo `estimated_hours` su `Story`: colonna diretta, inserita manualmente in Nova da Developer/Admin (`app/Traits/fieldTrait.php:497-518`, `estimatedHoursField()`), nessun calcolo.

**Consumatori:**
- `Tag::getEstimateAttribute()` (`app/Models/Tag.php:25-30`) — somma `estimated_hours` di tutte le story taggate
- `StoryMetricsCalculator::onTimeDelivery()` (`app/Services/Metrics/StoryMetricsCalculator.php:56-75`) — benchmark: se `estimated_hours` è impostato, usa `estimated_hours * 60` minuti come soglia; altrimenti usa la media del cycle time del team
- `StoryMetricsCalculator::estimationAccuracy()` (righe 103-112) — rapporto `estimated_hours / hours` (nota: usa la colonna `hours`, non `cycleTimeMinutes()`)

---

## Ore effettive — colonna `hours` (nominalmente deprecata, ma in uso)

### Chi la scrive

Due path distinti, entrambi ultimamente basati su `StoryTimeService::getStoryProgressDaysMinutes()`:

**1. `Story::save()`** (override completo, `app/Models/Story.php:41-107`) — ad ogni salvataggio che cambia lo `status`, chiama `StoryTimeService::make()->getStoryTime($this)` **direttamente** e scrive il risultato con una query raw fuori da Eloquent:

```php
if (isset($changes['status'])) {
    $storyTime = StoryTimeService::make()->getStoryTime($this);
    if ($storyTime !== false && $storyTime['hours']) {
        DB::table('stories')->where('id', $this->id)->update(['hours' => $storyTime['hours']]);
    }
}
```

Questo path **non chiama** `StoryTimeService::handle()` — bypassa `saveQuietly()` e qualunque evento di modello, dentro la stessa transazione di `save()`.

**2. `StoryTimeService::handle()`** (`app/Actions/StoryTimeService.php:41-52`) — path separato, usa `saveQuietly()`:

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

Invocato **solo** dal comando artisan `service:story-time {story_id?}` (`StoryTimeService::asCommand()`) — senza argomento itera su `Story::all()`. **Verificato: non è schedulato in `app/Console/Kernel.php`** — eseguibile solo manualmente.

### Algoritmo di calcolo

`StoryTimeService::getStoryProgressDaysMinutes()` (righe 145-187):
- Legge tutti gli `StoryLog` della story, ordinati per `created_at` decrescente
- Per ogni log con `changes.status === 'progress'`, cerca il prossimo `StoryLog` successivo con un campo `status` nei `changes` (o usa `now()` se la story è ancora in progress) per determinare la fine dell'intervallo
- Somma i minuti dell'intervallo, **escludendo** — a passi di 10 minuti — quelli fuori dall'orario lavorativo (sabato/domenica esclusi; solo ore comprese tra le 8 e le 18, escluse le estremità: `$hour > 8 && $hour < 18`)
- Colonna sorgente: **`StoryLog.created_at`** (precisione al secondo)

### Comportamenti da conoscere

- **Truthy-check, non "not null"**: sia `Story::save()` (riga 99: `if ($storyTime !== false && $storyTime['hours'])`) sia `StoryTimeService::handle()` (riga 47: `if ($story->hours)`) saltano l'update quando le ore calcolate sono `0`. Il valore precedente resta invariato (stantio), non viene azzerato.
- **Edge case utente non assegnato**: `getStoryTime()` (righe 77-95) ritorna `false` se `$story->user` è `null` — nessun calcolo di `hours` avviene per story senza assegnatario.

### Chi la consuma

- **`fieldTrait::effectiveHoursField()`** (`app/Traits/fieldTrait.php:520-537`) — campo "Effective Hours" nel dettaglio/index Story in Nova, legge `$this->hours` direttamente
- **`Tag::getTotalHoursAttribute()`** (`app/Models/Tag.php:45-52`) — somma `hours` di tutte le story taggate (`round($this->tagged()->sum('hours'), 2)`)
- **`Tag::getSalAttribute()`** / **`calculateSalPercentage()`** (righe 37-64) — SAL% calcolato come `getTotalHoursAttribute() / estimate * 100`
- **Colonna "SAL t"** in Nova `app/Nova/Tag.php:61-85` (index Tag) — mostra `getTotalHoursAttribute()` / `estimate`
- **`App\Nova\Metrics\TagHoursTotal`** (mode `effective`, `app/Nova/Metrics/TagHoursTotal.php:30-35`) — restituisce `getTotalHoursAttribute()`

---

## Ore effettive — `Story::effectiveMinutes()` / `effectiveMinutesForStory()` (mai collegata a nessun consumatore)

### Definizione

`app/Models/Story.php:600-641`:

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

public static function effectiveMinutesForStory(int $storyId): ?int
{
    // somma intervalli 'progress' da StoryLog, ordinati per viewed_at
}
```

### Algoritmo di calcolo

Stessa idea concettuale della colonna `hours` (somma intervalli in stato `progress`), ma con differenze concrete:
- Colonna sorgente: **`StoryLog.viewed_at`** (non `created_at`) — scritta con precisione **al minuto**, non al secondo (`now()->format('Y-m-d H:i')` in `StoryLog::create()`, `Story.php:69`)
- **Nessuna esclusione** di weekend o orari non lavorativi — conta tutti i minuti trascorsi in `progress`, 24 ore su 24, 7 giorni su 7
- Nessuna scrittura su colonna: calcolato live ad ogni chiamata

### Verifica "nessun chiamante"

Effettuata con tre metodi indipendenti:
1. Ricerca statica case-insensitive `effectiveMinutes` su tutto il repo Orchestrator (esclusi `vendor/`, `node_modules/`, `.git/`) — nessuna occorrenza fuori dalla definizione stessa in `Story.php`
2. Verifica `$appends` su `Story` (serializzazione automatica in JSON/API) — array vuoto, nessun accessor esposto automaticamente
3. `git log --all -S "effectiveMinutes"` (tutta la cronologia, tutti i branch) — l'unico commit che tocca la stringa è quello che l'ha introdotta

**Riserva esplicita:** essendo `public static`, nulla impedisce che sia stato invocato manualmente via `tinker` in produzione — un uso che non lascerebbe traccia nel codice. La verifica sopra copre solo i chiamanti "nel codice", non un uso interattivo occasionale.

### Origine

Introdotta nel commit `34c59326` — `feat(oc:8123): add team performance analytics dashboard`, 2026-06-25. Nello **stesso commit** che l'ha introdotta, la logica reale della dashboard Team Performance è stata scritta come classe **separata e indipendente**, `StoryMetricsCalculator` (vedi sezione successiva) — il metodo su `Story` non è mai stato ricollegato a quella logica, né a nient'altro.

Non è menzionata in `docs/features/8123-team-performance-analytics/overview.md` né in `plan.md` (entrambi creati nello stesso commit): non era nel piano approvato per quella feature.

---

## Ore effettive — `StoryMetricsCalculator::cycleTimeMinutes()` (Team Performance)

### Definizione

`app/Services/Metrics/StoryMetricsCalculator.php:26-49`:

```php
/**
 * Minuti effettivi in stato `progress`, sommati da tutti gli intervalli attivi.
 */
public function cycleTimeMinutes(int $storyId): ?int
{
    $logs = $this->getStatusLogs($storyId);
    // somma intervalli 'progress', stessa logica concettuale di effectiveMinutesForStory()
}
```

### Algoritmo di calcolo

Reimplementazione **indipendente** della stessa logica concettuale di `effectiveMinutesForStory()` (somma intervalli `progress` da `StoryLog`), con differenze implementative:
- Colonna sorgente: **`StoryLog.viewed_at`** (stessa colonna di `effectiveMinutesForStory()`, non riusa però il suo codice)
- **Nessuna esclusione** di weekend/orari lavorativi (come `effectiveMinutesForStory()`, a differenza della colonna `hours`)
- Cache in-memory per processo (`private static array $logCache`) per evitare N+1 query quando chiamata più volte nella stessa request (es. su una lista di story)

### Chi la consuma

**`TeamPerformanceController`** (`app/Http/Controllers/Nova/TeamPerformanceController.php`) — unico consumatore:
- `getTickets()` (righe 84-120) — `cycle_time_hours` per ogni ticket nella dashboard
- `onTimeDetail()` / `onTimeDiff()` (righe 122-159) — confronto ore effettive vs stimate/media team
- `buildAggregate()` / `buildTeamAggregate()` (righe 175-251) — medie per sviluppatore e per team, cache Redis 1h (`team_perf_avg_{year}_q{quarter}`)

Nota: la query in `getTickets()` (riga 97) seleziona anche la colonna `hours` (`get(['id', 'name', 'type', 'estimated_hours', 'hours', 'updated_at'])`), ma il valore non viene poi utilizzato nell'array restituito — solo `cycleTimeMinutes()` alimenta le metriche esposte dalla dashboard.

---

## Tabella di confronto

| | Colonna `hours` | `effectiveMinutes()` / `effectiveMinutesForStory()` | `cycleTimeMinutes()` |
|---|---|---|---|
| **File** | `app/Actions/StoryTimeService.php` (scrittura), `Story.php:41-107` (trigger) | `app/Models/Story.php:600-641` | `app/Services/Metrics/StoryMetricsCalculator.php:26-49` |
| **Colonna sorgente `StoryLog`** | `created_at` | `viewed_at` | `viewed_at` |
| **Precisione** | al secondo | al minuto | al minuto |
| **Esclude weekend/orari 8-18** | sì | no | no |
| **Persistita?** | sì (colonna `hours` su `stories`) | no (calcolo live) | no (calcolo live, cache in-memory per request) |
| **Trigger di scrittura/calcolo** | `Story::save()` (automatico) + comando `service:story-time` (manuale, non schedulato) | on-demand, mai invocato | on-demand, da `TeamPerformanceController` |
| **Consumatori** | Story detail Nova, Tag SAL, `TagHoursTotal` | nessuno | dashboard Team Performance |

---

## Nota di contesto: `StoryLog` non è dedicata solo ai cambi di stato

La tabella `story_logs` (modello `App\Models\StoryLog`) viene riusata anche per altri scopi oltre al tracking dei cambi di `status` consumati dai calcoli sopra — ad esempio il tracking delle visualizzazioni via middleware `LogStory`, reminder e sync calendario. Questo documento non analizza in dettaglio quegli altri consumatori (fuori scope): la mappa sopra riguarda esclusivamente i log con un campo `status` nei `changes`, che è quanto letto da tutte e tre le implementazioni descritte.
