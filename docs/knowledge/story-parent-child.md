# Relazione padre-figlio fra Story

Come si lega un ticket padre ai suoi figli tecnici e come si aggregano tempo e stima.

## Stato attuale

### La fonte di verità è `stories.parent_id` (oc:8445)
- **La tabella pivot `story_story` è DEPRECATA: non leggerla, non scriverla, non ricollegarla.**
  È rimasta a DB con 51 righe stantie e `app/Models/StoryPivot.php` esiste ancora con gli hook
  `saving()`/`deleting()` vivi (scrivono e azzerano `stories.parent_id`), ma nessun codice li
  innesca più. Il drop fisico è rinviato a un ticket dedicato.
- `childStories()` è una `hasMany` su `stories.parent_id`. Il campo Nova "Ticket correlati" è
  read-only: **`searchable()` e `filterable()` non esistono su `Laravel\Nova\Fields\HasMany`**
  (esistono su `BelongsToMany`) e lasciarli in catena produce un `BadMethodCallException`, cioè
  un 500 sul detail di **ogni** Story, non solo dei padri. Ricerca e filtri dentro la sezione
  continuano comunque a funzionare: sono nativi dell'index della relazione. L'unica perdita
  reale è il bottone "Attach"; "Crea Ticket" resta.
- **La modifica al campo si propaga a tutte le viste**: `CustomerStory`, `DeveloperStory` e
  `AssignedToMeStory` estendono `App\Nova\Story` senza ridefinire `fields()`.
- **Nessun cascade di status padre→figli.** Faceva `$child->save()`, quindi non allineava solo lo
  stato: generava StoryLog, ricalcolo `hours` e notifiche a developer e tester del figlio. Chi
  lavora su un figlio non riceve **alcun** segnale quando il padre cambia stato, e l'help text di
  "Parent Story" è stato riscritto in entrambe le lingue per non promettere il contrario.
- **`DuplicateStory` non copia né figli né padre** e forza `parent_id = null` dopo
  `Story::create($story->toArray())`, che quel campo lo copia: rimuovere il solo
  `parentStory()->associate(...)` non basta.
- **L'indice `stories_parent_id_index` non è una seconda fonte di verità**: PostgreSQL non
  indicizza automaticamente le colonne con FK e l'unico indice di `stories` era `stories_pkey`.
  Il pivot forniva implicitamente un indice col suo `unique(parent_id, child_id)`; senza, il
  passaggio alla colonna avrebbe peggiorato il piano su una tabella di 7.538 righe (locale,
  2026-09-12) letta per riga
  in campi Nova non eager-loaded.
- **Vincolo `stories_parent_id_not_self`** contro l'auto-parentela. I cicli a due passaggi
  (A→B→A) restano possibili, ma producono una `ValidationException` recuperabile invece di una
  `\Exception` nuda, che rendeva la story impossibile da salvare per sempre — anche dai job in
  coda e dai comandi schedulati, che fallivano in loop. L'invariante non era garantita da nulla:
  `parent_id` è in `$fillable` e nella whitelist di `Api/StoryController::update()`, e il campo
  Nova non filtra i candidati.
- **Il rollback è lossy** pur non avendo migration di dati: la SQL di ripopolamento del pivot è in
  `docs/features/8445-ticket-correlati-non-mostra-i-ticket-figli/notes.md` e va eseguita **prima**
  del `git revert`, non dopo.

### Rollup di tempo e stima (oc:8421)
- **La stima è top-down, il tempo effettivo è bottom-up.** Si stima il padre (che coincide
  tipicamente con la feature); le stime sui figli sono una suddivisione interna di quel budget e
  **non si sommano** al padre, altrimenti lo stesso budget verrebbe contato due volte. Il tempo
  effettivo invece è misurato sui `StoryLog` e va sommato, altrimenti suddividere un ticket fa
  sparire il lavoro dai totali.
- Il rollup vive in **un solo metodo pubblico**, `Story::hoursWithChildren(): ?float`
  (`app/Models/Story.php:410`), che ritorna `hours` quando la story non ha figli; l'unico chiamante
  è `app/Traits/fieldTrait.php:524`, cioè il punto che già esponeva `hours` — vedi
  [Ore stimate ed effettive](ore-stimate-ed-effettive.md).
- I figli si risolvono sulla colonna (`Story::where('parent_id', $id)` / `idsWithChildren()`).
  Nessuna migration: indice e constraint arrivano da oc:8445.
- **L'N+1 sull'index Stories è un rischio accettato**, non risolto.

## Come ci siamo arrivati

- **Perché il pivot si era disallineato** (15 relazioni su 66 mancanti, nessuna in eccesso,
  oc:8445): la sync viveva in `Story::booted()` → `static::updated` con tre difetti sommati —
  racchiusa in `if (auth()->user())` (nessuna scrittura da comandi, job o seed la raggiungeva),
  presente solo su `updated` e **mai** su `created` (una story creata già con `parent_id` non
  entrava mai nel pivot), e con `catch` silenzioso. Il bug era invisibile perché `parentStory()`
  leggeva la colonna e `childStories()` il pivot: dal figlio il legame si vedeva, dal padre no.
  La diagnosi iniziale del ticket (`canSee` errato) era sbagliata.
- **Mantenere il cascade con un avviso preventivo**: valutato in challenge e scartato. Nota:
  il cascade dipendeva dalla freschezza dell'istanza — l'hook `created` esegue un `save()` interno
  che desincronizza il modello in memoria e rende `isDirty('status')` falso, quindi su un modello
  appena creato non scattava affatto.
- **Rollup costruito su `Story::effectiveMinutes()`** (v1 di oc:8421): ribaltato da oc:8446, che ha
  stabilito che la fonte reale è la colonna `hours` e che `effectiveMinutes()` non ha mai avuto
  un chiamante.
- **`addSelect()` permanente su `Story::indexQuery()`** per precaricare `children_hours_sum`
  (oc:8421): rimosso dopo review formale, controproducente su tre fronti verificati —
  (1) rompeva filtri Nova non toccati dal ticket (`TaggableTypeFilter`, `CreatorStoryFilter`):
  `Query\Builder::onceWithColumns()`, usato da `pluck()`, sostituisce le colonne solo se non erano
  già impostate, e l'`addSelect` le aveva già impostate per ogni chiamante successivo, con
  risultati silenziosamente corrotti; (2) non veniva ereditato dalle Resource realmente usate in
  produzione, che ridefiniscono il proprio `indexQuery()` senza chiamare `parent::indexQuery()`;
  (3) anche dove attivo, il fallback per "figli a somma zero" — il caso più comune — eseguiva
  comunque una query `exists()` per riga.
- **oc:8445 è prerequisito di oc:8421**: sulla relazione precedente il rollup avrebbe sommato 51
  collegamenti su 66, producendo totali silenziosamente più bassi del vero.
- **Verificato sui dati di produzione (2026-09-02)**: 0 story sono contemporaneamente padre e
  figlio, 0 sono padri di sé stesse.
