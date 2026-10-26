> Ticket: oc:8815

# Preventivi: nella tab Task della scheda mostrare l'assegnatario al posto del cliente

## Cosa cambia

Nella scheda di un preventivo (es. `/resources/quotes/219`), la lista della tab «Task» mostra la
colonna **«Assegnatario»** al posto della colonna «Cliente», nella stessa posizione. Le colonne
diventano: ID · Task · **Assegnatario** · Scadenza · Stato · Completato.

Il cambiamento è confinato al ramo `viaResource === 'quotes'` di `App\Nova\Task::fields()`: la vista
globale CRM → Task e «I miei task» (`MyTask`, che eredita `fields()`) non cambiano.

## Perché

Il cliente, in quella lista, è sempre lo stesso del preventivo e si vede già nella tab «Principale»;
ogni task invece può avere un assegnatario diverso, ed è l'informazione che serve leggendo i task di
un preventivo (ticket oc:8815, `customer_request`). La richiesta è di Alessio, indicata il
21/10/2026 (dev, in questa sessione; scrum 21/10 12:55, Carla Cupani: «era quello di Alessio che
aveva indicato stamattina»).

Il ticket rovescia una scelta di oc:8402, che aveva tenuto la lista della tab senza colonna
Assegnatario (`app/Nova/Task.php:58-59`, `docs/knowledge/task-e-quote.md`).

## Requisiti

- [ ] Nella lista della tab «Task» del preventivo, la colonna «Cliente» è sostituita da
      «Assegnatario», nella stessa posizione (dopo «Task», prima di «Scadenza»). — ticket oc:8815
- [ ] La colonna «Assegnatario» è un link alla scheda dell'utente, con lo stesso campo e lo stesso
      comportamento della vista CRM → Task (`assigneeField()`, `BelongsTo` verso `User`). Il link
      c'è solo per chi può aprire la scheda utente, cioè gli admin (`UserPolicy::before()`): gli
      altri ruoli vedono il nome come testo, ed è il comportamento atteso, non un bug. — dev,
      domanda 4 e challenge
- [ ] La colonna «Assegnatario» **non** è ordinabile, come non lo era «Cliente». — dev, domanda 3
- [ ] Un task senza assegnatario mostra «—» (comportamento di default di Nova per un `BelongsTo`
      vuoto, lo stesso che `customerField()` restituiva per un preventivo senza cliente).
- [ ] La nuova colonna ha la stessa visibilità della colonna che sostituisce (`exceptOnForms()`),
      così «al posto di» vale ovunque il ramo `quotes` mostrava il cliente.
- [ ] Il form aperto dalla tab (pulsante «Crea Task» e matita di modifica) **non cambia**: il campo
      «Assegnatario» resta in fondo, dopo «Completato», con default e regole attuali. — dev, domanda 2
- [ ] La vista CRM → Task, «I miei task» e l'indice dei preventivi restano identici, colonna
      «Cliente» compresa. — ticket oc:8815, dev
- [ ] Il test `test_quote_subpanel_fields_keep_original_layout_without_assignee_column` è
      aggiornato al nuovo layout e verifica: «Assegnatario» al posto di «Cliente» nella lista della
      tab; «Assegnatario» ancora in fondo nel form; esattamente due campi «Assegnatario» nel ramo
      `quotes`, uno solo in lista e uno solo nel form; «Cliente» ancora presente nella vista
      globale. — dev, domanda 5 e challenge
- [ ] I test che nel ramo `quotes` cercano il campo del form (`TaskNovaResourceTest.php` righe 170
      e 193; quello di riga 212 usa la vista globale e non cambia) lo individuano per visibilità (`showOnCreation`), non con
      `firstWhere('attribute', 'assignee')`, che dopo la modifica troverebbe il campo della lista.
      — challenge
- [ ] Nessuna nuova chiave di traduzione: «Assignee»/«Assegnatario» esiste già in `lang/en.json` e
      `lang/it.json`.

## Rischi

- **La regola di oc:8402 è scritta in tre punti** (test, commento in `Task.php`, pagina
  `docs/knowledge/task-e-quote.md`): se se ne aggiorna solo una parte, la documentazione dice il
  contrario del codice. Mitigazione: i tre punti sono nello stesso task del piano.
- **Una modifica fuori dal ramo `quotes` farebbe cambiare anche CRM → Task e «I miei task»**, che
  condividono la stessa Resource. Mitigazione: il test aggiornato verifica anche che la vista
  globale abbia ancora «Cliente».
- **Due campi «Assegnatario» nel ramo `quotes`** (colonna in lista, campo in fondo al form, scelta
  del dev): i test che cercano il campo con `firstWhere('attribute', 'assignee')` prenderebbero
  quello della lista e smetterebbero di controllare obbligatorietà e default del form, senza
  diventare rossi. Mitigazione: nei test i due campi si cercano per visibilità e il test di layout
  ne verifica il numero. Entrambi nascono da `assigneeField()`, quindi restano identici.

## Out of scope

- Ordinamento e filtro per assegnatario nella tab del preventivo (il filtro `TaskAssigneeFilter`
  resta solo nella vista globale).
- Il precaricamento dell'assegnatario (`with('assignee')`) nel ramo `quotes` di `indexQuery()`: la
  nuova colonna fa una query per riga, una in meno della colonna «Cliente» che sostituisce.
- Qualunque modifica al form dei task, alla vista CRM → Task, a «I miei task» e all'indice dei
  preventivi.

## Moduli toccati

Tutto nel repo principale `orchestrator`; nessun submodule coinvolto.

- `app/Nova/Task.php` — ramo `viaResource === 'quotes'` di `fields()`: `customerField()` sostituito
  da `assigneeField()` visibile fuori dai form; il campo del form in fondo resta; commento di
  oc:8402 aggiornato.
- `tests/Feature/TaskNovaResourceTest.php` — test del layout della tab aggiornato, con controllo
  della vista globale.
- `docs/knowledge/task-e-quote.md` — la regola «nel sub-panel il campo Assegnatario è
  `onlyOnForms()`» diventa la regola nuova, quella vecchia passa in «Come ci siamo arrivati».
