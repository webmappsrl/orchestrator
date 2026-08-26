> Ticket: oc:8402

# Riordino colonne e filtro assegnatario Task

## Cosa cambia
Nella vista globale Nova dei Task (Resources → Tasks, esclusa la vista sub-panel dentro il dettaglio di una Quote):
- cambia l'ordine dei campi in Cliente, Preventivo, Task (poi Scadenza, Stato, Completato, Assegnatario);
- la colonna Scadenza mostra solo la data (non più l'ora), in tutto il ciclo di vita del campo incluso il form di creazione/modifica;
- viene aggiunta una nuova colonna Assegnatario che mostra a chi è assegnato il task (derivato da `quote->user` tramite l'accessor `Task::assignee`, già esistente da oc:8327, mai persistito);
- viene aggiunto un nuovo filtro Assegnatario che permette di selezionare la persona a cui è assegnato il task, con opzioni limitate dinamicamente agli utenti che possiedono effettivamente almeno un task assegnato;
- `Task::indexQuery()` applica lo scoping `forUser($user)` in modo condizionato al ruolo: **Admin e Manager** vedono tutti i task (il filtro Assegnatario diventa lo strumento per isolare i propri o quelli di un collega), **tutti gli altri ruoli** (Developer, Editor, Customer) mantengono `forUser()` esattamente come oggi — nessuna modifica per loro.

## Perché
Con l'ordine attuale delle colonne, i campi più rilevanti per l'identificazione rapida del task (Cliente, Preventivo) sono in coda invece che in testa. Il team lavora sia sui propri task individuali sia, per alcune attività, in modo condiviso — serve quindi un modo rapido per isolare i task di una persona specifica senza dover scorrere l'intero elenco.

Verificando il codice attuale (`app/Nova/Task.php::indexQuery`, oc:8327) è emerso che la vista globale Tasks è oggi sempre ristretta a "task assegnati o creati da me" (`Task::scopeForUser`), senza alcuna eccezione per ruolo, e non esiste una `TaskPolicy`: `forUser()` è di fatto l'unico controllo di accesso su questa risorsa. Rimuoverlo per tutti gli utenti indistintamente avrebbe esposto l'intero backlog aziendale (incluse note interne Tiptap e collegamenti a Quote/Customer di altri clienti) anche a ruoli come Customer o Editor, che oggi hanno accesso Nova (`viewNova` gate) ma la cui vista Tasks è resa innocua solo dal filtro `forUser`.

La sezione menu `CRM` (che contiene la voce "Tasks") è già oggi visibile solo ad Admin e Manager (`canSee` in `NovaServiceProvider.php`) — sono gli unici ruoli che usano davvero questa vista globale. Confermato con l'utente: `forUser()` resta per tutti gli altri ruoli, e viene bypassato solo per Admin/Manager, che sono anche gli unici a guadagnare il nuovo filtro Assegnatario in modo utile.

## Requisiti
- [ ] Ordine colonne nella vista globale Tasks: Cliente, Preventivo, Task (poi Scadenza, Stato, Completato, Assegnatario)
- [ ] Colonna Scadenza mostra solo la data (index, filtro, form) — campo Nova `Date::make()` al posto di `DateTime::make()`, nessuna migrazione, colonna DB resta `datetime`
- [ ] Colonne Stato e Completato restano invariate
- [ ] Nuova colonna Assegnatario nella vista globale Tasks, valore derivato dall'accessor esistente `Task::assignee` (`quote->user`, mai persistito come colonna DB)
- [ ] Nuovo filtro Assegnatario (`select-filter`) nella vista globale Tasks, opzioni = utenti che possiedono almeno una Quote con un Task collegato (`User::whereHas('quotes.tasks')`), applicato via `Task::whereHas('quote', fn q => q->where('user_id', $value))`
- [ ] `Task::indexQuery()` bypassa `forUser($user)` per la vista globale solo se l'utente ha ruolo Admin o Manager (`$user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager)`); per tutti gli altri ruoli resta `forUser($user)` invariato; resta l'esclusione già esistente per utenti non autenticati; il sub-panel Quote (`$request->viaResource === 'quotes'`) resta invariato
- [ ] Label "Assegnatario"/"Assignee" già presente in `lang/it.json` e `lang/en.json` (aggiunta in oc:8327, non ancora usata nel codice) — riutilizzarla, non duplicarla; eventuali nuove chiavi per il filtro seguono la stessa convenzione (chiavi in inglese, lingua di default `it`)

## Rischi
- **Cambio di visibilità dati limitato ad Admin/Manager**: questi due ruoli vedranno d'ora in poi i task di tutti i colleghi (Admin/Manager), non solo i propri/creati da sé. Nessuna policy `TaskPolicy` esiste oggi — accettato perché sono già gli unici ruoli con accesso al menu Tasks (`canSee` in `NovaServiceProvider.php`) e già gestiscono in comune Quote/Customer. Developer, Editor e Customer restano scoped a `forUser()` esattamente come oggi — nessuna nuova esposizione per loro.
- **Filtro senza opzione "Non assegnato"**: `quotes.user_id` è nullable, quindi esistono (o possono esistere) Task con `assignee` null. Il filtro Assegnatario, le cui opzioni derivano da `User::whereHas('quotes.tasks')`, non permette di isolare questi task orfani — restano visibili solo nella vista non filtrata. Accettato per questo ciclo: nessuna mitigazione ora, da rivalutare se in produzione emergono in numero significativo quote senza owner con task collegati.
- **Filtro include utenti storici/disattivati**: `whereHas('quotes.tasks')` non verifica lo stato attivo dell'utente — un ex-dipendente con una quote storica e un task collegato resta nella tendina indefinitamente. Accettato, nessun controllo di stato attivo esiste già altrove nel progetto per liste analoghe.
- **Colonna Scadenza mostra solo la data ma il badge di urgenza (`urgencyBadgeKey`) confronta ancora l'orario**: `due_date` resta un `datetime` a DB e i confronti in `urgencyBadgeKey()`/`urgencyBadgeLabel()` non cambiano. Per i record storici con orari non a mezzanotte, il badge (Overdue/Due today/Upcoming) può risultare meno intuitivo rispetto alla sola data mostrata in colonna, senza indicazione visibile del perché. Accettato come rischio noto e transitorio: i nuovi task creati/modificati con `Date::make` avranno sempre orario troncato a mezzanotte.
- **Perdita di dati minore e non reversibile su `due_date`**: una volta che un task viene salvato tramite il form con `Date::make`, l'orario originale (se diverso da mezzanotte) viene troncato e non è più recuperabile — un eventuale rollback futuro a `DateTime::make` non ripristina gli orari persi nel frattempo. Nessuna migrazione prevista; accettato come effetto collaterale minore.
- **Filtro non testuale**: la ricerca full-text di Nova (`$search`) non include l'assegnatario — resta scelta esplicita, il filtro select è l'unico meccanismo per isolare per persona (vedi Out of scope).

## Out of scope
Ricerca testuale per assegnatario (solo filtro). Ordinamento/filtro nativo per Cliente (resta HTML computed, non BelongsTo; nessuna colonna denormalizzata `customer_id` su `tasks`). Qualsiasi modifica al sub-panel Task dentro il dettaglio Quote (resta invariato, incluso lo scoping). Migrazione della colonna `due_date` da datetime a date puro (resta `datetime` a livello DB, solo troncata a mezzanotte via `Date::make`). Introduzione di una `TaskPolicy` formale. Opzione "Non assegnato" nel filtro Assegnatario. Filtro/esclusione di utenti storici/disattivati dalle opzioni del filtro. Estensione della vista globale (non scoped) a ruoli diversi da Admin/Manager.

## Moduli toccati
- `app/Nova/Task.php` — riordino `fields()`, `DateTime::make` → `Date::make` per Scadenza, nuovo campo Assegnatario, nuovo filtro in `filters()`, branching per ruolo (Admin/Manager) in `indexQuery()`
- `app/Nova/Filters/TaskAssigneeFilter.php` (nuovo) — filtro Select per assegnatario, opzioni dinamiche da `User::whereHas('quotes.tasks')`
- `lang/it.json`, `lang/en.json` — riuso chiave `Assignee` esistente; eventuali nuove chiavi per il filtro (es. nome del filtro se diverso dal campo)
