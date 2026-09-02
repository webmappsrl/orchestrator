> Ticket: oc:8445

# Notes — Ticket correlati non mostra i ticket figli

## Deviazioni dal piano

- **Il test sul cascade è nato falso-verde e ha richiesto una correzione.** La prima stesura di `lo_status_del_padre_non_si_propaga_piu_ai_figli` passava *prima* di rimuovere il cascade, quindi non dimostrava nulla. Causa: l'hook `created` di `Story` esegue un `save()` interno che desincronizza l'istanza in memoria, rendendo `isDirty('status')` falso nell'hook `updated`. Aggiunto `$parent = $parent->fresh()` prima dell'update per riprodurre la condizione reale (Nova lavora sempre su istanze ricaricate dal DB). Solo allora il test è diventato rosso.
- **`tests/Feature/StoryChildFieldTest.php` ha richiesto `Nova::resourcesIn(app_path('Nova'))` in `setUp()`.** Senza, `detailFields()` solleva `ResourceMissingException` sui campi `BelongsTo` prima di arrivare al campo in esame: il test sarebbe fallito per il motivo sbagliato.
- **`app/Nova/Actions/DuplicateStory.php` ha richiesto una riga in più rispetto al piano.** Non bastava rimuovere `parentStory()->associate(...)`: `Story::create($story->toArray())` copia `parent_id` dall'originale, quindi il duplicato ereditava comunque il padre. Aggiunto `$newStory->parent_id = null;` prima di `saveQuietly()`.

- **`tests/Feature/DuplicateStoryTest.php` è stato sovrascritto per errore durante l'esecuzione e poi ripristinato.** Il file **esisteva già** (115 righe, namespace `Tests\Feature\Nova`, `DatabaseTransactions`, con helper `createFullStory()`/`assertStoryCloned()` e copertura su clonazione di tag/participants/tester e sull'URL di redirect dell'azione). Il piano lo dichiarava erroneamente come "Create" e l'esecuzione lo ha riscritto da zero, distruggendo quella copertura (44 assertions ridotte a 3). Rilevato dal subagente isolato del review gate, **non** dalla suite di test — che restava verde, perché i test cancellati semplicemente non esistevano più. File ripristinato da `HEAD` e poi **esteso** invece che sostituito: aggiunto un test dedicato (`il_duplicato_non_compare_tra_i_figli_del_padre_dell_originale`) e aggiornato `assertStoryCloned()` per pretendere `parent_id` nullo e zero figli sul duplicato. Lezione: prima di scrivere un file "nuovo" previsto da un piano, verificarne l'esistenza — una suite verde non rileva la sparizione di test.

## Bug trovati

- **`static::updated` conteneva un `Undefined array key "parent_id"`** (ex `Story.php:267`): il ramo `wasChanged('parent_id')` leggeva `$story->getOriginal()['parent_id']` su istanze in cui quella chiave non esiste. Il warning è sparito con la rimozione del blocco, non è stato corretto separatamente.
- **Il cascade di status dipendeva dalla freschezza dell'istanza** (vedi sopra): su un modello appena creato non scattava affatto. Comportamento incoerente mai documentato, ora irrilevante perché il cascade è stato rimosso.
- **Chiave di traduzione orfana in `lang/vendor/nova/{it,en}.json`**: contengono ancora il vecchio help text con la promessa del cascade. Verificato che `__()` risolve da `lang/{locale}.json` e non da quei file, quindi sono inerti. Non toccati (override di pacchetto vendor, fuori scope) — vedi Follow-up.

## Decisioni

- La diagnosi iniziale del ticket ("sospetta condizione di visibilità errata sul `canSee`") si è rivelata **sbagliata**: il `canSee` funziona correttamente — su oc:8180 `parent_id` è `NULL`, quindi il campo era visibile e semplicemente vuoto. La causa era la doppia fonte di verità colonna/pivot.
- Il cascade di status padre→figli è stato **smantellato**. In fase di challenge la decisione è stata riaperta (valutato di mantenerlo aggiungendo un avviso preventivo sul campo Stato quando il ticket ha figli) e poi **richiusa**: si resta sullo smantellamento completo, senza avviso.
- **`searchable()` e `filterable()` vanno rimossi perché non esistono su `HasMany`** (la loro presenza produce un `BadMethodCallException`, cioè un 500 sul detail di **ogni** ticket; coperto da test di regressione). **Verificato nella UI dopo il rilascio: la perdita è molto minore di quanto stimato in challenge.** Dentro la sezione "Ticket correlati" restano funzionanti sia il campo di ricerca sia i filtri (Creatore, Assegnato a, Stato, Tag, Tipo): appartengono nativamente all'index della relazione e alla Resource, non ai due metodi rimossi. `searchable()` serviva a cercare il ticket **da agganciare** nella modale di Attach (sparita col bottone), e `filterable()` generava un filtro per relazione nella lista Ticket principale. **L'unica perdita osservabile nell'uso quotidiano è il bottone "Attach"** (agganciare un ticket già esistente dal padre); il bottone "Crea Ticket", che crea un figlio nuovo, resta.
- Perdita accettata: il cascade eseguiva `$child->save()`, quindi generava anche StoryLog e notifiche. Chi lavora su un figlio non riceve più alcun segnale quando il padre cambia stato. Nessun rimpiazzo previsto in questo ciclo.
- **Durante l'esecuzione, l'inserimento della nuova chiave di traduzione ha inizialmente riordinato alfabeticamente l'intero `it.json`/`en.json`**, producendo un diff di 1.019 righe. Ripristinato e rifatto con inserimento testuale mirato (1 riga per file): il riordino avrebbe reso illeggibile la review e messo a rischio le coppie di chiavi duplicate documentate in `CLAUDE.md` (`Closed_Won`, `To_Present`, ecc.).

## Procedura di rollback

Il rollback è **lossy** e la perdita cresce nel tempo: dal deploy in poi nessuno scrive più su `story_story`, quindi ogni nuova relazione padre-figlio esiste solo in colonna. Un `git revert` da solo renderebbe invisibili tutte le relazioni create nel frattempo — cioè ripresenterebbe il bug oc:8445 su un insieme più grande e in silenzio.

Un rollback corretto richiede, **prima** di ripristinare il codice:

```sql
INSERT INTO story_story (parent_id, child_id, created_at, updated_at)
SELECT parent_id, id, NOW(), NOW()
FROM stories
WHERE parent_id IS NOT NULL
ON CONFLICT (parent_id, child_id) DO NOTHING;
```

Poi:
- `php artisan migrate:rollback --step=1` per rimuovere indice e vincolo;
- ripristinare la chiave di traduzione dell'help text in `it.json` e `en.json`, altrimenti la UI dichiara stati indipendenti mentre il codice tornerebbe a cascadarli.

## Follow-up

- **Drop fisico di `story_story` e rimozione di `app/Models/StoryPivot.php`** — i suoi hook `saving()`/`deleting()` restano codice vivo che scrive e azzera `stories.parent_id`, anche se nessuno li innesca più. Ticket dedicato dopo un periodo di osservazione.
- **Pulizia della chiave orfana in `lang/vendor/nova/{it,en}.json`** con il vecchio help text.
- **Nova Action "Collega ticket figlio"** per rimpiazzare il bottone "Attach" perso, se l'assenza si fa sentire.
- **Blocco dei cicli a due passaggi** (A padre di B, B padre di A) e filtro dei candidati nel campo "Parent Story": oggi restano creabili, ma producono una `ValidationException` recuperabile.
- **Notifica ai figli quando il padre cambia stato**, in sostituzione del cascade rimosso.
- **oc:8421 (rollup ore padre-figlio) dipende da questo ticket**: costruito sulla relazione precedente avrebbe sommato 51 collegamenti su 66, con totali silenziosamente più bassi del vero sui 15 padri coinvolti.
