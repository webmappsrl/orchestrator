> Ticket: oc:8446

# Notes — Documentazione: calcolo ore stimate/effettive

## Deviazioni dal piano

- **Ambiente Docker con drift di versione PHP noto**: `php81_orchestrator` (ricostruito dal `docker-compose.yml` attuale) gira PHP 8.2.15 mentre `composer.lock` richiede ≥8.4 — impossibile usare `tinker` per verifiche sul DB locale. Problema preesistente, già documentato in CLAUDE.md (blocco migrazione PHP 8.1→8.4 di oc:8412). Applicata la regola fail-soft della Fase: environment-setup: proseguito solo su base ricerca statica del codice (grep estese, `git log --all -S`), senza verifica runtime sui dati.
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
