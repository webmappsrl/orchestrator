> Ticket: oc:8421

# Piano — Relazione padre-figlio: rollup di tempo effettivo (oc:8421)

Riferimento: `docs/features/8421-rollup-tempo-stima-padre-figlio/overview.md` (v7).

**Nota sui commit:** le indicazioni "Commit suggerito" sotto ogni task sono testo per l'utente, non vengono eseguite automaticamente. Nessun `git commit`/`git push`/`git checkout -b` viene eseguito da Claude senza conferma esplicita per ogni singolo commit — vedi `execution: branch` e `execution: implementation` di `wm-plan`.

**Nota sulla revisione 2026-09-09 (v2 del piano):** tutte le query dei figli usano la colonna `parent_id` direttamente (`Story::where('parent_id', $id)` / `idsWithChildren()`). Lo schema (colonna, indice `stories_parent_id_index`, constraint anti-auto-parentela) è già in `develop`; questo piano non prevede migration.

---

## Task 1 — Helper condiviso: `Story::idsWithChildren()` + `Story::hoursWithChildren()`

**File:** `app/Models/Story.php`

**Obiettivo:** due helper interni (non nuovi metodi pubblici "fonte di verità" come `effectiveMinutes()`) che costruiscono l'insieme padre+figli e calcolano il tempo effettivo aggregato. Base per Task 2, 3, 4, 5.

**Dettagli implementativi:**
```php
/**
 * Dato un insieme di id story, ritorna l'unione con gli id dei loro figli diretti
 * (query su parent_id / idsWithChildren, vedi overview §Decisioni).
 */
public static function idsWithChildren(iterable $storyIds): \Illuminate\Support\Collection
{
    $ids = collect($storyIds)->filter()->unique()->values();
    if ($ids->isEmpty()) {
        return $ids;
    }
    $childIds = static::whereIn('parent_id', $ids)->pluck('id');
    return $ids->merge($childIds)->unique()->values();
}

public function hoursWithChildren(): ?float
{
    if (! static::where('parent_id', $this->id)->exists()) {
        return $this->hours;
    }

    return (float) round(
        static::whereIn('id', static::idsWithChildren([$this->id]))->sum('hours'),
        2
    );
}
```
- Il branch "senza figli" di `hoursWithChildren()` ritorna `$this->hours` invariato (anche `null`) — nessuna riga di comportamento cambia per il 100% delle story che oggi non hanno figli.
- Il branch "con figli" usa `sum()` SQL, che ignora i `NULL` per costruzione (Postgres `SUM()` su colonna con `NULL` misti a valori li tratta come 0 nella somma) — copre da solo il requisito di coalescenza a 0 senza `??` espliciti sparsi nei tre punti di consumo.
- Nessun `max(0, ...)` — i negativi si sommano come sono (decisione esplicita, overview §Decisioni).
- `idsWithChildren()` è l'unico punto che sa costruire "insieme padre+figli" — riusato da Task 3 (tre rami) e Task 4 (Tag), coerente con la decisione "helper interno condiviso" dell'overview (evita triplicare la query).
- Nomi scelti per non essere confusi con `effectiveMinutes()`/`effectiveMinutesForStory()` (minuti, altra fonte, deprecato) né con `hours` (colonna). Metodi pubblici ma di fatto dettagli implementativi interni — nessun consumer esterno a questo ticket li deve chiamare.

**Criteri di completamento:**
- Story senza figli, `hours = 5.5` → `hoursWithChildren() === 5.5`
- Story senza figli, `hours = null` → `hoursWithChildren() === null`
- Story con 2 figli (`hours` 3.0 e `2.5`), padre `hours = 5.0` → `10.5`
- Story con 2 figli, padre `hours = null`, figli `3.0`/`null` → `3.0` (coalescenza)
- Story con un figlio `hours = -0.5`, padre `hours = 5.0` → `4.5` (nessun clamp)
- `idsWithChildren([])` → collection vuota (nessuna query eseguita)
- `idsWithChildren([$id1, $id2])` con figli condivisi/sovrapposti → nessun id duplicato nel risultato

**Commit suggerito:** `feat(oc:8421): helper Story::idsWithChildren e hoursWithChildren per il rollup del tempo effettivo`

---

## Task 2 — `fieldTrait::effectiveHoursField()`

**File:** `app/Traits/fieldTrait.php:520-537`

**Obiettivo:** il ramo `Text` (index/detail) usa `$this->hoursWithChildren()` invece di `$this->hours ?? 0`.

**Dettagli implementativi:**
```php
public function effectiveHoursField(NovaRequest $request, $fieldName = 'hours')
{
    if ($request->isResourceDetailRequest() || $request->isResourceIndexRequest()) {
        return Text::make(__('Effective Hours'), $fieldName, function () {
            $hours = $this->hoursWithChildren() ?? 0;
            return
                <<<HTML
                    <span >Effective Hours: $hours</span>
                HTML;
        })->asHtml()->canSee($this->estimatedHoursFieldCanSee($fieldName));
    } else {
        // ramo Number (create/update) — INVARIATO, mostra sempre $this->hours proprio,
        // mai il rollup: il form deve editare la colonna reale della story, non un valore aggregato.
        return Number::make(__('Effective Hours'), $fieldName)
            ->sortable()
            ->rules('nullable', 'numeric', 'min:0')
            ->help(__('Enter the effective time to resolve the ticket in hours.'))
            ->canSee($this->estimatedHoursFieldCanSee($fieldName));
    }
}
```
- Il ramo `Number` (create/update) **non va toccato**: mostra sempre `hours` proprio della story. Se mostrasse il rollup, il form si pre-popolerebbe con un totale aggregato e Nova lo persisterebbe come `hours` proprio al primo save — esattamente il problema per cui l'overview ha scartato l'opzione "accessor su `hours`" (overview §"Dove si applica il rollup", nota finale).

**⚠️ Discrepanza trovata rispetto alla tabella normativa dell'overview (da segnalare, non blocca il task):** verificato che il ramo `Text` di `effectiveHoursField()` **non è mai visibile sul detail della story principale** nel codice attuale — `app/Nova/Story.php` lo chiama solo dentro `fieldsInIndex()` (riga 157), mai in `fieldsInDetails()` (righe 170-215); l'intero array di `fieldsInIndex()` viene poi marcato `->onlyOnIndex()` (riga 165-167), quindi anche se il branch `isResourceDetailRequest()` internamente costruisce il campo "Text" durante il render del detail, Nova lo nasconde comunque. La riga "Detail ticket — campo Text" della tabella normativa dell'overview descrive quindi un punto **oggi non raggiungibile in UI** per la risorsa `Story` principale (lo è invece per `ArchivedStoryShowedByCustomer`, che però lo chiama solo in `fieldsInIndex()` anch'essa — stesso discorso). La modifica va fatta comunque (il metodo è condiviso), ma **non aspettarsi un cambiamento visibile sul detail della story in QA manuale** — è atteso solo sull'index. Già registrato in `notes.md`.

**Criteri di completamento:**
- Test Nova/HTTP che verifica il testo renderizzato del campo Text sull'index di una story con figli include la somma corretta.
- Nessuna regressione sul ramo `Number` (create/update continua a leggere/scrivere `hours` proprio).

**Commit suggerito:** `feat(oc:8421): rollup su effectiveHoursField (ramo Text index)`

---

## Task 3 — `Nova\Metrics\StoryTime::calculate()`

**File:** `app/Nova/Metrics/StoryTime.php`

**Obiettivo:** i tre rami (Tag, Story con id, Story senza id/index) sommano tempo effettivo padre+figli invece del solo `hours` diretto, tutti riusando `Story::idsWithChildren()` (Task 1).

**Dettagli implementativi per ramo:**

1. **`$requestModel instanceof Story` con `id` valorizzato** (detail Story):
   ```php
   $query = Story::whereIn('id', Story::idsWithChildren([$requestModel->id]));
   ```
   Il resto (`$this->precision(2)->sum($request, $query, 'hours')`) resta invariato.

2. **`$requestModel instanceof Story` senza `id`** (card in cima all'index Stories) — la query di partenza è `$requestResource->indexQuery($request, (new Story)->newQuery())` (righe filtrate/ricercate dall'utente). Serve estendere quell'insieme di ID con i figli diretti, **senza perdere i filtri applicati**:
   ```php
   $baseQuery = $requestResource->indexQuery($request, (new Story)->newQuery());
   $baseIds = (clone $baseQuery)->pluck('id');
   $query = Story::whereIn('id', Story::idsWithChildren($baseIds));
   ```
   Attenzione: `indexQuery()` può già avere `select`/`with` non compatibili con un secondo `pluck('id')` sulla query clonata — verificare in test che il `clone` non riesegua side-effect indesiderati (nessuno noto oggi in `Story::indexQuery()`, righe 137-142).

3. **`$requestModel instanceof Tag`** (detail Tag):
   ```php
   $taggedIds = Story::whereRelation('tags', 'taggables.taggable_type', Story::class)
       ->whereRelation('tags', 'taggables.tag_id', $requestModel->id)
       ->pluck('id');
   $query = Story::whereIn('id', Story::idsWithChildren($taggedIds));
   ```

**Criteri di completamento:**
- Card "Story Time" sul detail di un padre con figli mostra la somma corretta.
- Card in cima all'index Stories, con e senza filtro di ricerca attivo, mostra la somma corretta includendo i figli delle righe filtrate.
- Card sul detail di un Tag mostra l'unione deduplicata (story taggate ∪ loro figli, contate una volta sola anche se il figlio ha lo stesso tag del padre).

**Commit suggerito:** `feat(oc:8421): rollup sui tre rami di StoryTime::calculate()`

---

## Task 4 — `Tag::getTotalHoursAttribute()` con unione deduplicata + memoization

**File:** `app/Models/Tag.php:45-52`

**Obiettivo:** sommare `hours` sull'unione deduplicata (story taggate ∪ loro figli diretti), e memoizzare il risultato per istanza (il valore è chiamato 3-4 volte per riga in `app/Nova/Tag.php` e `app/Nova/TagGroup.php`).

**Dettagli implementativi:**
```php
protected ?float $totalHoursMemo = null;
protected bool $totalHoursComputed = false;

public function getTotalHoursAttribute()
{
    if ($this->totalHoursComputed) {
        return $this->totalHoursMemo;
    }
    $this->totalHoursComputed = true;

    $taggedIds = $this->tagged()->pluck((new Story)->getTable() . '.id');
    if ($taggedIds->isEmpty()) {
        return $this->totalHoursMemo = null;
    }

    $allIds = Story::idsWithChildren($taggedIds);

    return $this->totalHoursMemo = round(Story::whereIn('id', $allIds)->sum('hours'), 2);
}
```
- **Memoization a livello di modello (`Tag`), non solo di risorsa Nova**: risolve il rischio N+1 di `app/Nova/Tag.php:61-85` (chiamate dirette) **e** propaga automaticamente a `app/Nova/TagGroup.php` (che eredita `getTotalHoursAttribute()` da `Tag` senza override — vedi `app/Models/TagGroup.php:37`, dove `tagged()` è già ridefinito su `stories()`/`tag_group_stories`) e a `Nova\Metrics\TagHoursTotal` (nessuna modifica di codice lì ma beneficia della memoization). Copre il Requisito "memoization" con un solo punto di modifica, non tre.
- **Attenzione al ciclo di vita dell'istanza Nova**: verificare in test che ogni richiesta HTTP dell'index Tags/TagGroups istanzi un modello `Tag`/`TagGroup` per riga (comportamento standard di Nova, un `Resource` per riga con il proprio `$model()`), così la memoization è per-riga e non trapela tra righe diverse nella stessa risposta. Se in fase di test emergesse un caso di riuso della stessa istanza tra righe diverse, la memoization andrebbe invalidata esplicitamente (non atteso, ma da verificare con un test dedicato).
- `getEstimateAttribute()` **non tocca**, zero righe (overview, invariato).

**Criteri di completamento:**
- Tag con 2 story taggate (una con 2 figli non taggati) → `getTotalHoursAttribute()` include i figli.
- Tag con una story taggata E il suo figlio anch'esso taggato con lo stesso tag → conteggiato **una sola volta** (non doppio).
- `getTotalHoursAttribute()` chiamato 4 volte sulla stessa istanza esegue **una sola query SQL** (assert con `DB::listen`/`assertQueryCount` o equivalente).
- `TagGroup` (stessa gerarchia) beneficia dello stesso comportamento senza modifiche proprie.

**Commit suggerito:** `feat(oc:8421): Tag::getTotalHoursAttribute con unione deduplicata padre-figli e memoization`

---

## Task 5 — Anti-N+1 sull'index globale Stories — ❌ RIMOSSO in review formale

**Stato:** implementato, poi **revertito** dopo la review formale (5 finder paralleli, 2026-09-09). L'idea originale (subquery correlata `addSelect` su `Story::indexQuery()` + ramo "precomputed" in `hoursWithChildren()`) è descritta sotto solo per memoria storica — **non è nel codice finale**.

**Perché è stato rimosso — tre problemi indipendenti, tutti confermati:**
1. **Correttezza**: `Query\Builder::onceWithColumns()` (usato internamente da `pluck()`) sostituisce le colonne solo se non erano già impostate. L'`addSelect` permanente su `indexQuery()` le impostava sempre, quindi qualsiasi `pluck()` successivo sulla stessa query (es. `app/Nova/Filters/TaggableTypeFilter.php:49-61`, `CreatorStoryFilter.php`) ignorava silenziosamente le colonne richieste e leggeva dati sbagliati. Confermato leggendo il sorgente di Laravel (`vendor/laravel/framework/.../Query/Builder.php:4072-4085`).
2. **Efficacia**: nessuna delle risorse Nova realmente usate in produzione (`DeveloperStory`, `CustomerStory`, `AssignedToMeStory`, `BacklogStory`, ecc.) chiama `parent::indexQuery()` — ognuna sovrascrive completamente il metodo. L'ottimizzazione si applicava solo alla risorsa `Story` base, priva di voce di menu propria. Verificato empiricamente in tinker: 15 query aggiuntive su 5 righe con figli via `DeveloperStory::indexQuery()`.
3. **Anche dove attivo**: il fallback per "figli a somma zero" (`children_hours_sum === 0.0`) eseguiva comunque una query `exists()` per riga — e la somma zero è il caso più comune (story senza figli), vanificando l'obiettivo anche sulla sola risorsa `Story` base.

**Decisione:** `Story::indexQuery()` torna al codice originale pre-oc:8421 (nessuna modifica). `hoursWithChildren()` (Task 1) resta con un solo path — query diretta per ogni chiamata quando la story ha figli, nessun precaricamento. L'N+1 sull'index Stories è un **rischio accettato**, documentato in overview.md §Rischi, non un problema silenziosamente "risolto" da codice che in pratica non funzionava.

**Nessun commit per questo task** — le modifiche fatte e poi revertite non compaiono nella history finale (nessun commit intermedio creato durante l'esecuzione).

---

## Task 6 — Verifica N+1 su `app/Nova/Tag.php` e `app/Nova/TagGroup.php`

**File:** `app/Nova/Tag.php:61-85`, `app/Nova/TagGroup.php` (blocco "SAL t" equivalente)

**Obiettivo:** confermare che la memoization del Task 4 basta a risolvere il Requisito "N+1/costo ripetuto su SAL t" — **nessuna modifica di logica in questi due file**, solo verifica.

**Criteri di completamento:**
- Test che conta le query SQL eseguite per il rendering di una singola riga index Tags/TagGroups con "SAL t" visibile: non più di 1 query aggiuntiva per il totale ore (oltre alle query standard di Nova per la risorsa).

**Commit suggerito:** nessuno dedicato — incluso nel commit di Task 4 se il test si scrive insieme, altrimenti `test(oc:8421): verifica assenza N+1 su SAL t Tag e TagGroup`

---

## Task 7 — Docblock `Story::effectiveMinutes()` — ✅ già fatto

**File:** `app/Models/Story.php:600-603`

**Stato:** completato in questa sessione (2026-09-09), prima della revisione v2 del piano. Nessuna azione residua.

```php
/**
 * Minuti trascorsi in stato `progress`, sommando tutti gli intervalli attivi.
 * Alimenta solo la dashboard Team Performance — NON è la fonte delle ore effettive
 * lette da Nova/Tag SAL/report/API (quella è la colonna `hours`, vedi docs/calcolo-ore-effettive-stimate.md).
 * Restituisce null se non ci sono mai stati log di progress.
 */
public function effectiveMinutes(): ?int
```

**Commit suggerito:** `docs(oc:8421): correggi il docblock fuorviante di Story::effectiveMinutes`

---

## Task 8 — Comando artisan `tags:compare-sal-rollup`

**File:** nuovo `app/Console/Commands/CompareTagsSalRollup.php`

**Obiettivo:** comando read-only che, per ogni tag, stampa il valore attuale (`sum('hours')` sulle sole story taggate — **calcolato senza passare dal nuovo `getTotalHoursAttribute()`**, altrimenti "attuale" e "nuovo" leggerebbero lo stesso codice) vs il nuovo (unione deduplicata), evidenziando i tag con story a `hours` negativa nell'insieme aggregato.

**Dettagli implementativi:**
```php
class CompareTagsSalRollup extends Command
{
    protected $signature = 'tags:compare-sal-rollup {--only-changed : mostra solo i tag con uno scostamento}';
    protected $description = 'Confronta il SAL attuale (solo story taggate) col nuovo SAL (story taggate + figli), read-only.';

    public function handle(): int
    {
        Tag::query()->whereNull('taggable_type')->chunk(100, function ($tags) {
            foreach ($tags as $tag) {
                $before = round($tag->tagged()->sum('hours'), 2);
                $after = $tag->getTotalHoursAttribute(); // già il nuovo comportamento, post-Task 4
                $hasNegativeChild = Story::whereIn('id', Story::idsWithChildren($tag->tagged()->pluck('id')))
                    ->where('hours', '<', 0)->exists();
                // stampa tabellare: id, nome, before, after, delta, flag negativi
            }
        });
        return self::SUCCESS;
    }
}
```
- **Nessuna scrittura**: solo output tabellare (`$this->table(...)`).
- **Chiarito in esecuzione**: `TagGroup` è già un modello/tabella separata (`tag_groups`), mai restituita da una query su `Tag` — nessun filtro necessario per "escluderla". Il comando esegue `Tag::all()` per i tag e `TagGroup::all()` separatamente, in due sezioni distinte dell'output.
- **Bug trovato in review formale e corretto**: la prima implementazione filtrava con `Tag::whereNull('taggable_type')`, pensando (erroneamente) che servisse a escludere `TagGroup`. Effetto reale: escludeva ~93% dei tag reali del DB (quelli con `taggable_type = Project`, verificato: 412 tag su 443), cioè proprio i tag "manuali" che il comando deve misurare secondo l'overview ("Il buco reale è solo sui tag manuali"). Rimosso il filtro, aggiunto test di regressione dedicato.

**Criteri di completamento:**
- Eseguito su `orchestrator_test` con dati di fixture (padre+figli, tag condiviso) produce output coerente coi criteri del Task 4.
- Include tag con `taggable_type` valorizzato (es. `Project`), non solo quelli senza (test `test_compare_sal_rollup_command_includes_project_tags`).
- **Da eseguire su dati reali prima del merge** (out of scope per il codice, ma azione richiesta prima della PR — annotare in `notes.md`).

**Commit suggerito:** `feat(oc:8421): comando artisan tags:compare-sal-rollup (read-only)`

---

## Task 9 — Test automatici

**File:** nuovi test in `tests/Feature/` (es. `StoryHoursRollupTest.php`, estensioni a `TagHoursMetricsTest.php` esistente)

Casi da coprire (dall'overview, Requisiti):
- [ ] Padre con N figli — somma corretta.
- [ ] Padre senza figli — comportamento invariato (`hours` così com'è, incluso `null`).
- [ ] Story figlia (senza propri figli) — `hoursWithChildren()` ritorna il proprio `hours`, non aggrega verso l'alto.
- [ ] Figlio taggato con lo stesso tag del padre — nessun doppio conteggio sul tag (caso normale, non edge case).
- [ ] Padre con `hours = null` e figli valorizzati → totale = somma figli, non `null`.
- [ ] Padre valorizzato e figli con `hours = null` → coalescenza a 0 sulla parte mancante.
- [ ] Figlio con `hours` negativa → il totale scende, nessun clamp a 0.
- [ ] Story con figli la cui somma `hours` è 0 (non "nessun figlio") → totale aggregato 0, non il valore proprio del padre (Task 5, caso limite del precaricamento).
- [ ] Non-regressione: `Tag::getEstimateAttribute()`, `estimatedHoursField()` (ramo Number), export Excel (`SelectedStoriesToExcel`), `Api\StoryController`, `ReportController` — invariati.
- [ ] Conteggio query SQL (Task 4, Task 5, Task 6) — nessun N+1 introdotto.

Riusare i pattern già presenti in `tests/Feature/TagHoursMetricsTest.php` e `tests/Feature/TagSalTest.php` per lo stile delle asserzioni sul SAL.

**Commit suggerito:** `test(oc:8421): copertura completa rollup tempo effettivo padre-figlio`

---

## Ordine di esecuzione consigliato

1. Task 1 (helper condiviso `idsWithChildren`/`hoursWithChildren`, base di tutto)
2. Task 2, Task 3 (consumano l'helper — indipendenti tra loro, eseguibili in qualsiasi ordine)
3. Task 4 (consuma `idsWithChildren`, indipendente da Task 2/3)
4. Task 5 (ottimizzazione, dipende da Task 1 già adattato per il precaricamento)
5. Task 6 (verifica, dipende da Task 4)
6. Task 7 — ✅ già fatto
7. Task 8 (dipende da Task 4 completato)
8. Task 9 (trasversale, scritto man mano ma completato per ultimo)
