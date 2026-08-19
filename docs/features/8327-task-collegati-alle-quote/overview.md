> Ticket: oc:8327

# Task collegati alle Quote (replica feature HubSpot)

## Cosa cambia

Viene introdotta in Orchestrator un'entità `Task`: un semplice promemoria collegato a una `Quote` (belongsTo), con titolo, note, data/ora di scadenza (`due_date`) e stato (`todo`/`completed`). L'assegnatario del Task non è un campo indipendente ma è sempre derivato dall'utente proprietario della Quote collegata (`quote->user`) — nessuna assegnazione, tipo o priorità indipendenti.

Sono previste due viste:
- **Sub-panel dentro la Nova Resource `Quote`**: lista cronologica semplice dei Task collegati (non un mini-Kanban), con toggle rapido di completamento.
- **Vista globale `Task`**: index Nova standard, scoping automatico sull'utente loggato, ordinato per `due_date` crescente, con un **Nova Filter** (non Lens, non Kanban) per isolare Overdue / Due today / Upcoming / Completed / Tutti — pattern verificato sul comportamento reale di HubSpot (viste "All/Completed/Due today/Overdue/Upcoming", sempre scoping "assignee: me", nessuna soglia arbitraria oltre "oggi").

## Perché

Replicare in Orchestrator la feature Task di HubSpot per la gestione commerciale: ogni Quote (= trattativa/deal) deve poter avere promemoria associati che il commerciale usa per organizzare il proprio lavoro, senza introdurre un sistema di task management generico o cross-utente.

## Requisiti

- [ ] Modello `Task`: `title`, `notes` (testo lungo, HTML da editor WYSIWYG), `due_date` (datetime: data **e ora** di scadenza del Task, non la data di creazione né quella di completamento), `status` (todo/completed), `completed_at`, FK `quote_id` (belongsTo Quote)
- [ ] Campo `notes` editato in Nova tramite `Marshmallow\Tiptap\Tiptap` (stesso componente WYSIWYG già usato per campi testuali di `Quote`, es. `additional_info`/`payment_plan`) — colonna DB testuale che conserva l'HTML prodotto dall'editor, nessun parsing/sanitizzazione aggiuntiva oltre a quella già in uso nel progetto per Tiptap
- [ ] Nessun campo `type`/`priority`/`queue`/`reminder` sul Task (esplicitamente fuori scope, a differenza dei campi visti negli screenshot HubSpot che includono anche questi)
- [ ] Assegnatario derivato via accessor (es. `Task::assignee()` → risolve tramite `quote->user`), mai copiato o persistito sul Task
- [ ] Task su Quote senza `user_id` → creazione **permessa** (nessuna restrizione sull'autore del Task: chiunque abbia accesso a Nova può creare un Task su qualsiasi Quote), l'assegnatario risulta semplicemente `null` ("Non assegnato" a valle) — decisione presa in esecuzione (vedi `notes.md`), diverge dal blocco inizialmente previsto in questa sezione
- [ ] Campo `creator_id` sul Task (FK verso `users`, nullable, valorizzato automaticamente all'utente autenticato in creazione) — non previsto in pianificazione, introdotto per autorizzare l'azione di cambio stato solo a chi ha creato il Task (vedi requisito Nova Action sotto)
- [ ] Nova Resource `Task`: vista standalone con index ordinato per `due_date` asc, scoping automatico sull'utente loggato — mostra i Task delle Quote di cui l'utente è owner **oppure** i Task creati dall'utente stesso (anche su Quote di altri o senza owner, altrimenti resterebbero invisibili per sempre a chi li ha creati) — **applicato solo quando `Task` è la vista principale**, non quando la lista è richiesta come relazione (`HasMany`) dal sub-panel della Quote, altrimenti il sub-panel di una Quote non propria risulterebbe erroneamente vuoto
- [ ] Colonne index `Task`: Stato (`status`, todo/completed con badge urgenza — vedi [UX] sotto), Titolo (`title`), Preventivo (`quote`), Cliente (`quote.customer.full_name`, cliccabile — link al dettaglio Customer), Data di scadenza (`due_date`) — sostituisce la colonna "Assegnatario" originariamente prevista
- [ ] Nova Action `ToggleTaskCompleted` (mostrata inline nel menu riga), autorizzata solo per `creator_id === utente loggato`; sostituisce l'azione "Replica" di default di Nova (disabilitata con `authorizedToReplicate() => false`)
- [ ] Campo "Completato" nascosto dal form di creazione (`hideWhenCreating()`) — un Task appena creato è sempre `todo`, il campo resta visibile/modificabile solo su modifica/dettaglio
- [ ] Nova Filter `TaskDueDateFilter` con opzioni: Tutti, Overdue (`due_date` < oggi, status todo), Due today (`due_date` = oggi, status todo), Upcoming (`due_date` > oggi, status todo), Completed (status completed) — confronto "oggi" sempre su timezone di sistema (`Europe/Rome`), nessuna gestione per-utente (team interamente in Italia)
- [ ] Sub-panel Task dentro la Nova Resource `Quote` esistente: lista cronologica (ordinata per `due_date`), toggle rapido di completamento inline
- [ ] Riassegnazione Quote (cambio `user_id`) → i Task collegati seguono automaticamente il nuovo assegnatario (conseguenza naturale della derivazione via relazione, nessuna gestione ad hoc)
- [ ] [UX] Badge/indicatore urgenza con colore semantico (rosso=scaduto, arancione=oggi, verde=imminente, grigio=completato) e testo relativo ("scaduto da 3gg", "oggi", "tra 2gg") invece della sola data assoluta
- [ ] [UX] Badge separato per "completato in ritardo" (`completed_at` > `due_date`)
- [ ] Toggle di completamento: passaggio a `completed` valorizza `completed_at = now()`; ripristino a `todo` azzera `completed_at` (evita badge "completato in ritardo" fantasma su un task non più completato)
- [ ] Traduzioni IT/EN per tutte le nuove label (locale default `it`, lingue disponibili `it`/`en` in `lang/`)

## Rischi

- **Task orfani (Quote senza `user_id`)**: accettato consapevolmente in esecuzione — la creazione non è più bloccata (134/185 Quote attuali non hanno `user_id`), il Task risulta "non assegnato" e non compare nella vista globale filtrata per utente, ma resta visibile nel sub-panel della Quote.
- **Ambiguità semantica Kanban vs Filter**: la description originale del ticket proponeva di riusare il componente Kanban condiviso (`nova-components/kanban-card/`, già usato da Story e Quote) con colonne temporali calcolate. Verificato con ricerca sul comportamento reale HubSpot che l'interfaccia di riferimento è in realtà tab-di-filtro + tabella, non un board drag&drop — scelto **Nova Filter** al posto del Kanban per fedeltà al comportamento da replicare e per non introdurre un adapter su un componente condiviso e delicato (nessun impatto su Sales/Story Kanban).
- **[UX] Colonne temporali non sono stati persistiti**: se in futuro si volesse comunque un Kanban, il drag&drop tra colonne "temporali" sarebbe semanticamente ambiguo (non c'è un'azione di scrittura naturale) — rischio esplicitamente evitato scegliendo Filter invece di Kanban in questo ciclo.

- **Nessun audit trail sull'assegnatario storico**: poiché l'assegnatario è sempre derivato da `quote->user` (mai persistito sul Task), una riassegnazione della Quote riscrive retroattivamente la paternità percepita di tutti i Task collegati, inclusi quelli già completati. Accettato consapevolmente in Fase: reverse-interaction (nessuna assegnazione indipendente per design) — non recuperabile in un secondo momento senza un campo storico dedicato, se mai richiesto in futuro.
- **Cascade delete Quote→Task** (`onDelete('cascade')`): l'eliminazione di una Quote cancella silenziosamente tutti i Task collegati, anche quelli con contenuto (note) rilevante. Accettato: i Task sono considerati privi di valore autonomo senza la Quote di riferimento.

## Out of scope

- Nessuna notifica/promemoria email o push in prossimità della scadenza (il campo "Promemoria" visto negli screenshot HubSpot non viene replicato in questo ciclo)
- Nessun campo `type`/`priority`/`queue` sul Task
- Nessuna sincronizzazione con calendario esterno (Google/Outlook)
- Nessuna vista cross-utente/supervisione (Admin/Manager che vogliono vedere i Task di altri commerciali usano l'impersonation già esistente in `App\Models\User`)
- Nessun Kanban board per la vista globale Task (valutato e scartato in favore di Nova Filter, vedi sezione Rischi)

## Moduli toccati

- `app/Models/Task.php` (nuovo)
- `database/migrations/2026_08_19_113137_create_tasks_table.php` (nuovo)
- `database/migrations/2026_08_19_123457_add_creator_id_to_tasks_table.php` (nuovo)
- `app/Nova/Task.php` (nuovo, Nova Resource)
- `app/Nova/Filters/TaskDueDateFilter.php` (nuovo)
- `app/Nova/Actions/ToggleTaskCompleted.php` (nuovo)
- `app/Nova/Quote.php` (aggiunta relazione/sub-panel Task, fallback `title()`)
- `app/Models/Quote.php` (aggiunta relazione `tasks()`)
- `lang/it.json`, `lang/en.json` (nuove traduzioni)
- `tests/Feature/TaskTest.php` (nuovo — modello, cascade delete, reset `completed_at`, filtro `TaskDueDateFilter`, autorizzazione azione per creatore)
