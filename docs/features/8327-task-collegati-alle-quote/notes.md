> Ticket: oc:8327

# Notes — Task collegati alle Quote

## Deviazioni dal piano

- **Validazione bloccante rimossa**: l'overview e il piano prevedevano di bloccare la creazione di un Task se `quote.user_id` è `null`. In corso di test manuale è emerso che, avendo già deciso che chiunque può creare Task su qualsiasi Quote (nessuna restrizione di autorizzazione legata all'owner), non ha più senso vietare la creazione su Quote senza owner: si accetta semplicemente un assegnatario `null` (Task "non assegnato"). Rimosso il blocco in `Task::booted()`, aggiornato il test corrispondente (`test_can_create_task_on_quote_without_owner_with_null_assignee` sostituisce `test_cannot_create_task_on_quote_without_owner`).
- **Campo `creator_id` aggiunto (non previsto in overview/piano)**: durante il test manuale è emerso che l'azione di cambio stato doveva essere eseguibile solo da chi ha creato il Task, non da chiunque abbia accesso a Nova. Aggiunta migration `2026_08_19_123457_add_creator_id_to_tasks_table.php`, colonna nullable con FK verso `users` (`nullOnDelete`), valorizzata automaticamente in `Task::booted()` all'utente autenticato in fase di creazione.
- **Azione "Replica" rimossa, sostituita da azione esplicita**: la Nova Resource ereditava l'azione di replica di default. Sostituita con `App\Nova\Actions\ToggleTaskCompleted` (mostrata inline), autorizzata solo per `creator_id === $request->user()->id` (vedi `authorizedToRun`). `authorizedToReplicate()` impostato a `false`.
- **Campo "Completato" nascosto dalla creazione**: il toggle booleano ha senso solo su un Task esistente (in creazione un Task è sempre `todo`); aggiunto `->hideWhenCreating()`.
- **Colonna index "Assegnatario" sostituita con "Cliente"**: su richiesta esplicita del dev in fase di test — la vista globale mostra `quote.customer.full_name` (cliccabile, link al dettaglio Customer) invece dell'utente assegnatario, non previsto nell'overview iniziale.

## Bug trovati

- **Nova `Badge::map()` mappa il valore restituito dalla callback, non una chiave logica separata**: la prima implementazione passava direttamente l'etichetta tradotta (es. "Scaduto da 3 giorni") come valore, che non trovava corrispondenza nella mappa colore → eccezione "Error trying to find type [...] inside of the field's type mapping." Fix: la callback della Badge restituisce una chiave stabile (`urgencyBadgeKey()`), la label localizzata è prodotta da `->label()` separato.
- **Calcolo giorni di ritardo con segno invertito**: `now()->diffInDays($this->due_date)` su una data passata restituiva un valore che, castato a intero, produceva "Scaduto da -3 giorni". Fix: invertito l'ordine (`$this->due_date->diffInDays(now())`) per i soli Task scaduti.
- **`indexQuery()` applicava lo scoping "solo i miei task" anche al sub-panel dentro la Quote**: aprendo il dettaglio di una Quote non propria, il sub-panel Task risultava vuoto anche in presenza di Task reali, perché tutti i Task di quella Quote condividono lo stesso assegnatario (il proprietario della Quote) diverso dall'utente loggato. Fix: `indexQuery()` bypassa lo scoping quando `$request->viaResource === 'quotes'` (richiesta come relazione HasMany dal dettaglio Quote), applicandolo solo quando la Resource `Task` è la vista principale (menu CRM → Task).
- **Ordinamento `orderBy('due_date','asc')` non applicato in `indexQuery()`**: Nova applica il proprio ordinamento di default (`id DESC`) *prima* di invocare `indexQuery()`; aggiungere un `orderBy` in coda non ha effetto perché la query ha già un ordinamento esplicito su `id`. Fix: uso `reorder('due_date', 'asc')` per sovrascrivere l'ordinamento invece di accodarlo.
- **Badge urgenza confrontava data e ora esatta invece di sola data**: `urgencyBadgeKey()` usava `$this->due_date->isPast()`/`isToday()` (confronto su datetime completo), disallineato dalla semantica "sola data" già usata da `scopeOverdue`/`scopeDueToday`/`scopeUpcoming` e dall'overview (`due_date < oggi` / `= oggi` / `> oggi`, sempre a livello di giorno). Un Task con scadenza "oggi alle 11:57" mostrava "Scaduto da 0 giorni" invece di "In scadenza oggi" non appena l'orario corrente superava quello di scadenza, nella stessa giornata. Trovato durante la revisione della documentazione utente (screenshot del Task "Follow-up oggi"). Fix: confronto sempre su `now()->startOfDay()` (istanza fresca, nessuna mutazione dell'attributo `due_date` reale) sia per la classificazione sia per il calcolo dei giorni nella label. Aggiunto test di regressione dedicato.

## Decisioni

- **`Quote::title()` con fallback su `title`**: la Nova Resource `Quote` usa `$title = 'name'`, ma la maggior parte delle Quote reali ha solo `title` valorizzato (`name` è fillable morto, vedi CLAUDE.md → oc:8286). Aggiunto un metodo `title()` con fallback `$this->name ?: $this->title`, così il campo BelongsTo verso Quote nel form Task mostra sempre un'etichetta leggibile.
- **Ambiente di sviluppo locale privo di web server attivo**: il container `php81_orchestrator` espone solo PHP-FPM (porta 8099→8000 mappata ma nessun processo in ascolto di default). Per il test manuale in browser è stato avviato `php artisan serve --host=0.0.0.0 --port=8000` in background nel container — non persistente, da rilanciare ad ogni riavvio del container per test futuri.

- **`scopeForUser()` esteso a includere anche i Task creati dall'utente**: durante la revisione della documentazione utente è emerso che un Task creato su una Quote di un collega (o senza owner) non compariva mai nell'elenco personale del creatore, nemmeno a lui stesso — nessun modo di ritrovarlo per controllarne la scadenza. Modificato lo scope in `where(quote.user_id = utente) OR creator_id = utente`, così un Task resta sempre raggiungibile da chi lo ha creato, oltre che dal proprietario del Preventivo. Aggiunto test dedicato.

## Follow-up

- Nessun follow-up aperto: tutte le deviazioni emerse sono state implementate e testate manualmente dal dev nel corso della sessione.
