> Ticket: oc:8421

# Relazione padre-figlio: suddivisione ticket cliente e rollup di tempo effettivo e stima

Vedi mappa completa del sistema attuale in `docs/calcolo-ore-effettive-stimate.md` (oc:8446).

**Storia delle revisioni di questo documento.** La v1 pianificava di far passare `Tag::getTotalHoursAttribute()` da `hours` a un rollup costruito su `Story::effectiveMinutes()`. La v2 ha ribaltato quella premessa: oc:8446 ha stabilito, con verifica su tutto il repo e sulla cronologia git, che la fonte reale del tempo effettivo e la colonna `hours` (l'unica letta da Nova, Tag SAL, `TagHoursTotal`, export, API), mentre `effectiveMinutes()`/`effectiveMinutesForStory()` non ha mai avuto un chiamante. Alessandro Peci ha confermato il dato guardando il DB in call (2026-09-01); Giuseppe Bonfanti ha deciso l'approccio: **nessun nuovo metodo pubblico di rollup**, si estendono i punti esistenti che espongono `hours`. Questa v3 recepisce la sessione del 2026-09-02, che ha definito con precisione **dove** il rollup si applica e **dove no** — in particolare separando il trattamento del tempo effettivo da quello della stima.

## Cosa cambia
Un ticket padre (riferimento per il cliente) puo essere suddiviso in piu ticket figli tecnici — gia possibile oggi, N figli per padre, gerarchia a 2 livelli. Il **tempo effettivo** visto sul padre e sul tag che lo contiene includera anche il lavoro svolto sui figli. La **stima resta invariata dappertutto**. La cascata di stato dal padre ai figli viene rimossa: ogni figlio avanza in modo indipendente.

## Perche
Ticket corposi con attivita su sviluppatori diversi (es. backend/frontend) vengono suddivisi in figli, ma il tempo effettivo resta visibile solo sul singolo ticket: il lavoro sui figli risulta "non tracciato" agli occhi di chi guarda il padre o il tag che lo raggruppa.

## Il modello concettuale: stima top-down, tempo effettivo bottom-up

E la decisione che governa tutto il resto del documento, stabilita da Giuseppe nella sessione del 2026-09-02.

- **La stima e top-down.** Si stima il **padre**, che tipicamente coincide con la feature: es. 40h, scelte sapendo che quel lavoro verra svolto da N ticket figli i quali, al piu, incuberanno quelle 40h. Le stime che ogni dev inserisce sui propri figli sono una **suddivisione interna** di quel budget, non un'aggiunta: non devono modificarlo. Sommarle al padre conterebbe due volte lo stesso budget (40h sul padre + 40h distribuite sui figli = 80h).
- **Il tempo effettivo e bottom-up.** E un dato **misurato** sui `StoryLog`, e il lavoro e stato realmente svolto sui figli: va sommato, altrimenti suddividere un ticket fa sparire il lavoro dai totali.

Conseguenza diretta: **il rollup si applica al solo tempo effettivo. Alla stima non si applica in nessun punto** — ne sul ticket ne sul tag. Non e una semplificazione di scope, e la semantica corretta delle due grandezze.

Conseguenza sul SAL dei tag manuali, verificata in sessione: il denominatore resta il budget di 40h del padre e il numeratore diventa la somma delle ore effettive di padre + figli — esattamente il confronto voluto. Il SAL% dei tag manuali diventa **piu** corretto, non gonfiato: oggi confronta 40h con le sole ore del padre, quindi **sottostima** l'avanzamento reale. L'allarme "SAL falsato al rialzo" presente nel testo del ticket e nelle v1/v2 di questo overview era fondato solo sull'ipotesi (scartata) di aggregare le ore senza aggregare le stime *dove le stime dei figli contano*: qui non contano per costruzione.

## Dove si applica il rollup (tabella normativa)

| Punto | Codice | Ore effettive | Stima |
|---|---|---|---|
| Colonna index Stories | `fieldTrait::effectiveHoursField()` (ramo index) | **padre + figli** | invariata (`estimatedHoursField()` non si tocca) |
| Detail ticket — campo Text | `fieldTrait::effectiveHoursField()` (ramo detail) | **padre + figli** | invariata |
| Detail ticket — card "Story Time" | `Nova\Metrics\StoryTime::calculate()`, ramo `instanceof Story` con `id` | **padre + figli** | n/a |
| Card in cima all'index Stories | `Nova\Metrics\StoryTime::calculate()`, ramo `instanceof Story` senza `id` | **padre + figli** | n/a |
| Detail Tag — card ore | `Nova\Metrics\StoryTime::calculate()`, ramo `instanceof Tag` | **unione deduplicata** | n/a |
| Tag: ore, SAL, `SAL t`, `TagHoursTotal`, `TagGroup` | `Tag::getTotalHoursAttribute()` e tutto cio che legge a valle | **unione deduplicata** | invariata |
| Tag: stima | `Tag::getEstimateAttribute()` | n/a | **invariata, zero righe di codice** |
| Export Excel | `SelectedStoriesToExcel:62` | invariato | invariato |
| API pubblica `/api/stories` | `Api\StoryController:130` | invariato | invariato |
| Report (per status e totale) | `ReportController:285,494` | invariato | invariato |

Note sulla tabella:
- **Sul detail di una story il tempo effettivo compare due volte** — nel campo Text di `effectiveHoursField()` e nella card `StoryTime` — e sono due punti di codice distinti. Vanno allineati entrambi, altrimenti la stessa schermata mostra due numeri diversi.
- **`StoryTime` serve tre contesti dallo stesso metodo** (detail Tag, detail Story, index Stories): tutti e tre ricadono dentro il perimetro del rollup, nessun ramo resta indietro.
- **`Estimated Hours` su index e detail e la stessa riga di codice** (`estimatedHoursField()`, ramo `isResourceDetailRequest() || isResourceIndexRequest()`): "non aggregata nell'index" implica automaticamente "non aggregata sul detail". Nessuna separazione dei due rami e richiesta.
- **`->sortable()` non e presente sul ramo index/detail** di `effectiveHoursField()` (e un `Text` con closure; il `sortable` sta solo sul ramo `Number` del form create/update). Non esiste quindi il rischio "ordino per Effective Hours e vedo righe incoerenti perche `ORDER BY hours` non conosce il rollup": quella colonna non e ordinabile.
- **Un accessor Eloquent su `hours` e stato valutato ed escluso** (proposta di Giuseppe, sessione 2026-09-02). Avrebbe coperto gratis i soli lettori PHP dell'attributo (`fieldTrait`, export Excel, API), ma: (a) **non intercetta i lettori SQL** — `Tag::getTotalHoursAttribute()`, `ReportController:285,494`, `StoryTime::calculate()` usano tutti `sum('hours')` o l'equivalente Nova, dove un accessor non viene mai invocato, e il primo di questi e il requisito centrale del ticket; (b) `hours` e **editabile a mano da Nova** (`effectiveHoursField()` ritorna un `Number` su create/update e Nova bypassa `$fillable`), quindi il form si pre-popolerebbe col rollup e al primo save persisterebbe il totale nella colonna propria del padre, accumulando doppi conteggi in silenzio a ogni salvataggio; (c) romperebbe la guardia `StoryTimeService:46-47`, che scrive `$story->hours = <valore proprio>` e subito dopo verifica `if ($story->hours)`.

## Decisioni
- Nessuna modifica di schema DB, nessuna migration. Nessuna eccezione (vedi indice mancante su `parent_id` nei Rischi).
- **Nessun nuovo metodo pubblico dedicato al rollup**: si estendono i punti esistenti che espongono `hours`. Ammesso un **helper interno condiviso** su `Story`, richiamato dai punti esistenti, per non triplicare la stessa logica in tre file (e un dettaglio implementativo, non un metodo pubblico a cui i chiamanti devono migrare).
- **Nessuna scrittura in DB per la somma padre+figli**: calcolata solo in lettura, mai persistita. La colonna `hours` di ogni story continua a essere scritta esattamente come oggi (`StoryTimeService`/`Story::save()`).
- **Regola di calcolo**: story senza figli → `hours` cosi com'e, incluso il caso `null`. Story con figli → `hours` del padre + somma di `hours` dei figli diretti.
- **I valori negativi si sommano cosi come sono, nessun clamp a 0** (decisione esplicita di Giuseppe). 280 story su 7537 hanno `hours` negativa (fino a `-0.23`) per un difetto di calcolo sui bordi della finestra oraria di `StoryTimeService` — visibile anche nell'index Stories in produzione. Un `max(0, ...)` nasconderebbe il bug e renderebbe il rollup non riconciliabile con la somma delle righe dei figli. Il posto giusto per correggere il segno e `StoryTimeService`, in un ticket dedicato (follow-up di oc:8446, **non ancora aperto**).
- **Deduplica obbligatoria sul tag, come caso normale e non edge case.** L'auto-tagging (`App\Nova\Story::afterCreate/afterUpdate` → `TagService`) attacca tag automatici a **qualsiasi** story salvata, figli inclusi e senza alcun filtro su `parent_id`: tag trimestrale (`attachQuarterTagToStory`), tag cliente (`attachCustomerTagToStory`), tag nome-repo dal testo (`attachTagsFromTextToStory`). Su quei tag i figli sono **gia** taggati e **gia** contati oggi: sommare "story taggate + loro figli" senza deduplicare li conterebbe due volte. La query del tag va quindi costruita come **unione distinta di (story taggate ∪ figli di story taggate)**, sommando ogni story una volta sola. L'auto-tagging non va modificato (serve sapere a quale trimestre/cliente appartiene anche il singolo figlio).
- **Il buco reale e solo sui tag manuali** (RDO, progetto — quelli su cui si guarda il SAL): li i figli non sono taggati, quindi oggi le loro ore sono perse. Sui tag automatici il rollup non aggiunge nulla, serve solo a non duplicare.
- **`Tag::getEstimateAttribute()` resta invariato, zero righe di codice.** Sui tag automatici il valore della stima non e significativo e per scelta non lo si guarda (il SAL serve prettamente per i tag manuali); sui tag manuali il denominatore e il budget del padre, che e quello corretto.
- **Fonte per i figli: query diretta su `parent_id`** (`Story::where('parent_id', $id)`), non la relazione `childStories()` che legge la pivot `story_story`. La pivot si sincronizza solo nell'hook `updated`: se un figlio nasce con `parent_id` gia valorizzato dal form Nova (campo `Parent Story` presente in create, `app/Nova/Story.php:245`) l'evento e `created` e la sincronizzazione non scatta mai. `parent_id` e l'unica fonte affidabile in ogni scenario di creazione.
- **Cascata di stato padre → figli rimossa** (`app/Models/Story.php:256-261`). Nessuna propagazione introdotta nella direzione opposta (figli → padre).
- **Il campo Nova "Child Stories" rotto e fuori scope: esiste gia un ticket dedicato.** Il campo (`app/Nova/Story.php:195`) e un `BelongsToMany` su `childStories`, cioe sulla pivot non sincronizzata: per questo non mostra i figli creati con `parent_id` gia valorizzato. Causa confermata in questa sessione, correzione tracciata altrove. Tracciato in **oc:8445** ("Ticket correlati non mostra i ticket figli nel detail Story", Bug, status `todo`).

## Requisiti
- [ ] Helper interno condiviso su `Story` che restituisce il tempo effettivo con i figli (`hours` proprio + somma di `hours` dei figli diretti via `parent_id`), null-safe e senza clamp sui negativi. Nessun nuovo metodo pubblico alternativo.
- [ ] **Null-safety**: padre con `hours = null` e figli valorizzati → il totale e la somma dei figli (coalescenza a 0 sulla parte mancante), non `null`. Story senza figli e con `hours = null` → comportamento invariato rispetto a oggi (l'index stampa `Effective Hours: 0` per il `?? 0` gia presente).
- [ ] Rollup applicato a `fieldTrait::effectiveHoursField()` (ramo index/detail).
- [ ] Rollup applicato a `Nova\Metrics\StoryTime::calculate()` in **tutti e tre** i rami (detail Story, index Stories, detail Tag).
- [ ] `Tag::getTotalHoursAttribute()` calcolato sull'unione deduplicata (story taggate ∪ loro figli), ogni story contata una volta sola.
- [ ] `Tag::getEstimateAttribute()`, `estimatedHoursField()`, export Excel, `Api\StoryController`, `ReportController`: **verificati come non modificati** (requisito di non-regressione, con test dove sensato).
- [ ] SAL% (`getSalAttribute`, `calculateSalPercentage`), colonna `SAL t` (`app/Nova/Tag.php:61-85`, `onlyOnIndex()`) e metrica `TagHoursTotal` restano coerenti senza modifiche proprie: assorbono il cambio a monte in `getTotalHoursAttribute()`.
- [ ] **N+1 / costo ripetuto su `SAL t`**: `app/Nova/Tag.php:61-85` chiama `getTotalHoursAttribute()` **3-4 volte per riga**. Serve memoization a livello di risorsa, non solo query batch. Nessuna cache/Redis (fuori scope).
- [ ] **N+1 sull'index globale delle Stories**: ora che il rollup compare anche li, ogni riga deve conoscere i figli. Serve una query batch unica per pagina (una sola query su tutti i `parent_id` rilevanti, raggruppata in memoria) eseguita da un punto a monte che la renda disponibile alle righe — `effectiveHoursField()` gira riga per riga e da solo non puo farla. Candidato naturale: `Story::indexQuery()` (`app/Nova/Story.php:137`, oggi usato solo per lo scoping del ruolo Customer). **Meccanismo e posizione esatta da chiudere in fase di piano.**
- [ ] **`TagGroup` va coperto esplicitamente**: `App\Models\TagGroup extends Tag`, ha una relazione diversa (`stories()`, pivot `tag_group_stories`) ma **eredita** `getTotalHoursAttribute()`/`getSalAttribute()`/`calculateSalPercentage()` senza override, e `app/Nova/TagGroup.php` ha una propria colonna "SAL t" con lo stesso pattern N+1. La logica si propaga automaticamente; la **mitigazione N+1 no** — va estesa anche a `app/Nova/TagGroup.php`.
- [ ] **Allineare il guard anti-nipoti alla fonte del rollup**: `Story.php:343` controlla `childStories()->exists()`, cioe la pivot. Va portato su `parent_id`, cosi il blocco "una story figlia non puo avere figli" e garantito nello stesso scenario che il rollup assume vero.
- [ ] Rimossa la cascata status padre → figli (`Story.php:256-261`).
- [ ] **Correggere il docblock fuorviante di `Story::effectiveMinutes()`** (`Story.php:600-603`: "Fonte autorevole per le ore effettive — il campo `hours` e deprecato", ormai falso), per evitare che un futuro lettore riapra l'equivoco chiarito in oc:8446.
- [ ] **Comando artisan read-only di confronto** (es. `tags:compare-sal-rollup`): per ogni tag stampa il valore attuale (`sum('hours')` sulle sole taggate) vs il nuovo (unione deduplicata con i figli), ed evidenzia i tag che contengono story con `hours` negativa. Nessuna scrittura. Da eseguire su dati reali prima del merge per misurare lo scostamento sui SAL gia comunicati ai clienti.
- [ ] **Test automatici**: padre con N figli; padre senza figli; story figlia; **figlio taggato con lo stesso tag del padre trattato come caso normale** (verifica di non-doppio-conteggio); padre con `hours = null` e figli valorizzati, e viceversa; figlio con `hours` negativa (verifica che il totale scenda, nessun clamp); non-regressione su stima del tag, export, API, report.
- [ ] **Test esistente da invertire**: `tests/Feature/StoryRelationshipTest.php::it_propagates_status_changes_from_parent_to_child` verifica la cascata che va rimossa — va rinominato e invertito (assert che il figlio NON cambi status), non eliminato, per documentare il cambio di comportamento nella cronologia dei test.

## Rischi
- **N+1 su tre viste distinte** (index Tags, index TagGroups, index Stories), su una tabella `stories` **senza indice su `parent_id`** — in PostgreSQL una FK non crea automaticamente l'indice sulla colonna referenziante, e la migration che ha aggiunto `parent_id` non lo crea. Per decisione esplicita (nessuna migration in questo ciclo) l'indice non viene aggiunto: le query per pagina restano scansioni sequenziali nel caso peggiore. Rischio accettato; se in produzione si rivelasse reale, la soluzione e un ticket dedicato con la sua migration, non un'eccezione infilata qui. Nota che il comando di confronto pianificato misura i **valori**, non i **tempi** di query: non intercetterebbe questo problema.
- **Valori negativi propagati per scelta**: 280 story con `hours < 0` entrano nei totali aggregati e nel SAL cliente. Consapevole e mitigato solo dalla segnalazione nel comando di confronto; la correzione vive in un altro ticket, **ancora da aprire**.
- **I numeri di SAL dei tag manuali cambiano** (salgono, per inclusione delle ore dei figli) su valori potenzialmente **gia comunicati a clienti**. Da misurare col comando di confronto prima del merge e da segnalare, non da scoprire dopo.
- **Doppia fonte di verita padre-figlio** (`parent_id` vs pivot `story_story`, sincronizzata da tre hook su due modelli — `Story::updated`, `StoryPivot::saving`, `StoryPivot::deleting` — uno dei quali ingoia le eccezioni senza loggarle, `Story.php:288-291`). Mitigato per il rollup usando sempre e solo `parent_id`; il rischio resta su `childStories()` come relazione, usata dal campo Nova "Child Stories" — tracciato nel ticket dedicato, fuori scope qui.
- **Gerarchia a 2 livelli non garantita end-to-end**: il guard controlla la pivot, che puo desincronizzarsi. Una gerarchia a 3 livelli formatasi per questo gap sarebbe invisibile al guard e **sottostimerebbe silenziosamente** il rollup del nonno. Mitigato allineando il guard a `parent_id` (vedi Requisiti).
- **Asimmetria stima/ore visibile in UI**: sulla stessa riga dell'index si leggeranno `Estimed Hours` del solo padre e `Effective Hours` di padre + figli. E **intenzionale** (stima top-down, ore bottom-up) ma non e autoevidente per chi guarda: un lettore puo interpretarlo come uno sforamento. Nessuna etichetta o tooltip previsto in questo ciclo — nessuna nuova chiave di traduzione. Rischio di comunicazione, non tecnico.
- **Dato di produzione non misurato**: quante story figlie condividono oggi un tag col padre e di quanto scostano i SAL. Richiede l'esecuzione del comando di confronto su un ambiente con dati reali.
- **Figli "orfani" al rilascio**: rimossa la cascata, i figli in corso restano nel loro stato mentre il padre puo risultare chiuso; nessun reminder li segnala. Nessuna azione pianificata (stesso pattern accettato per `pending_release`).
- **Rollback non simmetrico**: il codice si revert facilmente (nulla di persistito, nessuna migration), ma il comportamento no: una volta rimossa la cascata i dev si abituano a chiudere i figli a mano, e un revert non riallinea gli stati rimasti indietro — servirebbe un intervento manuale sui dati.

## Out of scope
- Modifiche di schema DB e migration di qualsiasi tipo, incluso l'indice su `stories.parent_id`.
- Qualsiasi modifica a `Story::effectiveMinutes()`/`effectiveMinutesForStory()` — non toccate, non usate come base di alcun calcolo (solo il docblock viene corretto).
- Qualsiasi rollup della **stima**, in qualunque punto (ticket e tag).
- Modifiche all'auto-tagging (`TagService`): i figli continuano a ricevere i tag automatici.
- Fix del campo Nova "Child Stories" e bonifica della pivot `story_story`/dei suoi hook (incluso il `catch (\Exception $e) { $e; }` che ingoia errori) — **oc:8445**.
- Correzione delle `hours` negative in `StoryTimeService` — ticket dedicato, da aprire.
- Rimozione della colonna `hours` e delle sue scritture.
- Gerarchia a piu di 2 livelli; propagazione di status figli → padre; riassegnazione automatica di assegnatari.
- Cache/Redis per il rollup (mitigazione N+1 solo via memoization/query batch).
- Modifiche a export Excel, API pubblica `/api/stories`, `ReportController`.

## Moduli toccati
- `app/Models/Story.php` — quattro modifiche distinte: (1) helper interno per il tempo effettivo con figli; (2) rimozione cascata status (righe 256-261); (3) guard anti-nipoti (riga 343) allineato a `parent_id`; (4) correzione docblock di `effectiveMinutes()` (righe 600-603)
- `app/Models/Tag.php` — solo `getTotalHoursAttribute()` (unione deduplicata). `getEstimateAttribute()` **non si tocca**
- `app/Traits/fieldTrait.php` — `effectiveHoursField()` (ramo index/detail). `estimatedHoursField()` **non si tocca**
- `app/Nova/Metrics/StoryTime.php` — `calculate()`, tutti e tre i rami
- `app/Nova/Story.php` — probabile `indexQuery()` (riga 137) per la query batch anti-N+1, meccanismo da confermare in fase di piano
- `app/Nova/Tag.php` — memoization per il rischio N+1 su `SAL t` (nessuna modifica alla logica della colonna)
- `app/Nova/TagGroup.php` — stessa memoization
- `app/Nova/Metrics/TagHoursTotal.php` — nessuna modifica prevista (legge `getTotalHoursAttribute()`)
- `app/Console/Commands/` — nuovo comando `tags:compare-sal-rollup` (read-only)
- `tests/Feature/` — nuovi test sul rollup e sul non-doppio-conteggio; inversione di `StoryRelationshipTest::it_propagates_status_changes_from_parent_to_child`
- `lang/it.json`, `lang/en.json` — nessuna nuova chiave (riuso di "Effective Hours")
