> Ticket: oc:8446

# Notes — Documentazione: calcolo ore stimate/effettive

## Deviazioni dal piano

- **Ambiente Docker con drift di versione PHP noto**: `php81_orchestrator` (ricostruito dal `docker-compose.yml` attuale) gira PHP 8.2.15 mentre `composer.lock` richiede ≥8.4 — impossibile usare `tinker` per verifiche sul DB locale. Il blocco era preesistente e non introdotto da questo ticket. **Risolto successivamente:** il container gira ora PHP 8.4.21, coerente con `composer.json` (`^8.4`), e `tinker` funziona — verificato durante la review. (La nota originale rimandava a CLAUDE.md come fonte, ma CLAUDE.md non documentava lo stato dell'ambiente: citava il blocco solo di passaggio, a sostegno di una decisione su una dipendenza.) Applicata la regola fail-soft della Fase: environment-setup: proseguito solo su base ricerca statica del codice (grep estese, `git log --all -S`), senza verifica runtime sui dati.
- **`docs/features/8421-rollup-tempo-stima-padre-figlio/overview.md` non esisteva sul branch di oc:8446**: era stato committato solo su `feature/oc-8421-rollup-tempo-stima-padre-figlio` (commit `c24f93a`), non ancora mergiato su `develop` (da cui è stato creato il branch di oc:8446). Prima soluzione tentata: `git cherry-pick c24f93a` per portare il file sul branch corrente e aggiungere lì la riga di link (scelta confermata dal dev tra tre opzioni proposte). **Decisione successiva, ribaltata dal dev**: il link va tenuto fuori da questo branch — oc:8446 è un'analisi indipendente (nessun commento/collegamento a codice o commit di oc:8421 nel documento tecnico), quindi non deve trascinarsi commit del branch di un altro ticket. Eseguito `git reset --hard e0865db` per rimuovere il cherry-pick (i file nuovi di oc:8446, essendo untracked, non sono stati toccati dal reset). Il link da oc:8421 verso `docs/calcolo-ore-effettive-stimate.md` resta da aggiungere separatamente, direttamente sul branch `feature/oc-8421-...`.

## Bug trovati

Nessun bug applicativo introdotto o corretto in questo ticket (documentazione pura). Trovate — e documentate come fatti neutri, senza correggerle — alcune divergenze di comportamento preesistenti nel sistema di calcolo ore (vedi `docs/calcolo-ore-effettive-stimate.md`): truthy-check che lascia `hours` stantio quando il calcolo dà 0, `effectiveMinutes()`/`effectiveMinutesForStory()` mai collegate a nessun consumatore nonostante il docblock le dichiari "fonte autorevole".

## Decisioni

- Confermato con il dev (Fase: reverse-interaction) che il documento resta puramente descrittivo: nessuna raccomandazione, nessuna proposta di unificazione tra le tre implementazioni trovate.
- Struttura a due file confermata: tracciamento standard `wm-plan` (`overview.md`/`plan.md`/`notes.md` in `docs/features/8446-.../`) separato dal documento tecnico consultabile (`docs/calcolo-ore-effettive-stimate.md`, root `docs/`).
- Durante la Fase: challenge, il subagente ha trovato un'imprecisione fattuale già presente nell'overview approvato (il path di scrittura di `hours` via `Story::save()` non passa da `StoryTimeService::handle()`) — corretta prima di procedere a `write-plan`.
- Su richiesta del dev, aggiunta al documento tecnico anche l'origine storica di `effectiveMinutes()`/`effectiveMinutesForStory()` (commit `34c59326`, oc:8123) per spiegare perché il CTO la cita come "funzione coinvolta" nel calcolo pur non essendo mai usata.

## Follow-up

- Nessun meccanismo automatico di sincronizzazione tra il documento tecnico e il codice: il documento è "verificato al commit `e0865db`" e diventerà stantio alla prossima modifica di uno dei file mappati (`StoryTimeService`, `Story.php`, `StoryMetricsCalculator`, `Tag.php`, `TeamPerformanceController`). Nessun backlink dal codice al documento — accettato consapevolmente, fuori scope per questo ticket (solo disclaimer di data/commit in testa al documento).
- **Link da oc:8421 non ancora aggiunto**: da fare separatamente sul branch `feature/oc-8421-rollup-tempo-stima-padre-figlio` (riga "Vedi mappa completa del sistema attuale in `docs/calcolo-ore-effettive-stimate.md` (oc:8446)."), quando quel branch torna attivo o al momento del merge.

---

## Ciclo di review formale (`wm-review-ticket`, 2026-09-02)

Review eseguita sul branch prima del merge di PR #250. Esito iniziale: **DA CORREGGERE** — 6 finding bloccanti e 18 cleanup. Il nucleo del documento (la ricostruzione storica di `effectiveMinutes()`: commit d'origine, assenza dal piano approvato di oc:8123, tripla verifica dei chiamanti con riserva su `tinker`) è risultato accurato e non è stato toccato. I bloccanti riguardavano tutti la mappa di *chi scrive* e *chi legge* la colonna `hours`, cioè la parte destinata a stimare il raggio d'impatto di una modifica.

### Bloccanti corretti

1. **Terzo path di scrittura di `hours` omesso.** Il documento dichiarava due path (`Story::save()` e il comando `service:story-time`). Ne esiste un terzo: `fieldTrait::effectiveHoursField()` ritorna un `Number` **editabile** su create/update (`app/Nova/Story.php`, `app/Nova/ArchivedStoryShowedByCustomer.php`), e Nova bypassa `$fillable`. Conseguenza documentata: `hours` non è un valore interamente derivato dai log, e un valore inserito a mano viene sovrascritto al successivo cambio di `status`.
2. **Lista dei consumatori di `hours` incompleta: 3 dichiarati, 11 reali.** Aggiunti `Nova/Metrics/StoryTime` (usata in `Nova/Story` e `Lenses/StoriesByQuarter`), `ReportController` (5 punti, con cache schedulata `reports:refresh-cache`), `SelectedStoriesToExcel`, `Api/StoryController` (**API pubblica**) e `estimationAccuracy()`.
3. **`cycleTimeMinutes()` non ha un solo consumatore.** Aggiunto `developerMetrics()` → `GeneratePerformanceReportJob` (report PDF trimestrale). Annotato che la dashboard Team Performance consuma *entrambe* le misure.
4. **Tipo, unità e nullabilità non dichiarati.** `hours` è `float` nullable in ore arrotondate per-story; le altre due sono `?int` in minuti; `estimated_hours` è `decimal(5,2)`. Aggiunte righe dedicate in tabella e rinominata la riga "Precisione" in "Granulo della colonna sorgente" — descriveva il granulo della sorgente, non la precisione del risultato, e induceva a credere `hours` la più precisa quando è l'unica che perde informazione.
5. **Accoppiamento a un ticket in sviluppo in `CLAUDE.md`.** La sezione istruiva su cosa fare in un ticket specifico ancora aperto. Riscritta tenendo il fatto durevole (quale misura è letta da chi, e che il docblock di `effectiveMinutes()` è falso) e rimuovendo ogni riferimento a ticket in corso, che sarebbe scaduto col ticket stesso.
6. **Verifica empirica assente.** Le affermazioni sul comportamento a runtime erano deduzioni dal codice, presentate senza riserva, e il limite era registrato solo qui in `notes.md` — non nel documento che il lettore apre. Il blocco riguardava `tinker`, non l'accesso al DB: `psql` gira nel container Postgres e non passa da PHP. Eseguita la verifica e aggiunta al documento una sezione **Stato reale dei dati** con query riproducibili.

### Fatti emersi dalla verifica sul DB (non presenti nella prima stesura)

| Fatto | Valore |
|---|---|
| Divergenza tra le due misure in uso | **58%** delle story (1890/3242), fattore **~2x** sulla media, scarto massimo ~36 giorni |
| `hours` è `NULL` | **3970/7537 (53%)** — il null è il caso normale |
| `hours` negativa | **280 story**, fino a `-0.23`, non spiegata da alcuna anomalia nota |
| Story con `hours` *e* `estimated_hours` | **99/7537 (1.3%)** — le sole su cui SAL% e `estimationAccuracy()` producono un numero |

### Cleanup applicati

Riferimenti ancorati a nomi di metodo invece che a range di riga (`Story.php` e `fieldTrait.php` sono tra i file più modificati del repo: i range invecchiano in settimane); aggiunti indice e glossario; disclaimer spostato sotto l'H1; gerarchia dei titoli resa parallela tra le tre sezioni sorelle; ordine delle misure allineato tra sintesi, corpo e tabella; corretta l'etichetta della finestra oraria (`$hour > 8 && $hour < 18` dà **09:00–17:59**, non "8-18" — il commento nel codice sorgente è impreciso di un'ora); corretta l'affermazione su `git log --all -S` (7 commit, di cui uno solo tocca codice, non "l'unico commit"); corretto `$appends` ("non definita", non "array vuoto"); precisato che il granulo al minuto di `viewed_at` vale per i log di stato e non per la colonna; aggiunti i path mancanti (`LogStory`, `SendWaitingStoryReminder`); completati i consumatori di `estimated_hours` (`TagGroup`, export Excel, API) e il suo path di scrittura via API; rimosso l'aggettivo "deprecata" dal titolo della sezione `hours`, la cui unica fonte era il docblock che il documento stesso dichiara falso.

## Decisioni (ciclo di review)

- **Terminologia fissata a glossario**: "ore effettive" designa il concetto, mai un valore; le tre misure sono sempre chiamate col nome del simbolo. Lo stato della misura 3 è sempre "nessun chiamante", mai "dead code": la verifica statica non può escludere un'invocazione via `tinker`, e la riserva era già dichiarata nel documento — usare "dead code" affermava più di quanto dimostrato.
- **Ordine delle misure invertito nel corpo** per far precedere le due realmente in uso, allineandolo alla numerazione della sintesi invece del contrario.
- **Refuso corretto in `CLAUDE.md` e nei documenti di specifica di oc:8412**: descrivevano un approccio valutato e non rilasciato, con un rimando che non trovava più riscontro nel codice. Rimosso dai documenti che dicono *cos'è* la feature (`overview.md`, `plan.md`) e da `CLAUDE.md`, che è contesto attivo; il `notes.md` di oc:8412 resta il verbale di quel ciclo. Modifica estranea a oc:8446 ma applicata qui perché `CLAUDE.md` viene comunque aggiornato in questo ciclo.

## Follow-up (ciclo di review)

- **Il documento resta senza owner per l'aggiornamento.** Un ticket di rollup padre-figlio modificherebbe `Story.php`, `Tag.php`, `fieldTrait.php` e `app/Nova/Story.php` — 4 dei file mappati, incluse `getTotalHoursAttribute()` e `getEstimateAttribute()` — e al suo merge il documento intitolato "come funziona oggi" descriverebbe lo stato precedente senza alcun marcatore. L'ancoraggio a nomi di metodo riduce la deriva dei riferimenti, non l'obsolescenza dei contenuti.
- **`hours` negativa su 280 story** è un difetto di calcolo di `StoryTimeService` sui limiti della finestra oraria, non documentato prima di questo ciclo e non corretto qui (il ticket è di sola documentazione). Merita un ticket dedicato: qualsiasi aggregazione propaga i valori negativi silenziosamente.
- **`CLAUDE.md` riga 101** (oc:8413) attribuisce un `TypeError` a "PHP 8.1", ora stantio: il progetto gira su 8.4.
