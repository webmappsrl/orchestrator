> Ticket: oc:8404

# Riordino colonne e metriche lista Quotes

## Cosa cambia
Nella vista elenco Nova delle Quotes (Resources → Quotes):
- le due metric-card in cima ("Quotes by Status" e "New Quotes") passano dalla configurazione attuale (`full` + `1/2`, che le mette su due righe) a `1/2` + `1/2` sulla stessa riga (`app/Nova/Quote.php::cards()`);
- l'ordine delle colonne diventa Cliente, Titolo, Stato, Proprietario, Scadenza, Totale, PDF;
- il Titolo mostra solo la lingua attiva dell'utente (`app()->getLocale()`) invece di tutte le traduzioni (il campo è wrappato in `NovaTabTranslatable` su un modello con `Spatie\Translatable\HasTranslations` — serve forzare la risoluzione a singola lingua sull'index, la tab multilingua resta sul form);
- entrambe le colonne Prodotti e Recurring Products (sia la variante tag `BelongsToMany`, sia la variante importo `Currency`) vengono nascoste dall'index (`hideFromIndex()`), restando visibili solo nel dettaglio;
- viene aggiunta una nuova colonna Scadenza (prima di Totale, solo visualizzazione) che mostra la `due_date` del task `todo` collegato alla Quote più vicino nel tempo (scaduto o futuro), corrispondente alla richiesta letterale del cliente — vedi nota sotto.

## Perché
La vista lista Quotes ha accumulato colonne ridondanti e poco leggibili (Titolo multilingua, doppie colonne Prodotti/Ricorrente — oggi coesistono `BelongsToMany::make('Products')` e `Currency::make('Products')`, stesso label ma contenuto diverso) mentre manca un'informazione operativa chiave (la prossima scadenza task collegata). Recuperare spazio verticale in cima (metriche su una riga) e riordinare le colonne per priorità (Cliente prima di Titolo, stesso principio già applicato ai Task in oc:8402) rende la lista più immediatamente leggibile per il lavoro quotidiano.

**Nota sulla colonna Scadenza — parole esatte del cliente (`customer_request` del ticket):** "Prima della colonna Totale deve essere aggiunta una colonna Scadenza, che riporti la scadenza dell'ultimo task aperto collegato al preventivo visualizzato." Il cliente non menziona un vincolo "solo se nel futuro" — quel vincolo era stato aggiunto dalla sessione `wm-plan` precedente in fase di traduzione in requisito tecnico, non fa parte della richiesta originale. Rimosso in Fase: challenge: con il vincolo "solo futuro", un task todo scaduto (il caso più urgente, dato che `due_date` è sempre valorizzata a DB — non nullable) sparirebbe dalla colonna mostrando `—`, il contrario di quanto la colonna dovrebbe comunicare operativamente.

## Requisiti
- [ ] `cards()`: `DynamicPartitionMetric` e `NewQuotes` entrambe `->width('1/2')`, stessa riga (oggi: `full` + `1/2`)
- [ ] Ordine colonne index: Cliente, Titolo, Stato, Proprietario, Scadenza, Totale, PDF
- [ ] Titolo in index mostra solo la lingua attiva (`app()->getLocale()`), non tutte le traduzioni — implementato con un campo Nova separato `onlyOnIndex()` che legge l'accessor Spatie `$model->title` (locale-aware con fallback), NON tentando di parametrizzare `NovaTabTranslatable` (che genera field statici per-locale, non condizionabili a runtime — vedi Rischi); il blocco `NovaTabTranslatable` esistente resta invariato ma con `hideFromIndex()` aggiunto, così il form di modifica continua a mostrare/editare tutte le lingue
- [ ] `BelongsToMany::make('Products')`, `Currency::make('Products')`, `BelongsToMany::make('Recurring Products')`, `Currency::make('Recurring')` tutte con `hideFromIndex()` — restano visibili solo nel dettaglio
- [ ] Nuova colonna Scadenza (prima di Totale): `due_date` del task con `status: todo` collegato alla Quote più vicino nel tempo — `ORDER BY due_date ASC LIMIT 1` senza filtro su passato/futuro, così un task scaduto (il caso più urgente) resta visibile invece di sparire (relazione `Quote::tasks()`, già esistente da oc:8327); solo visualizzazione, non ordinabile/filtrabile; mostra `—` se nessun task todo collegato
- [ ] Colonne Stato e Proprietario restano invariate come oggi

## Rischi
- **`NovaTabTranslatable` non supporta risoluzione a singola lingua per-request**: il package genera N campi Nova statici indipendenti (uno per locale, es. `translations_title_it`, `translations_title_en`), ciascuno con visibilità fissata alla definizione — non esiste un'opzione nativa "mostra solo la lingua attiva in index". Il fix corretto è un campo Nova separato per l'index che riusa l'accessor Spatie (locale-aware, con fallback automatico), non un tentativo di parametrizzare il field esistente. Verificato leggendo il sorgente del package.
- **Tre punti di risoluzione del titolo coesistenti**: l'accessor Spatie (`Quote::title()` per il display-title della risorsa), il field `NovaTabTranslatable` (legge `translations['title'][$locale]` bypassando l'accessor, usato dal form), e il nuovo field index-only (accessor Spatie). Rischio di divergenza silenziosa su edge case (fallback `name`/`title`, wordwrap/HTML) se in futuro uno dei tre viene modificato senza aggiornare gli altri due.
- **Titolo a singola lingua sull'index**: verificare che il fix non rompa la tab multilingua sul form di modifica (che deve continuare a mostrare/editare tutte le lingue) — la modifica va isolata al nuovo campo index-only, senza toccare il blocco `NovaTabTranslatable` esistente oltre ad aggiungere `hideFromIndex()`.
- **Query per la colonna Scadenza — indice esistente non ottimale, non "assente"**: la migration `tasks` ha già un indice composito `(due_date, status)`, ma l'ordine delle colonne non serve bene il pattern di query di questa feature (filtro per `status='todo'` raggruppato per `quote_id`, poi `MIN(due_date)` per riga). Da valutare in `plan.md` se serve un indice aggiuntivo `(quote_id, status, due_date)` o se il volume dati attuale (tabella nuova, poche righe) rende il problema trascurabile per ora.
- **Rischio N+1 concreto se implementato con `displayUsing` naive**: se la colonna Scadenza chiama `$this->tasks` senza eager loading filtrato in `indexQuery()`, genera una query aggiuntiva per ogni riga della pagina indice (fino a 25 per pagina Nova standard). Il piano deve includere esplicitamente un `->with(['tasks' => fn ($q) => $q->where('status', 'todo')->orderBy('due_date')])` (o subquery equivalente) in `indexQuery()`.
- **Worst case eccezione nel `displayUsing`**: se il calcolo della Scadenza lancia un'eccezione non gestita (es. accesso a Carbon su relazione non caricata), l'intera index view di Quotes va in errore 500 per tutti gli utenti — Quotes è una risorsa commerciale core, un blocco qui ferma il lavoro quotidiano del team vendite. Il codice va scritto difensivamente (null-safe, nessuna assunzione che la relazione sia già popolata).
- Fase: challenge eseguita.

## Out of scope
Ordinamento/filtro per la nuova colonna Scadenza (solo visualizzazione). Riorganizzazione del dettaglio Quote in tab (richiesta figurava in una trascrizione più ampia — ticket separato, non fa parte di oc:8404).

## Moduli toccati
- `app/Nova/Quote.php` — riordino `fields()` per l'index, `hideFromIndex()` sulle quattro colonne Prodotti/Recurring, `hideFromIndex()` aggiunto al blocco `NovaTabTranslatable` del Titolo, nuovo campo Titolo index-only (accessor Spatie), nuova colonna Scadenza computata, eager loading filtrato in `indexQuery()`, `cards()` width `1/2`+`1/2`.
