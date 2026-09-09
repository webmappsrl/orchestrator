> Ticket: oc:8445

# Ticket correlati non mostra i ticket figli nel detail Story

## Cosa cambia

Nel dettaglio di un ticket padre, la sezione **"Ticket correlati"** elenca da ora **tutti** i ticket figli realmente collegati, leggendo la colonna `stories.parent_id` invece della tabella pivot `story_story`.

La relazione `Story::childStories()` passa da `belongsToMany(Story::class, 'story_story', ...)` a `hasMany(Story::class, 'parent_id')`. La tabella pivot `story_story` **resta in database ma è deprecata**: nessun codice la legge o la scrive più.

Contestualmente vengono smantellati due comportamenti che dipendevano dal pivot:

1. **Il cascade di status padre → figli** (`Story::booted()` → `static::updated`): cambiare lo status di un ticket padre non modifica più lo status dei figli. Gli stati diventano indipendenti.
2. **La copia dei figli in `DuplicateStory`**: duplicando un ticket, il duplicato nasce senza figli.

L'help text del campo "Parent Story" viene riscritto in `it.json` e `en.json` per rimuovere la promessa del cascade e dichiarare esplicitamente che gli stati sono indipendenti.

Una migration aggiunge inoltre due blindature sulla nuova fonte unica — **nessuna delle due duplica il dato**:

1. **indice su `stories.parent_id`**, che il pivot forniva implicitamente con il suo `unique(parent_id, child_id)` e che sulla colonna non esiste (unico indice su `stories` è `stories_pkey`);
2. **vincolo anti-auto-parentela** (`parent_id IS NULL OR parent_id <> id`), oggi non impedito da nulla.

La guardia "una storia figlia non può avere figli" passa da `throw new \Exception` nudo a una `ValidationException`.

## Perché

Il bug segnalato (oc:8180 mostra "Ticket correlati" vuoto pur avendo il figlio 8414) **non è una condizione di visibilità errata**, come ipotizzato nelle note dev iniziali: il `canSee` a `app/Nova/Story.php:199` valuta `empty($this->parent_id)`, e su oc:8180 `parent_id` è `NULL` — il campo è visibile, semplicemente elenca zero righe.

La causa reale è che **la stessa relazione ha due fonti di verità disallineate**:

| Fonte | Letta da | Righe (2026-09-02) |
|---|---|---|
| colonna `stories.parent_id` | `parentStory()`, form di edit del figlio | 66 |
| pivot `story_story` | `childStories()`, campo "Ticket correlati" | 51 |

Le 66 relazioni della colonna sono un **superinsieme stretto** delle 51 del pivot: **15 relazioni** esistono solo in colonna (incluso `8180 → 8414`), **0** esistono solo nel pivot. Il pivot non contiene alcuna informazione non derivabile dalla colonna, non ha colonne extra oltre `parent_id`/`child_id`, e nessun figlio ha più di un padre.

Le tre cause della desincronizzazione, tutte nel hook `static::updated` di `app/Models/Story.php:253-300`:

1. l'intera sync è racchiusa in `if (auth()->user())` — ogni scrittura senza utente autenticato (comando artisan, job in coda, seed) non scrive mai il pivot;
2. la sync esiste solo su `updated`, mai su `created` — una story creata già con `parent_id` valorizzato (POST API, `Story::create()`) non entra mai nel pivot;
3. il `catch` è silenzioso (`$e;`) — nessun fallimento è mai comparso nei log.

Riparare la sincronizzazione lascerebbe in piedi la doppia fonte, e ogni futuro punto di scrittura di `parent_id` che dimentichi il pivot reintrodurrebbe lo stesso bug — è già successo 15 volte su 66 in modo silenzioso. Eliminando la fonte ridondante il vincolo diventa la FK del database, non un hook che dipende da `auth()->user()`.

## Requisiti

- [ ] `Story::childStories()` è una `hasMany(Story::class, 'parent_id')`
- [ ] Il detail di un ticket padre elenca **tutti** i figli con `parent_id` uguale al proprio id, incluse le 15 relazioni oggi invisibili
- [ ] Nessun codice legge o scrive più la tabella `story_story`: rimossi il blocco di sync del pivot in `Story::booted()` e il `->using(StoryPivot::class)`
- [ ] Il campo Nova "Ticket correlati" diventa `HasMany` read-only, **senza** `->searchable()`, `->filterable()` e `->nullable()`: quei metodi non esistono su `HasMany` e lasciarli produce un `BadMethodCallException`, cioè un 500 sul detail di **ogni** Story
- [ ] Perdita accettata e dichiarata: ricerca dentro il campo e filtro sull'index dei correlati non sono più disponibili
- [ ] Il cascade di status padre → figli è rimosso da `Story::booted()`
- [ ] `DuplicateStory` non copia i figli **e non copia il padre**: il duplicato nasce isolato, senza comparire nell'elenco figli di terzi ticket
- [ ] Migration: indice su `stories.parent_id`
- [ ] Migration: vincolo `parent_id IS NULL OR parent_id <> id` (anti-auto-parentela)
- [ ] La guardia "una storia figlia non può avere figli" solleva una `ValidationException` con messaggio tradotto, non una `\Exception` nuda
- [ ] Prima del deploy la query di invariante torna 0 righe: `SELECT s.id FROM stories s WHERE s.parent_id IS NOT NULL AND EXISTS (SELECT 1 FROM stories c WHERE c.parent_id = s.id)`
- [ ] L'help text del campo "Parent Story" non promette più il cascade e dichiara che gli stati sono indipendenti, con la chiave aggiornata **in entrambi** `lang/it.json` e `lang/en.json`
- [ ] `tests/Feature/StoryRelationshipTest.php` è riscritto sulla colonna `parent_id`; il test sul cascade di status è rimosso
- [ ] Un test carica il detail Nova di un ticket padre (regressione sul 500 da metodi inesistenti)
- [ ] `CLAUDE.md` documenta che `story_story` è deprecata e non va più usata
- [ ] La documentazione riporta la procedura di rollback con la SQL di ripopolamento del pivot

## Rischi

**Il cascade rimosso è un cambiamento di comportamento visibile, non solo un fix.** Finora, cambiando lo status di un padre, i figli **presenti nel pivot** venivano allineati. Dopo questo ticket nessun figlio viene più allineato. È una scelta esplicita del dev ("è un approccio che non serve più"), non un effetto collaterale. Mitigazione: help text riscritto per dichiararlo, così l'aspettativa dell'utente viene corretta nel punto in cui si crea.

**Se il cascade fosse stato mantenuto, il fix lo avrebbe esteso a 15 relazioni oggi invisibili**, di cui 4 con status divergente (`8218 done → 8228 backlog`, `8133 done → 8146 backlog`, `8181 pending_release → 8349 done`, `8180 todo → 8414 pending_release`). Al primo cambio di status del padre quei figli sarebbero stati trascinati con un `save()` completo (StoryLog, ricalcolo `hours`, sync Google Calendar, email). Rimuovendo il cascade **questo rischio si azzera** — è una ragione ulteriore a favore della scelta fatta.

**La tabella `story_story` resta in DB con 51 righe che diventano immediatamente stantie.** Nessun codice le legge, quindi non causano comportamenti errati, ma un futuro sviluppatore che trovi la tabella potrebbe ricollegarla in buona fede. Mitigazione: nota esplicita in `CLAUDE.md` e `StoryPivot.php` lasciato in repo ma non più referenziato.

**Perdita del bottone "Attach Child Story" dal detail del padre.** Accettato: quel bottone è proprio ciò che scriveva il pivot tramite `StoryPivot::saving()`, ed è una delle sorgenti del bug. Il percorso alternativo (campo "Parent Story" nel form del figlio) è già oggi quello prevalente — 15 relazioni su 66 sono nate senza passare dal bottone.

**Il `canSee` con `empty($this->parent_id)` non viene toccato.** Verificato sul DB: **0 story sono contemporaneamente figlie e padri**, la gerarchia è a un solo livello, quindi la condizione non nasconde alcun caso legittimo. Se in futuro si volesse una gerarchia a più livelli, questo `canSee` e la guardia in `Story::booted()` → `static::saving` sono i due punti da rivedere.

**Il rollback è lossy e la perdita cresce nel tempo.** Dal deploy in poi nessuno scrive più su `story_story`: ogni nuova relazione esiste solo in colonna. Un `git revert` riporterebbe `childStories()` sul pivot e renderebbe invisibili tutte le relazioni create nel frattempo — cioè il bug oc:8445 di nuovo, su un insieme più grande e senza alcun segnale. L'assenza di migration di dati rende il rollback *apparentemente* banale: è la trappola. Mitigazione: procedura di rollback documentata con la SQL di ripopolamento `INSERT INTO story_story (parent_id, child_id) SELECT parent_id, id FROM stories WHERE parent_id IS NOT NULL ON CONFLICT DO NOTHING`.

**Rimuovendo il cascade si perdono anche le notifiche, non solo l'allineamento di status.** Il cascade eseguiva `$child->save()`, che passava per l'intero hook `updated`: StoryLog, ricalcolo `hours`, mail e `NovaNotification` a developer/tester del figlio. Da ora chi lavora su un figlio non riceve **alcun** segnale che il padre è stato chiuso. Valutata e **scartata** in fase di challenge l'ipotesi di mantenere il cascade con un avviso preventivo sul campo Stato: fuori perimetro per un bugfix. Nessun rimpiazzo previsto in questo ciclo.

**La guardia "una storia figlia non può avere figli" allarga la propria sorgente.** Passando alla colonna, valuta 66 relazioni invece di 51. Verificato che oggi **0 story sono sia figlie sia padri** e **0 sono padri di sé stesse** (SQL diretto sulla colonna, non sul pivot), ma nulla lo vincola: `parent_id` è in `$fillable` e nella whitelist di `Api/StoryController::update()`, e il campo Nova "Parent Story" non ha filtro sui candidati. Se un caso si presentasse, la story diventerebbe **impossibile da salvare per sempre** — non solo in UI, ma anche per i job in coda (`SyncDeveloperCalendarJob`) e i comandi schedulati (`service:story-time`, `SlackRevertProgressCommand`), che fallirebbero in loop. Mitigato dal vincolo DB anti-auto-parentela e dalla `ValidationException`; il caso a due passaggi (A→B→A) resta possibile ma produce ora un errore di validazione recuperabile, non un blocco.

**`StoryPivot` resta in repo con i suoi hook attivi.** `saving()` scrive `stories.parent_id`, `deleting()` lo azzera. Nessun codice li innesca più, ma sono codice vivo se qualcuno tocca quella tabella via Eloquent. Non modificati in questo ciclo per tenere il diff minimo; la deprecazione è documentale.

**Questo ticket è propedeutico a oc:8421** (rollup ore padre-figlio, oggi solo in overview, nessun codice). Un rollup costruito sulla relazione rotta sommerebbe 51 collegamenti su 66: totali silenziosamente più bassi del vero sui 15 padri coinvolti — su una somma di ore, il difetto peggiore possibile perché plausibile e invisibile. L'indice su `parent_id` serve anche a quelle query.

**La FK `stories.parent_id` è `onDelete('set null')`**: cancellando un padre, i figli restano con `parent_id` NULL. È il comportamento equivalente al `cascade` del pivot, quindi nessuna regressione — ma è ora l'unico meccanismo, non più duplicato.

## Out of scope

- **Rimozione fisica della tabella `story_story`** e del modello `StoryPivot`: nessuna migration di drop in questo ciclo. Da fare in un ticket dedicato dopo un periodo di osservazione.
- **Backfill o pulizia delle 51 righe del pivot**: restano dove sono, non lette.
- **Nova Action "Collega ticket figlio"** per rimpiazzare il bottone "Attach": da aprire come ticket dedicato solo se l'uso reale lo richiede.
- **Allineamento manuale dei 4 ticket con status divergente**: non necessario, dato che il cascade viene rimosso.
- **Gerarchia padre-figlio a più livelli**: la guardia "una storia figlia non può avere figli" resta invariata.
- **Duplicazione ricorsiva dei figli** in `DuplicateStory`: sarebbe una feature nuova, non un bugfix.
- **Notifica ai figli quando il padre cambia stato** (in sostituzione del cascade): valutata in challenge e scartata, da aprire come ticket dedicato se l'assenza si fa sentire.
- **Blocco dei cicli a due passaggi** (A padre di B, B padre di A) e filtro dei candidati nel campo "Parent Story": resta possibile crearli, ma producono un errore di validazione recuperabile.
- **Svuotamento degli hook di `StoryPivot`**: la classe resta invariata, deprecata solo a livello documentale.

## Moduli toccati

Tutto nel repo principale `orchestrator` — nessun submodule coinvolto (feature **custom**).

| File | Modifica |
|---|---|
| `app/Models/Story.php` | `childStories()` → `hasMany(Story::class, 'parent_id')`; rimozione del blocco di sync pivot in `static::updated`; rimozione del cascade di status padre → figli |
| `app/Traits/fieldTrait.php` | nessuna modifica al codice, ma i due punti di rendering dei figli (`:193` e `:260`) cambiano contenuto: mostreranno 66 relazioni invece di 51 — da verificare manualmente |
| `database/migrations/<nuova>` | indice su `stories.parent_id` + vincolo anti-auto-parentela |
| `app/Models/StoryPivot.php` | non più referenziato (nessuna modifica al file, resta in repo come deprecato) |
| `app/Nova/Story.php` | `BelongsToMany::make(__('Child Stories'), ...)` → `HasMany::make(...)`, `canSee` invariato |
| `app/Nova/Actions/DuplicateStory.php` | rimozione delle 2 righe che copiano i figli sul duplicato |
| `lang/it.json`, `lang/en.json` | riscrittura dell'help text del campo "Parent Story" (chiave sostituita in entrambi i file) |
| `tests/Feature/StoryRelationshipTest.php` | riscritto sulla colonna `parent_id`; rimosso il test sul cascade di status |
| `CLAUDE.md` | sezione "Feature disponibili" e "Decisioni architetturali"; nota di deprecazione di `story_story` |
