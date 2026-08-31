> Ticket: oc:8421

# Relazione padre-figlio: suddivisione ticket cliente e rollup di tempo effettivo e stima

## Cosa cambia
Un ticket padre (riferimento per il cliente) può essere suddiviso in più ticket figli tecnici (già possibile oggi, N figli per padre, gerarchia a 2 livelli). Il tempo effettivo e la stima visti sul padre — e sul tag che lo contiene — includeranno anche il lavoro svolto sui figli, tramite un nuovo metodo di rollup su `Story` e un aggiornamento delle aggregazioni su `Tag`. La cascata di stato dal padre ai figli viene rimossa: ogni figlio avanza in modo indipendente.

## Perché
Ticket corposi con attività su sviluppatori diversi (es. backend/frontend) vengono oggi suddivisi in figli, ma tempo e stima restano visibili solo sul singolo ticket: il lavoro sui figli risulta "non tracciato" agli occhi di chi guarda il padre o il tag che lo raggruppa.

## Decisioni già presenti nel ticket (non ridiscusse)
- Nessuna modifica di schema DB, nessuna migration.
- Nuovo metodo di rollup (`totalEffectiveMinutes()` o simile) **distinto** da `effectiveMinutes()`, che resta invariato nella sua semantica attuale (tempo proprio della singola story).
- Sul padre vanno mostrati **entrambi** i valori (proprio + totale con figli), mai uno al posto dell'altro.
- Su una story figlia (o senza figli) il rollup coincide col tempo proprio — sempre definito, mai null.
- I figli non vanno taggati; le loro ore/stime entrano nel tag solo attraverso il padre, in modo idempotente (nessun doppio conteggio se un figlio risultasse comunque taggato con lo stesso tag del padre).
- `Tag::getTotalHoursAttribute()` passa dalla colonna deprecata `hours` al rollup su `effectiveMinutes()`. La colonna `hours` resta in DB (sola lettura, non rimossa).
- `Tag::getEstimateAttribute()` aggrega anche gli `estimated_hours` dei figli — stessa aggregazione delle ore, altrimenti il SAL% risulterebbe sistematicamente falsato al rialzo.
- Cascata di stato padre→figli (`app/Models/Story.php:256-261`) rimossa. Nessuna propagazione introdotta nella direzione opposta (figli→padre).

## Requisiti (integrati con le indagini di questa sessione)
- [ ] Nuovo metodo di rollup su `App\Models\Story` (proprio + somma dei figli), `effectiveMinutes()` invariato
- [ ] **Fonte per i figli: query diretta su `parent_id`** (`Story::where('parent_id', $id)`), non la relazione `childStories()` (pivot `story_story`). Verificato nel codice (non sui dati, il DB locale non ha relazioni reali): la pivot si sincronizza solo se una story *già esistente* riceve un cambio di `parent_id` in un salvataggio separato dalla creazione — se un figlio viene creato direttamente con `parent_id` già valorizzato (possibile dal form "Create Story" di Nova stesso, dove il campo `parentStory` è presente senza restrizioni onlyOnUpdate), l'evento è `created` non `updated`, e la sincronizzazione verso la pivot non scatta mai. La query diretta su `parent_id` è quindi l'unica fonte affidabile in ogni scenario di creazione.
- [ ] Sul **detail** della story padre sono visibili entrambi i valori (tempo proprio + totale con figli). **Non** sull'index globale delle Stories (per non moltiplicare il rischio N+1 su una lista con potenzialmente molte righe) — visibile solo quando la story ha effettivamente dei figli.
- [ ] **`effectiveHoursField()` (`app/Traits/fieldTrait.php:519-534`) aggiornato per usare `effectiveMinutes()` invece della colonna deprecata `hours`** che mostra oggi (bug preesistente non segnalato nel ticket, trovato in questa sessione). Evita 3 valori diversi e incoerenti sulla stessa card (colonna legacy, tempo proprio, totale).
- [ ] `Tag::getTotalHoursAttribute()` calcolato dal rollup su `effectiveMinutes()`, includendo i figli non taggati, senza doppi conteggi (deduplica per ID story: story taggate ∪ loro figli, come insieme unico)
- [ ] `Tag::getEstimateAttribute()` include gli `estimated_hours` dei figli delle story taggate, stessa logica di deduplica
- [ ] SAL% (`getSalAttribute`, `calculateSalPercentage`), colonna `SAL t` (`app/Nova/Tag.php:61-85`, `onlyOnIndex()`) e metrica `TagHoursTotal` restano coerenti (stessa base aggregata per numeratore e denominatore)
- [ ] **N+1 su `SAL t` (index Tags)**: `effectiveMinutesForStory()` fa 1 query per story; con più tag e più story per tag l'index esploderebbe in query. Soluzione: query batch (1 query su tutti gli `StoryLog` delle story rilevanti per la pagina corrente, raggruppati in memoria per story_id), non un loop di chiamate al metodo esistente. Nessuna cache/Redis (evitato: il Redis locale in questo ambiente risulta non raggiungibile, comunque fuori scope introdurre una dipendenza da cache per questo ticket)
- [ ] Rimossa la cascata status padre→figli
- [ ] **Comando artisan read-only di confronto** (es. `tags:compare-sal-rollup`) che stampa, per ogni tag, il valore attuale (`sum('hours')` diretto) vs il nuovo (rollup con figli) — nessuna scrittura, da eseguire dal dev in un ambiente con dati reali prima del merge per misurare lo scostamento sui SAL già comunicati ai clienti
- [ ] **Test automatici** (dal ticket): padre con N figli, padre senza figli, story figlia, figlio taggato con lo stesso tag del padre (non-doppio-conteggio), story senza alcun `StoryLog` di progress
- [ ] **Test esistente da aggiornare**: `tests/Feature/StoryRelationshipTest.php::it_propagates_status_changes_from_parent_to_child` verifica oggi la cascata che va rimossa — va invertito (rinominato, es. `it_does_not_propagate_status_changes_from_parent_to_child`, assert che il figlio NON cambi status), non solo eliminato, per documentare esplicitamente il cambio di comportamento nella cronologia dei test

## Rischi
- **I numeri di SAL cambiano** rispetto a quelli eventualmente già comunicati ai clienti (passaggio da `hours` a rollup su `effectiveMinutes()` + inclusione figli). Mitigato dal comando di confronto pre-merge (vedi Requisiti) — misurazione delegata al dev con accesso ai dati reali, non eseguibile da questa sessione (ambiente locale con soli dati fittizi).
- **Doppia fonte di verità padre-figlio** (`parent_id` vs pivot `story_story`, sincronizzata da tre hook distinti su due modelli — `Story::updated`, `StoryPivot::saving`, `StoryPivot::deleting` — uno dei quali ingoia le eccezioni senza loggarle, `app/Models/Story.php:288-291`). Mitigato per il rollup usando sempre e solo `parent_id` (vedi Requisiti) — il rischio sulla pivot resta per `childStories()` come relazione (usata altrove, es. nel campo Nova "Child Stories"), ma non entra nel calcolo delle ore/stime.
- **N+1 su `SAL t`**: mitigato con query batch, vedi Requisiti.
- **Dato di produzione non misurabile da questa sessione**: quante story figlie condividono oggi un tag col padre, quanto scostano i SAL — richiede l'esecuzione del comando di confronto su un ambiente con dati reali.
- **Bug preesistente in `effectiveHoursField()`** (mostra `hours` invece di `effectiveMinutes()`): la correzione, pur necessaria per evitare valori incoerenti sulla stessa card, cambia un valore già visibile agli utenti Nova su ogni Story esistente (non solo i padri) — non solo sui nuovi rollup. Accettato consapevolmente in questa sessione, da monitorare dopo il merge.

## Out of scope
- Modifiche di schema DB e migration di qualsiasi tipo
- Eliminazione della pivot ridondante `story_story` e bonifica degli hook che la sincronizzano (incluso il `catch (\Exception $e) { $e; }` che ingoia errori silenziosamente) — ticket separato
- Rimozione della colonna `hours` e delle sue scritture
- Gerarchia a più di 2 livelli
- Propagazione di status dai figli al padre
- Ridistribuzione o riassegnazione automatica di assegnatari tra padre e figli
- Introduzione di cache/Redis per il rollup (mitigazione N+1 solo via query batch in questo ciclo)
- Visibilità del "totale con figli" sull'index globale delle Stories (solo detail, vedi Requisiti)

## Moduli toccati
- `app/Models/Story.php` — nuovo metodo di rollup; rimozione cascata status (righe 256-261)
- `app/Models/Tag.php` — `getTotalHoursAttribute()`, `getEstimateAttribute()`, batch query per evitare N+1
- `app/Nova/Story.php` — campo con il totale aggregato sul detail, solo se la story ha figli
- `app/Traits/fieldTrait.php` — `effectiveHoursField()` aggiornato a `effectiveMinutes()`
- `app/Nova/Tag.php` — colonna `SAL t`, se necessario per coerenza col batching
- `app/Nova/Metrics/TagHoursTotal.php` — verifica coerenza con la nuova base
- `app/Console/Commands/` — nuovo comando `tags:compare-sal-rollup` (read-only)
- `tests/Feature/` — nuovi test sul rollup e sul non-doppio-conteggio; aggiornamento di `StoryRelationshipTest::it_propagates_status_changes_from_parent_to_child`
- `lang/it.json`, `lang/en.json` — nuove chiavi per le etichette dei due valori sul detail Story
