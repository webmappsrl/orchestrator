> Ticket: oc:8407

# Tab nel dettaglio Quote (Principale/Task/Prodotti/Recurring)

## Cosa cambia

Il dettaglio Nova di una Quote (Resources → Quotes → {id}) viene riorganizzato in 4 tab usando il **Tab nativo di Nova 4** (`Laravel\Nova\Tabs\Tab` / `Tab::group()`), lo stesso pattern già in uso in `app/Nova/Customer.php` (unico altro precedente nel progetto). Non si tratta del package `eminiarts/nova-tabs` (installato in composer ma non usato direttamente per il layout — è una dipendenza transitiva legata a `NovaTabTranslatable`).

- **Tab 1 "Main"** (Principale) — tutti i campi identificativi/commerciali/testuali oggi presenti in `fields()`, tranne Prodotti/Recurring (spostati nei tab dedicati). Ordine campi:
  ID, Titolo, Creato il, Stato (tutte e tre le varianti esistenti: Text onlyOnDetail, Status onlyOnIndex, Select onlyOnForms), Cliente, Google Drive Url, Template, Proprietario, Totale, Sconto, Additional Services Total Price, IVA, Final Price, Servizi aggiuntivi, Dettagli dei servizi aggiuntivi, PDF, Contenuti aggiuntivi, Informazioni aggiuntive, Tempo di consegna, Piano di pagamento, Piano di fatturazione, Documents, Note.
- **Tab 2 "Task"** — `HasMany::make(__('Tasks'), 'tasks', \App\Nova\Task::class)` spostato così com'è, nessuna modifica a scoping/comportamento del sub-panel (`indexQuery` di `App\Nova\Task` continua a bypassare lo scoping quando `viaResource === 'quotes'`, invariato).
- **Tab 3 "Prodotti"** — `BelongsToMany::make('Products')` (con campo pivot `quantity`) + `Currency::make('Products')` (importo totale). Entrambi visibili, nessuno nascosto.
- **Tab 4 "Recurring Products"** — `BelongsToMany::make('Recurring Products')` (con campo pivot `quantity`) + `Currency::make('Recurring', 'recurring')` (importo totale).

Il gruppo tab usa `->withToolbar()`, per coerenza visiva con `Customer.php` (unico altro precedente nel progetto con più tab).

Nova non supporta nativamente un "tab attivo di default" diverso dal primo — il tab "Main" sarà sempre quello aperto all'apertura della pagina, anche se "Task" è concettualmente il più importante operativamente. Questo è un limite noto di Nova, non risolvibile senza JS custom (fuori scope).

Wrapping in `Tab::group()`/`Tab::make()` **non altera la visibilità dei campi sull'index** (`hideFromIndex`/`onlyOnIndex`/`sortable` restano invariati) — solo la vista Detail/Create/Update viene riorganizzata in tab. Confermato leggendo `vendor/laravel/nova/src/Tabs/TabsGroup.php`: il wrapping aggiunge solo metadata `tab` al field, non tocca le regole di visibilità esistenti.

Nuova chiave di traduzione `"Quote Details"` → `"Dettagli Preventivo"` (it) / `"Quote Details"` (en, invariato) per il titolo del gruppo tab. Le altre etichette (`Main`, `Task`/`Tasks`) esistono già in entrambi i file. `"Products"`/`"Recurring Products"` esistono già in `lang/it.json` ma **non in `lang/en.json`** — nessuna azione necessaria: il fallback di Laravel restituisce la chiave stessa, che coincide già col testo inglese desiderato.

## Perché

Il dettaglio Quote ha accumulato troppi campi in un unico pannello (21+ campi in un'unica vista), rendendo la pagina lunga e poco navigabile — confermato empiricamente sulla Quote 212 citata dal cliente (9 prodotti, 3 recurring products, 1 task collegato). Raggrupparli in tab tematici (con Task come secondo tab, data la sua importanza operativa per i follow-up commerciali, vedi oc:8327/oc:8403) rende la pagina più leggibile senza rimuovere alcuna informazione.

## Requisiti

- [ ] `fields()` avvolto in `Tab::group(__('Quote Details'), [...])->withToolbar()` con 4 `Tab::make()`: "Main" (Principale), "Task", "Products" (Prodotti), "Recurring Products"
- [ ] Tab Main: ordine campi come da sezione "Cosa cambia" sopra, incluso l'ID e tutte e tre le varianti del campo Stato
- [ ] Tab Task: `HasMany::make(__('Tasks'), 'tasks', \App\Nova\Task::class)` spostato invariato — nessuna modifica a scoping/comportamento del sub-panel
- [ ] Tab Prodotti: `BelongsToMany::make('Products')` + `Currency::make('Products')` (lista + importo, nessuna delle due nascosta)
- [ ] Tab Recurring Products: `BelongsToMany::make('Recurring Products')` + `Currency::make('Recurring', 'recurring')` (lista + importo)
- [ ] Prodotti/Ricorrente NON compaiono nel Tab Main (nessuna duplicazione)
- [ ] Nuova chiave `"Quote Details"` → `"Dettagli Preventivo"` in `lang/it.json`; `"Quote Details"` → `"Quote Details"` in `lang/en.json`
- [ ] Nessuna modifica alla visibilità dei campi sull'index (`hideFromIndex`/`onlyOnIndex`/`sortable` invariati)
- [ ] `->withToolbar()` sul gruppo tab, per coerenza con `Customer.php`
- [ ] Test manuale immediato dopo la prima scrittura della struttura tab (prima di completare il resto del riordino): Create + Update di una Quote con focus su editabilità di Title e dei campi Tiptap/MarkdownTui dentro `NovaTabTranslatable`; apertura di una Quote da Customer → tab Quotes (verifica rendering `QuoteNoFilter`); comportamento del modale Attach su Products/Recurring (tab attivo dopo il ritorno)

## Rischi

- **`NovaTabTranslatable` dentro `Tab::group` non è inedito quanto inizialmente stimato**: `app/Nova/App.php` (righe 235/449, `home_tab()`) usa già `Tab::group(...)->withToolbar()` con `NovaTabTranslatable::make([Tiptap::make(...)])` annidato in un tab, in produzione — precedente diretto non individuato dalla prima analisi (che aveva controllato solo `Customer.php`, dove questi campi restano fuori dal gruppo tab in un `Panel` separato). Rischio ridotto ma non azzerato: `App.php` testa un Tiptap generico, non il campo `Title` (che in Quote è anche il valore usato in `title()` — criticità specifica non coperta dal precedente). Mantenuto comunque il test manuale di Create/Update (Title + campi Tiptap) subito dopo aver scritto la struttura tab, come rete di sicurezza a basso costo.
- **`App\Nova\QuoteNoFilter` (usata da `Customer.php` per il tab "Quotes") eredita `fields()` senza override**: aprendo una Quote da lì si otterrebbe lo stesso layout a 4 tab, potenzialmente annidato nel modale relazione di Customer — combinazione mai esercitata nel codebase. Da verificare manualmente nella stessa sessione di test sopra; se il rendering risulta rotto, valutare in quel momento un override minimo di `fields()` in `QuoteNoFilter`.
- **Bug preesistente di scoping Task esposto più visibilmente**: `Task::indexQuery()` bypassa lo scoping "solo i miei/creati da me" solo quando `viaResource === 'quotes'`, ma l'uriKey di `QuoteNoFilter` è `quote-no-filters` — quindi il sub-panel Task raggiunto da Customer → tab Quotes non beneficia del bypass (oc:8327/oc:8403), mostrando potenzialmente una lista Task incompleta. Bug non introdotto da questo ticket, ma reso più probabile da scoprire (Task ora è un tab di primo piano). **Deciso: fuori scope, da valutare in un ciclo successivo** — registrato come follow-up in `notes.md`.
- **Verificato**: "Allega Prodotto"/"Allega Recurring" non è un modale ma una pagina dedicata (`/attach/products`); tornando indietro ("Annulla" o dopo il salvataggio) si atterra sempre sul tab "Main", non sul tab di provenienza ("Products"/"Recurring Products") — comportamento standard di Nova (non specifico di questa modifica: prima dei tab non esisteva alcuno stato "tab attivo" da perdere). Minore, richiede un click in più per tornare al tab giusto — accettato, nessuna azione correttiva.
- **Creazione Quote può restare silenziosamente senza Prodotti/Recurring**: `BelongsToMany` non è `required`; con i tab, un utente può salvare dal tab "Main" senza mai aprire "Prodotti"/"Recurring". Comportamento identico a oggi (nessun obbligo esiste già ora) — i tab lo rendono solo più "nascosto" visivamente. Accettato consapevolmente, nessuna validazione bloccante aggiunta (fuori scope, introdurrebbe una regola di business non richiesta).
- **Nova non supporta un tab di default diverso dal primo**: "Task", pur essendo il secondo per posizione, non sarà mai il tab aperto di default. Accettato — nessuna mitigazione nel ciclo attuale (richiederebbe JS custom fuori scope).
- **21 campi nel tab Main restano comunque numerosi**: i tab risolvono la profusione di *pannelli separati*, non necessariamente la lunghezza dello scroll interno al singolo tab Main. Non è un blocker rispetto al requisito del cliente, ma non risolve interamente la percezione di "troppi dati".
- **Spostare Prodotti/Ricorrente fuori dal Main** è la scelta col rischio UX più alto (segnalato da parere `ui-ux-pro-max`): sono spesso il primo motivo per cui si apre una quote. Mitigazione: Totale/Sconto/Final Price restano ben visibili nel Main, così il dato aggregato non richiede comunque il click sul tab dedicato.
- **Riordino a mano dell'intero array `fields()` su un file toccato spesso** (oc:8286/oc:8291/oc:8330 in `CLAUDE.md`): un rollback secco (`git revert`) rischia conflitti di merge non banali con lavoro concorrente su `Quote.php`. Nessun rollout incrementale/feature flag previsto — è un cambio tutto-o-niente su un singolo file ad alto traffico. Accettato, coerente con la natura del ticket (riorganizzazione dichiarativa, non logica di business).
- **Nessun impatto su test automatici**: nessun test PHPUnit esistente copre la configurazione dei campi Nova; verifica solo manuale (concordato con l'utente).
- **Due selettori lingua indipendenti invece di uno** (emerso in review, `wm-review-ticket`): lo split del blocco `NovaTabTranslatable` (vedi "Cosa cambia") separa lo switcher lingua dei 4 campi Tiptap da quello del campo Note. Un traduttore che cambia lingua su un blocco deve ricordarsi di farlo anche sull'altro. Accettato consapevolmente dallo sviluppatore: il rischio è minore rispetto al beneficio di rispettare l'ordine campi letterale richiesto dal ticket (Documents prima di Note).

## Out of scope

- Modifiche all'indice/lista Quotes (coperto dal ticket precedente, oc:8404).
- Modifiche al comportamento/scoping del sub-panel Task dentro il tab (solo spostamento visuale, `indexQuery` di `App\Nova\Task` non toccato).
- Badge/contatore sul Tab Task per numero di task aperti (non richiesto, valutato e scartato in fase di reverse-interaction).
- Riorganizzazione in tab di altre Nova Resource (es. `App\Nova\App`, `Story`) — questo ticket riguarda esclusivamente `App\Nova\Quote`.
- Test automatici sulla struttura dei campi Nova — verifica manuale concordata con l'utente.

## Moduli toccati

- `app/Nova/Quote.php` (repo `orchestrator`) — wrap `fields()` in `Tab::group()`/`Tab::make()`, riordino campi Tab Main.
- `lang/it.json`, `lang/en.json` (repo `orchestrator`) — nuova chiave di traduzione `"Quote Details"`.
