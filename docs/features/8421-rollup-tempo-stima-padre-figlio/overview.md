> Ticket: oc:8421

# Relazione padre-figlio: suddivisione ticket cliente e rollup di tempo effettivo e stima

Vedi mappa completa del sistema attuale in `docs/calcolo-ore-effettive-stimate.md` (oc:8446).

**Storia delle revisioni di questo documento.** La v1 pianificava di far passare `Tag::getTotalHoursAttribute()` da `hours` a un rollup costruito su `Story::effectiveMinutes()`. La v2 ha ribaltato quella premessa: oc:8446 ha stabilito, con verifica su tutto il repo e sulla cronologia git, che la fonte reale del tempo effettivo e la colonna `hours` (l'unica letta da Nova, Tag SAL, `TagHoursTotal`, export, API), mentre `effectiveMinutes()`/`effectiveMinutesForStory()` non ha mai avuto un chiamante. Alessandro Peci ha confermato il dato guardando il DB in call (2026-09-01); Giuseppe Bonfanti ha deciso l'approccio: **nessun nuovo metodo pubblico di rollup**, si estendono i punti esistenti che espongono `hours`. La v3 recepiva la sessione del 2026-09-02, che aveva definito con precisione **dove** il rollup si applica e **dove no** — in particolare separando il trattamento del tempo effettivo da quello della stima.

**v6 — Task "anti-N+1 su indexQuery" rimosso dopo review formale.** Una prima versione aveva introdotto una `addSelect()` permanente su `Story::indexQuery()` per precaricare `children_hours_sum` ed evitare una query per riga nell'index Stories. La review formale (5 finder paralleli + verifica diretta sul codice Laravel) ha trovato che questo tentativo era **controproducente su tre fronti indipendenti**, confermati sperimentalmente:
1. **Rompeva filtri Nova esistenti non toccati da questo ticket** (`app/Nova/Filters/TaggableTypeFilter.php`, `CreatorStoryFilter.php`): `Query\Builder::onceWithColumns()` (usato internamente da `pluck()`) sostituisce le colonne richieste solo se non erano gia' impostate — e l'`addSelect` le aveva gia' impostate per ogni chiamante successivo della stessa query, incluso questi filtri, con risultati silenziosamente corrotti (dropdown "Taggable Type" avrebbe mostrato id/nome di story al posto di tag).
2. **Non veniva mai ereditato dalle risorse Nova realmente usate in produzione**: `DeveloperStory`, `CustomerStory`, `AssignedToMeStory`, `BacklogStory` e le altre sottoclassi di `Story` ridefiniscono ciascuna il proprio `indexQuery()` senza chiamare `parent::indexQuery()` (verificato: nessun `parent::indexQuery` nel codebase). L'ottimizzazione si applicava solo alla risorsa Nova `Story` base, che non ha una voce di menu propria.
3. **Anche dove attivo, il fallback per "figli a somma zero" (il caso piu comune: la maggioranza delle story non ha figli) eseguiva comunque una query `exists()` per riga**, vanificando l'obiettivo.

**Decisione presa in review**: rimuovere del tutto la `addSelect` e il ramo "precomputed" di `hoursWithChildren()`. `Story::indexQuery()` torna al comportamento originale pre-oc:8421 (`app/Nova/Story.php`, `Moduli toccati` aggiornato di conseguenza). L'N+1 sull'index Stories resta un **rischio accettato** (vedi Rischi), non un problema silenziosamente "risolto" da codice che in pratica non funzionava.

**v7 (questa versione) — documentazione allineata allo schema attuale.** Verificato su `develop` (DB locale `orchestrator` e `orchestrator_test`): la colonna `stories.parent_id` e gia indicizzata (`stories_parent_id_index`, btree) e ha il constraint anti-auto-parentela. Questo ticket non introduce migration. I figli si risolvono sulla colonna `parent_id` (`Story::where('parent_id', $id)` / `idsWithChildren()`).

## Cosa cambia
Un ticket padre (riferimento per il cliente) puo essere suddiviso in piu ticket figli tecnici — gia possibile oggi, N figli per padre, gerarchia a 2 livelli. Il **tempo effettivo** visto sul padre e sul tag che lo contiene includera anche il lavoro svolto sui figli. La **stima resta invariata dappertutto**.

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
- Nessuna modifica di schema DB, nessuna migration in questo ticket. Lo schema necessario c'e gia: colonna `stories.parent_id` (nullable, FK verso `stories.id`), indice `stories_parent_id_index`, constraint `stories_parent_id_not_self`. Verificato su `develop` e sui DB locali `orchestrator` / `orchestrator_test` (migration gia Ran). Questo ticket non aggiunge ne modifica tabelle o colonne.
- **Nessun nuovo metodo pubblico dedicato al rollup**: si estendono i punti esistenti che espongono `hours`. Ammesso un **helper interno condiviso** su `Story`, richiamato dai punti esistenti, per non triplicare la stessa logica in tre file (e un dettaglio implementativo, non un metodo pubblico a cui i chiamanti devono migrare).
- **Nessuna scrittura in DB per la somma padre+figli**: calcolata solo in lettura, mai persistita. La colonna `hours` di ogni story continua a essere scritta esattamente come oggi (`StoryTimeService`/`Story::save()`).
- **Regola di calcolo**: story senza figli → `hours` cosi com'e, incluso il caso `null`. Story con figli → `hours` del padre + somma di `hours` dei figli diretti.
- **I valori negativi si sommano cosi come sono, nessun clamp a 0** (decisione esplicita di Giuseppe). 280 story su 7537 hanno `hours` negativa (fino a `-0.23`) per un difetto di calcolo sui bordi della finestra oraria di `StoryTimeService` — visibile anche nell'index Stories in produzione. Un `max(0, ...)` nasconderebbe il bug e renderebbe il rollup non riconciliabile con la somma delle righe dei figli. Il posto giusto per correggere il segno e `StoryTimeService`, in un ticket dedicato (follow-up di oc:8446, **non ancora aperto**).
- **Deduplica obbligatoria sul tag, come caso normale e non edge case.** L'auto-tagging (`App\Nova\Story::afterCreate/afterUpdate` → `TagService`) attacca tag automatici a **qualsiasi** story salvata, figli inclusi e senza alcun filtro su `parent_id`: tag trimestrale (`attachQuarterTagToStory`), tag cliente (`attachCustomerTagToStory`), tag nome-repo dal testo (`attachTagsFromTextToStory`). Su quei tag i figli sono **gia** taggati e **gia** contati oggi: sommare "story taggate + loro figli" senza deduplicare li conterebbe due volte. La query del tag va quindi costruita come **unione distinta di (story taggate ∪ figli di story taggate)**, sommando ogni story una volta sola. L'auto-tagging non va modificato (serve sapere a quale trimestre/cliente appartiene anche il singolo figlio).
- **Il buco reale e solo sui tag manuali** (RDO, progetto — quelli su cui si guarda il SAL): li i figli non sono taggati, quindi oggi le loro ore sono perse. Sui tag automatici il rollup non aggiunge nulla, serve solo a non duplicare.
- **`Tag::getEstimateAttribute()` resta invariato, zero righe di codice.** Sui tag automatici il valore della stima non e significativo e per scelta non lo si guarda (il SAL serve prettamente per i tag manuali); sui tag manuali il denominatore e il budget del padre, che e quello corretto.
- **Fonte per i figli: colonna `parent_id`**, via query diretta (`Story::where('parent_id', $id)` / `idsWithChildren()`). E la stessa colonna su cui poggia `childStories()` (`HasMany`). L'helper condiviso evita di triplicare la query nei tre punti di consumo; non e un secondo modello di relazione.
- **Cascata di stato padre → figli e campo Nova "Ticket correlati"**: non toccati. Nessuna propagazione introdotta nella direzione opposta (figli → padre).

## Requisiti
- [ ] Helper interno condiviso su `Story` che restituisce il tempo effettivo con i figli (`hours` proprio + somma di `hours` dei figli diretti via query su `parent_id`), null-safe e senza clamp sui negativi. Nessun nuovo metodo pubblico alternativo.
- [ ] **Null-safety**: padre con `hours = null` e figli valorizzati → il totale e la somma dei figli (coalescenza a 0 sulla parte mancante), non `null`. Story senza figli e con `hours = null` → comportamento invariato rispetto a oggi (l'index stampa `Effective Hours: 0` per il `?? 0` gia presente).
- [ ] Rollup applicato a `fieldTrait::effectiveHoursField()` (ramo index/detail).
- [ ] Rollup applicato a `Nova\Metrics\StoryTime::calculate()` in **tutti e tre** i rami (detail Story, index Stories, detail Tag).
- [ ] `Tag::getTotalHoursAttribute()` calcolato sull'unione deduplicata (story taggate ∪ loro figli), ogni story contata una volta sola.
- [ ] `Tag::getEstimateAttribute()`, `estimatedHoursField()`, export Excel, `Api\StoryController`, `ReportController`: **verificati come non modificati** (requisito di non-regressione, con test dove sensato).
- [ ] SAL% (`getSalAttribute`, `calculateSalPercentage`), colonna `SAL t` (`app/Nova/Tag.php:61-85`, `onlyOnIndex()`) e metrica `TagHoursTotal` restano coerenti senza modifiche proprie: assorbono il cambio a monte in `getTotalHoursAttribute()`.
- [ ] **N+1 / costo ripetuto su `SAL t`**: `app/Nova/Tag.php:61-85` chiama `getTotalHoursAttribute()` **3-4 volte per riga**. Memoization a livello di **modello** `Tag::getTotalHoursAttribute()` (non nella risorsa Nova), cosi da coprire in un solo punto sia `app/Nova/Tag.php` sia `app/Nova/TagGroup.php` (che eredita il metodo). Nessuna cache/Redis (fuori scope).
- [x] ~~N+1 sull'index globale delle Stories via subquery correlata su `Story::indexQuery()`~~ — **tentato e rimosso in review (v6)**: rompeva filtri Nova esistenti e non veniva comunque ereditato dalle risorse realmente usate in produzione. N+1 sull'index resta rischio accettato, vedi Rischi.
- [ ] **`TagGroup` coperto senza modifiche proprie**: `App\Models\TagGroup extends Tag`, ha una relazione diversa (`stories()`, pivot `tag_group_stories`, gia ridefinita come `tagged()` in `TagGroup.php:37`) ma **eredita** `getTotalHoursAttribute()`/`getSalAttribute()`/`calculateSalPercentage()` senza override, e `app/Nova/TagGroup.php` ha una propria colonna "SAL t" con lo stesso pattern di chiamate ripetute. Sia la logica di deduplica sia la memoization, vivendo in `Tag::getTotalHoursAttribute()`, si propagano automaticamente — nessuna riga da scrivere in `app/Nova/TagGroup.php`, solo verifica con un test dedicato.
- [ ] **Correggere il docblock fuorviante di `Story::effectiveMinutes()`** (`Story.php:600-603`: "Fonte autorevole per le ore effettive — il campo `hours` e deprecato", ormai falso), per evitare che un futuro lettore riapra l'equivoco chiarito in oc:8446.
- [ ] **Comando artisan read-only di confronto** (es. `tags:compare-sal-rollup`): per ogni tag stampa il valore attuale (`sum('hours')` sulle sole taggate) vs il nuovo (unione deduplicata con i figli), ed evidenzia i tag che contengono story con `hours` negativa. Nessuna scrittura. Da eseguire su dati reali prima del merge per misurare lo scostamento sui SAL gia comunicati ai clienti.
- [ ] **Test automatici**: padre con N figli; padre senza figli; story figlia; **figlio taggato con lo stesso tag del padre trattato come caso normale** (verifica di non-doppio-conteggio); padre con `hours = null` e figli valorizzati, e viceversa; figlio con `hours` negativa (verifica che il totale scenda, nessun clamp); non-regressione su stima del tag, export, API, report.

## Rischi
- **N+1 sull'index Stories (e viste analoghe)** — `hoursWithChildren()` esegue una query per riga quando deve sapere se ci sono figli. L'indice `stories_parent_id_index` e gia presente, quindi ogni query e un lookup, non una scansione della tabella. **Un tentativo di mitigazione via subquery su `Story::indexQuery()` e' stato fatto e rimosso in review formale** (v6, vedi intro documento): rompeva filtri Nova esistenti e non era comunque ereditato dalle risorse realmente usate (`DeveloperStory`, `CustomerStory`, `AssignedToMeStory`, …). Rischio accettato senza mitigazione applicativa. Il comando di confronto misura i **valori**, non i **tempi** di query.
- **Card "Story Time" in cima all'index Stories puo includere ore di figli fuori dallo scope di filtro della pagina**: `StoryTime::calculate()` ramo "Story senza id" costruisce l'insieme padre+figli a partire da `indexQuery()` (solo scoping di ruolo), poi Nova riapplica i Filter attivi (`Value::aggregate()` → `applyFilterQuery()`) sullo stesso insieme allargato. Un figlio con uno status diverso da quello filtrato sui padri puo essere escluso dal totale della card pur essendo incluso nel rollup del padre sul suo detail — la card in cima puo quindi non coincidere esattamente con la somma delle righe visibili sotto filtro attivo. Non e una corruzione di dati (il valore mostrato e comunque una somma reale, solo su un insieme leggermente diverso da quello ideale), e replicare esattamente il filtro sui figli richiederebbe intercettare il meccanismo di filtro di Nova — valutato non necessario per questo ciclo. Verificato in review, accettato come limite noto.
- **Valori negativi propagati per scelta**: 280 story su 7537 con `hours < 0` entrano nei totali aggregati e nel SAL cliente. Consapevole e mitigato solo dalla segnalazione nel comando di confronto; la correzione vive in un altro ticket, **ancora da aprire**.
- **I numeri di SAL dei tag manuali cambiano** (salgono, per inclusione delle ore dei figli) su valori potenzialmente **gia comunicati a clienti**. Da misurare col comando di confronto prima del merge e da segnalare, non da scoprire dopo.
- **Asimmetria stima/ore visibile in UI**: sulla stessa riga dell'index si leggeranno `Estimed Hours` del solo padre e `Effective Hours` di padre + figli. E **intenzionale** (stima top-down, ore bottom-up) ma non e autoevidente per chi guarda: un lettore puo interpretarlo come uno sforamento. Nessuna etichetta o tooltip previsto in questo ciclo — nessuna nuova chiave di traduzione. Rischio di comunicazione, non tecnico.
- **Dato di produzione non misurato**: quante story figlie condividono oggi un tag col padre e di quanto scostano i SAL. Richiede l'esecuzione del comando di confronto su un ambiente con dati reali.
- **Rollback**: il codice di oc:8421 si revert facilmente — nulla di persistito, nessuna migration.

## Out of scope
- Modifiche di schema DB e migration di qualsiasi tipo (lo schema padre-figlio e gia sufficiente).
- Qualsiasi modifica a `Story::effectiveMinutes()`/`effectiveMinutesForStory()` — non toccate, non usate come base di alcun calcolo (solo il docblock viene corretto).
- Qualsiasi rollup della **stima**, in qualunque punto (ticket e tag).
- Modifiche all'auto-tagging (`TagService`): i figli continuano a ricevere i tag automatici.
- Campo Nova "Ticket correlati" / `childStories()`, guard anti-nipoti, cascata di stato padre→figli: non toccati.
- Correzione delle `hours` negative in `StoryTimeService` — ticket dedicato, da aprire.
- Rimozione della colonna `hours` e delle sue scritture.
- Gerarchia a piu di 2 livelli; propagazione di status figli → padre; riassegnazione automatica di assegnatari.
- Cache/Redis per il rollup (mitigazione N+1 solo via memoization/subquery batch).
- Modifiche a export Excel, API pubblica `/api/stories`, `ReportController`.

## Moduli toccati
- `app/Models/Story.php` — due modifiche: (1) helper interno per il tempo effettivo con figli, basato su query dirette su `parent_id`; (2) correzione docblock di `effectiveMinutes()`. Non tocca cascata status, guard anti-nipoti, ne il campo Nova "Ticket correlati".
- `app/Models/Tag.php` — solo `getTotalHoursAttribute()` (unione deduplicata via `parent_id`, con memoization). `getEstimateAttribute()` **non si tocca**
- `app/Traits/fieldTrait.php` — `effectiveHoursField()` (ramo index/detail). `estimatedHoursField()` **non si tocca**
- `app/Nova/Metrics/StoryTime.php` — `calculate()`, tutti e tre i rami
- `app/Nova/Story.php` — **nessuna modifica finale**: un tentativo di `addSelect` anti-N+1 su `indexQuery()` e' stato fatto e rimosso in review (v6) perche rompeva altri filtri Nova.
- `app/Nova/Tag.php` — nessuna modifica di codice, beneficia della memoization in `Tag.php`
- `app/Nova/TagGroup.php` — nessuna modifica di codice, stesso beneficio (eredita `getTotalHoursAttribute()`)
- `app/Nova/Metrics/TagHoursTotal.php` — nessuna modifica prevista (legge `getTotalHoursAttribute()`)
- `app/Console/Commands/` — nuovo comando `tags:compare-sal-rollup` (read-only)
- `tests/Feature/` — nuovi test sul rollup e sul non-doppio-conteggio
- `lang/it.json`, `lang/en.json` — nessuna nuova chiave (riuso di "Effective Hours")
