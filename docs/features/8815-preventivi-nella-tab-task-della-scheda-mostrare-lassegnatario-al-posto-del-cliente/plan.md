> Ticket: oc:8815

# Piano — Preventivi: nella tab Task della scheda mostrare l'assegnatario al posto del cliente

Riferimento: [overview.md](overview.md). Tutto nel repo principale `orchestrator`, nessun submodule.

**Commit:** i commit indicati sono istruzioni per il dev, non azioni automatiche. Nessun `git commit`,
`git add` o `git push` senza conferma esplicita.

## Task 1 — Branch

```bash
git checkout -b feature/oc-8815-preventivi-nella-tab-task-della-scheda-mostrare-lassegnatario-al-posto-del-cliente
```

## Task 2 — Test prima del codice

File: `tests/Feature/TaskNovaResourceTest.php`

1. Aggiungere due helper privati che, dato l'elenco dei campi, restituiscono il campo `assignee`
   visibile in lista (`showOnIndex`) e quello visibile nel form di creazione (`showOnCreation`).
2. Riscrivere `test_quote_subpanel_fields_keep_original_layout_without_assignee_column` in
   `test_quote_subpanel_shows_assignee_column_instead_of_customer`, con `viaResource=quotes`:
   - `__('Customer')` **non** è fra i nomi dei campi;
   - i campi con attributo `assignee` sono esattamente due;
   - quello di lista: `showOnIndex` true, `showOnCreation` e `showOnUpdate` false, non sortable;
   - quello del form: `showOnIndex` false, `showOnCreation` e `showOnUpdate` true;
   - ordine dei nomi (ID escluso): `Task, Quote, Assignee, Due date, Status, Completed, Assignee, Notes`;
   - `assertCount(9, $fields)` resta valido (Cliente esce, Assegnatario di lista entra);
   - resta il controllo `date-time-field` su «Due date».
3. In `test_assignee_field_is_required_and_lists_only_users_with_task_access` (riga 170) e in
   `test_assignee_field_defaults_to_quote_owner_in_quote_subpanel_form` (riga 193) sostituire
   `firstWhere('attribute', 'assignee')` con l'helper del campo del form. Il test di riga 212 usa la
   vista globale (`/`), dove il campo è uno solo: non va toccato.
4. Il test della vista globale `test_global_index_fields_include_reordered_columns_and_assignee`
   controlla già che «Cliente» ci sia: lasciarlo com'è.
5. Eseguire la classe e verificare che il nuovo test di layout **fallisca** (il codice non è ancora
   cambiato) e gli altri passino:

   ```bash
   docker exec php81_orchestrator php artisan test --filter=TaskNovaResourceTest
   ```

## Task 3 — Codice

File: `app/Nova/Task.php`, ramo `if ($request->viaResource === 'quotes')` di `fields()`.

1. Sostituire `$this->customerField(),` con `$this->assigneeField()->exceptOnForms(),` nella stessa
   posizione (dopo il `BelongsTo` Quote, prima di «Due date»). Nessun `->sortable()`.
2. Lasciare `$this->assigneeField()->onlyOnForms(),` in fondo, e riscrivere il commento sopra
   (oggi: «la lista del tab Task della Quote resta senza colonna Assegnatario (oc:8402)») in: nella
   lista l'assegnatario prende il posto del cliente (oc:8815), nel form resta in fondo; i due campi
   nascono dallo stesso `assigneeField()`.
3. Non toccare il ramo globale, `customerField()` (usato ancora dal ramo globale), `indexQuery()`,
   `filters()` né `MyTask`.
4. Rieseguire la classe: tutti verdi.

   ```bash
   docker exec php81_orchestrator php artisan test --filter=TaskNovaResourceTest
   ```

5. Rieseguire le classi vicine che toccano task e preventivi:

   ```bash
   docker exec php81_orchestrator php artisan test --filter='QuoteNovaResourceTest|TaskTest|QuotesWithoutActiveTasksLensTest'
   ```

Commit suggerito: `feat(oc:8815): assegnatario al posto del cliente nella tab Task del preventivo`

## Task 4 — Pagina di conoscenza

File: `docs/knowledge/task-e-quote.md`

- Nel punto su `fields()`/`filters()`/`indexQuery()` scoped su `viaResource === 'quotes'` (righe
  ~79-84): sostituire «nel sub-panel il campo Assegnatario è `onlyOnForms()`, così la lista resta
  senza colonna» con la regola nuova — nella lista del sub-panel l'assegnatario prende il posto del
  cliente (link, non ordinabile), nel form resta in fondo; sono due campi dallo stesso
  `assigneeField()` e nei test vanno cercati per visibilità, non con `firstWhere`.
- In «Come ci siamo arrivati»: la scelta di oc:8402 (lista senza colonna Assegnatario), superata
  da oc:8815 perché il cliente è ridondante nella scheda del preventivo e l'assegnatario no.

## Task 5 — Verifica a mano (tester, ambiente dev)

1. Aprire un preventivo con almeno un task (es. 219) → tab «Task»: le colonne sono
   ID · Task · Assegnatario · Scadenza · Stato · Completato; al posto del cliente c'è il nome
   dell'assegnatario.
2. Con un admin il nome è un link alla scheda utente; con un manager o developer è testo.
3. «Crea Task» dalla tab: il campo «Assegnatario» è ancora in fondo, precompilato con l'owner del
   preventivo.
4. CRM → Task e «I miei task»: la colonna «Cliente» c'è ancora, nulla è cambiato.
