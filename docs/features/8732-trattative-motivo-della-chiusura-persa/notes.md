> Ticket: oc:8732

# Notes — Trattative: motivo della chiusura persa

## Deviazioni dal piano

- **Task 7 aggiunto dopo l'approvazione del piano:** la risposta 3 della review (commit `44b31c6`,
  12/10/2026) ha portato il Kanban `Sales` dentro il ticket, mentre il piano lo lasciava a un
  ticket a parte. Stima portata da 4h a 6h.

Un dettaglio dei test Nova del task 2: la validazione non passa da
`Resource::validateForUpdate()`, che senza la rotta Nova completa risponde 404 (le regole dei
`BelongsTo` risolvono la risorsa dalla rotta). Il test costruisce le regole come
`rulesForUpdate()` — `updateFields()` + `applyDependsOn()` sui dati inviati — limitandole al solo
`lost_reason`.

## Bug trovati

- **Errore nel dettaglio dei «Preventivi archiviati», preesistente ed estraneo al ticket** (trovato
  nel test manuale del 13/10/2026): il toast «Call to undefined method App\Models\Quote::newModel()»
  e «Unable to load actions». L'Action `EditFields` teneva la classe della risorsa nella proprietà
  `$resource`, che Nova sovrascrive con il model nel dettaglio e sulle righe dell'index
  (`vendor/laravel/nova/src/ResolvesActions.php`). Corretto rinominandola in `$resourceClass`
  (`app/Nova/Actions/EditFields.php`). L'Action resta comunque inefficace: non trova `status`,
  che in `Quote` sta dentro `Tab::group`, e la policy vieta la modifica delle trattative chiuse.

## Decisioni

- **Review dell'overview (12/10/2026, PR #262, commit `44b31c6`):** confermati il motivo nel form
  con `dependsOn` e l'obbligo via API; l'aggiornamento della skill commerciale è di chi la gestisce,
  non di questo ticket; il drop in «Chiuso Perso» sul Kanban `Sales` va rifiutato in questo ticket
  con un messaggio. Chiesta la prova a mano del testo scritto e poi nascosto: superata (nel DB
  `lost_reason` resta null), insieme al salvataggio senza motivo (rifiutato) e al drop sul Kanban
  (rifiutato, card tornata indietro con il messaggio).
- **Il blocco del Kanban sta nel `config` che manda il browser**, come `deniedUpdateRoles`: è il
  modo in cui il componente riceve tutte le opzioni. Chi modificasse a mano la richiesta, o una
  scheda del Kanban aperta prima del deploy (config senza la chiave), potrebbe chiudere una
  trattativa come persa senza motivo. L'hook del model non lo impedisce: azzera il motivo sulle
  trattative non perse, ma non lo richiede su quelle perse.
- **Regola nel model invece che nei punti di ingresso** (dalla challenge): l'hook `saving` azzera
  il motivo per ogni stato diverso da `closed lost`. Copre anche il Kanban, che riapre una
  trattativa persa senza passare dalla policy; al prezzo di perdere il motivo vecchio alla
  riapertura, che a quel punto non descrive più la trattativa.
- **Stima:** proposta di `wm-estimate` 2,41h misurate + 3,05h stimate; la dev ha scelto 4h, poi
  portate a 6h per il Kanban.

## Follow-up

- **Skill commerciale (a cura di chi la gestisce):** con `status: "closed lost"` deve mandare
  `lost_reason` chiedendolo all'utente; senza, dal deploy riceve 422.
