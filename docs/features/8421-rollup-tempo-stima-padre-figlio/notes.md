> Ticket: oc:8421

# Notes — Relazione padre-figlio: rollup di tempo effettivo

## Deviazioni dal piano
Nessuna deviazione sui contenuti tecnici — Task 1-9 completati nell'ordine del piano v2. Deviazione di processo: durante l'esecuzione il branch di oc:8421 è stato rebasato da terzi — gestito senza perdita di lavoro (auto-stash di Git, recuperato).

## Bug trovati
- **Discrepanza nella tabella normativa dell'overview (v4)**: la riga "Detail ticket — campo Text" (`fieldTrait::effectiveHoursField()`, ramo detail) descrive un punto oggi **non raggiungibile in UI** per la risorsa `Story` principale. `app/Nova/Story.php` chiama `effectiveHoursField()` solo dentro `fieldsInIndex()` (riga 157), mai in `fieldsInDetails()` (righe 170-215); l'intero array di `fieldsInIndex()` viene poi marcato `->onlyOnIndex()` (righe 165-167). Stesso discorso per `ArchivedStoryShowedByCustomer`. La modifica in Task 2 va fatta comunque (metodo condiviso, nessun consumer attuale lo chiama dal ramo detail ma nulla lo esclude in futuro), ma non aspettarsi un cambiamento visibile sul detail della story in QA manuale — solo sull'index.
- **Test flaky preesistente, non di oc:8421**: `tests/Feature/StoryChildFieldTest::il_campo_ticket_correlati_e_un_hasmany` fallisce in modo intermittente (~1 volta su 3) anche **in isolamento completo e senza nessuna modifica di oc:8421** — verificato con 3 run consecutivi sulla base pulita (pass/fail/pass). Non e una regressione introdotta da questo ticket: bisecato a lungo scambiando i file modificati uno a uno, ma la causa vera si e rivelata essere flakiness del test stesso, non del codice. Probabile causa: `canSee()` del campo `childStories` in `app/Nova/Story.php` dipende da `$request->user()->hasRole(...)`, o interazione con `Nova::resourcesIn()` chiamato in `setUp()`, entrambi candidati per una race/stato non deterministico. Non affrontato in questo ticket (fuori scope, non nostro codice).

## Decisioni
- **`docs/calcolo-ore-effettive-stimate.md:226` non aggiornato**: cita ancora il vecchio docblock di `effectiveMinutes()` come *evidenza storica* di uno stato passato (oc:8446, già mergiato). Non è una claim viva sul comportamento attuale del codice, quindi lasciato invariato — fuori scope di oc:8421. Se in un ciclo futuro si volesse allineare, andrebbe fatto come piccola nota in quel documento, non come parte di questo ticket.

## Follow-up
- Tutti i task del piano implementati e coperti da test (`tests/Feature/StoryHoursRollupTest.php`, 19 test dopo la review, tutti verdi). Suite completa del progetto: verde a parte il flaky preesistente `StoryChildFieldTest` descritto sopra (non nostro).
- **Comando `tags:compare-sal-rollup` da eseguire su dati reali prima del merge** (richiesto dall'overview, non ancora fatto in questa sessione — richiede DB di produzione/staging, non `orchestrator_test`).
- Segnalare il test flaky `StoryChildFieldTest::il_campo_ticket_correlati_e_un_hasmany` (causa probabile trovata dal finder 1 della review formale: `UserFactory` assegna ruoli casuali, e quando include `Customer` la `canSee()` del campo nasconde il campo).

## Review formale (wm-review-ticket, 2026-09-09)

5 finder paralleli lanciati sul diff completo (`git diff HEAD`, 11 file). Verdetto: **DA CORREGGERE → corretto in sessione, poi APPROVATO**.

### Finding bloccanti (tutti corretti prima del commit)

**1. `Story::hoursWithChildren()` + `Story::indexQuery()` — la mitigazione anti-N+1 (Task 5) era controproducente su tre fronti**, tutti confermati indipendentemente da 3 finder diversi più verifica diretta sul codice sorgente Laravel:
- Rompeva `app/Nova/Filters/TaggableTypeFilter.php` (e probabilmente `CreatorStoryFilter.php`): `Query\Builder::onceWithColumns()` non sovrascrive le colonne se erano già impostate, quindi `addSelect()` su `indexQuery()` faceva leggere dati sbagliati a qualsiasi `pluck()` successivo sulla stessa query — bug di correttezza silenzioso, non solo di performance.
- Non veniva mai ereditata da nessuna risorsa Nova realmente in produzione (`DeveloperStory`, `CustomerStory`, `AssignedToMeStory`, ecc. sovrascrivono `indexQuery()` senza `parent::indexQuery()`) — verificato empiricamente in tinker (15 query extra su 5 righe).
- Anche dove attiva, il fallback per "figli a somma zero" (il caso più comune) eseguiva comunque una query per riga.
- **Fix**: rimossa interamente la `addSelect` da `app/Nova/Story.php::indexQuery()` (torna al codice originale pre-oc:8421) e il ramo "precomputed" da `hoursWithChildren()`. N+1 sull'index Stories resta rischio accettato (overview §Rischi), non silenziosamente "risolto". Aggiunto test di regressione mirato (`test_index_query_does_not_pollute_columns_for_downstream_pluck`) per impedire che l'errore si ripresenti.

**2. `app/Console/Commands/CompareTagsSalRollup.php` — il filtro `Tag::whereNull('taggable_type')` escludeva ~93% dei tag reali**, inclusi proprio quelli "manuali" (Project) che il comando doveva misurare secondo l'overview. `TagGroup` è già una tabella/modello separato: nessun filtro serviva per escluderlo. **Fix**: rimosso il filtro (`Tag::all()`), aggiunto test di regressione (`test_compare_sal_rollup_command_includes_project_tags`) con un tag `taggable_type = Project`.

### Finding cleanup (applicati)
- Docblock di `Story::idsWithChildren()` riformulato: descrive la query su `parent_id` / `idsWithChildren()`, senza presentare `childStories()` come pivot desincronizzata (`childStories()` è `HasMany` sulla stessa colonna).
- Doppia riga vuota rimossa in `app/Nova/Metrics/StoryTime.php`.
- `CompareTagsSalRollup.php`: chiarito che `$delta === null` (dati assenti) è distinto da `$delta == 0.0` (nessuno scostamento) nel filtro `--only-changed`.

### Finding cleanup non applicati (accettati come limite noto, documentati in overview §Rischi)
- **Card "Story Time" in cima all'index Stories**: Nova riapplica i Filter attivi (`applyFilterQuery()`) sull'insieme padre+figli già allargato da `idsWithChildren()`, quindi un figlio con status diverso da quello filtrato sui padri può essere escluso dal totale mostrato pur essendo incluso nel rollup del padre sul suo detail. Non è corruzione di dati, solo un insieme leggermente diverso da quello ideale in presenza di filtro attivo. Risolverlo richiederebbe intercettare il meccanismo di filtro interno di Nova — valutato non necessario per questo ciclo.
- Memoization di `Tag::getTotalHoursAttribute()` non ha invalidazione esplicita (properties di istanza, non parte di `$attributes`): nessun chiamante attuale rilegge lo stesso `$tag` dopo una mutazione nello stesso ciclo di vita PHP, quindi non sfruttabile oggi, ma è una trappola latente per codice futuro (es. un job che processa più story riusando la stessa istanza `Tag`). Non è stato aggiunto un commento esplicito nel codice — valutato accettabile, il pattern è comunque isolato in un solo metodo.
- Duplicazione minore tra `Tag::getTotalHoursAttribute()` e `CompareTagsSalRollup.php` nel modo di ottenere gli id delle story taggate (uno usa `(new Story)->getTable().'.id'`, l'altro `'stories.id'` hardcoded) — stesso risultato, stile diverso. Non centralizzato per tenere il diff minimo su un comando diagnostico.

## Documentazione (v7)
- Overview/piano/notes allineati allo schema attuale: indice `stories_parent_id_index` già presente, nessuna migration in questo ticket. Rimossi i riferimenti a ticket collaterali ormai chiusi e il rischio «tabella senza indice», che non è più vero su `develop`.

