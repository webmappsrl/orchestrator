> Ticket: oc:8407

# Tab nel dettaglio Quote (Principale/Task/Prodotti/Recurring) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **⚠️ Nessun commit o branch automatico.** I commit indicati in ogni task sono istruzioni testuali per lo sviluppatore, non azioni da eseguire autonomamente. Non eseguire `git commit`, `git add` o `git push` per nessun motivo durante l'esecuzione di questo piano.

**Goal:** Riorganizzare il dettaglio Nova della Quote (`app/Nova/Quote.php`) in 4 tab (Main, Task, Products, Recurring Products) usando il Tab nativo di Nova 4, senza alterare la visibilità dei campi sull'index né lo scoping del sub-panel Task.

**Architecture:** Un solo metodo (`fields()`) di un solo file PHP viene riscritto: i field object esistenti vengono spostati (non ricreati) dentro `Tab::group(__('Quote Details'), [...])->withToolbar()`, con 4 `Tab::make()`. Nessuna nuova classe, nessuna migration, nessun nuovo endpoint.

**Tech Stack:** Laravel Nova 4 (Tab nativo `Laravel\Nova\Tabs\Tab`), `Kongulov\NovaTabTranslatable`, PHP 8.1.

**Spec:** `docs/features/8407-tab-nel-dettaglio-quote/overview.md`

## Global Constraints

- Nessuna modifica alla visibilità dei campi sull'index (`hideFromIndex`/`onlyOnIndex`/`sortable` invariati) — i Tab Nova nativi non toccano la visibilità index.
- Prodotti/Recurring Products NON devono comparire nel Tab Main (nessuna duplicazione).
- Tab Task: `HasMany::make(__('Tasks'), 'tasks', \App\Nova\Task::class)` spostato invariato — nessuna modifica a scoping/comportamento del sub-panel.
- Fuori scope, NON toccare in questo ticket: indice/lista Quotes (oc:8404), bug di scoping Task via `QuoteNoFilter` (registrare solo in `notes.md` → Follow-up), badge/contatore task aperti, altre Nova Resource, test automatici PHPUnit.
- Container Docker `php81_orchestrator` già attivo — nessun comando di avvio necessario.

---

### Task 1: Nuova chiave di traduzione "Quote Details"

**Files:**
- Modify: `lang/it.json`
- Modify: `lang/en.json`

**Interfaces:**
- Produce la chiave `"Quote Details"` usata da `Tab::group(__('Quote Details'), ...)` in Task 2.

- [ ] **Step 1: Aggiungi la chiave in `lang/it.json`**

Apri `lang/it.json` e aggiungi, vicino alla chiave esistente `"Customer Details": "Dettagli Cliente"` (riga 472), la nuova riga:

```json
"Quote Details": "Dettagli Preventivo",
```

Rispetta la sintassi JSON (virgola alla fine se non è l'ultima chiave del blocco).

- [ ] **Step 2: Aggiungi la chiave in `lang/en.json`**

Apri `lang/en.json` e aggiungi, vicino alla chiave esistente `"Customer Details": "Customer Details"` (riga 684), la nuova riga:

```json
"Quote Details": "Quote Details",
```

- [ ] **Step 3: Verifica sintassi JSON**

Run:
```bash
docker exec php81_orchestrator php -r "json_decode(file_get_contents('lang/it.json'), true) !== null ? print('it.json OK'.PHP_EOL) : print('it.json INVALID'.PHP_EOL);"
docker exec php81_orchestrator php -r "json_decode(file_get_contents('lang/en.json'), true) !== null ? print('en.json OK'.PHP_EOL) : print('en.json INVALID'.PHP_EOL);"
```
Expected: `it.json OK` e `en.json OK`.

- [ ] **Step 4: Commit (testuale — non eseguire automaticamente)**

```bash
git add lang/it.json lang/en.json
git commit -m "feat(oc:8407): add Quote Details tab group translation key"
```

---

### Task 2: Riorganizzare `Quote::fields()` in 4 tab

**Files:**
- Modify: `app/Nova/Quote.php:1-31` (import) e `app/Nova/Quote.php:75-281` (metodo `fields()`)

**Interfaces:**
- Consuma: la chiave `"Quote Details"` prodotta in Task 1.
- Produce: nessuna nuova interfaccia pubblica — stesso metodo `fields(NovaRequest $request): array`, stessa firma, stesso comportamento verso il resto della classe (`cards()`, `filters()`, `actions()`, `indexQuery()` non toccati).

**Precedente diretto verificato**: `app/Nova/App.php:235,449` usa già `Tab::group(...)->withToolbar()` con `NovaTabTranslatable::make([Tiptap::make(...)])` annidato in un `Tab::make()` — la combinazione Tab nativo + `NovaTabTranslatable` è già in produzione, riduce (ma non azzera — vedi Task 3) il rischio sul campo `Title`.

- [ ] **Step 1: Aggiungi l'import di `Tab`**

In `app/Nova/Quote.php`, dopo la riga `use Laravel\Nova\Panel;` (riga 5), aggiungi:

```php
use Laravel\Nova\Tabs\Tab;
```

- [ ] **Step 2: Sostituisci il `return [...]` di `fields()` con la struttura a tab**

Sostituisci integralmente il blocco `return [ ... ];` di `fields()` (righe 110-280 del file attuale, cioè da `ID::make()->sortable(),` fino a `Files::make(__('Documents'), 'documents')->hideFromIndex(),` incluso) con:

```php
        return [
            Tab::group(__('Quote Details'), [
                Tab::make(__('Main'), [
                    ID::make()->sortable(),
                    NovaTabTranslatable::make([
                        Text::make(__('Title'), 'title')
                            ->displayUsing(function ($name, $a, $b) {
                                $wrappedName = wordwrap($name, 50, "\n", true);
                                $htmlName = str_replace("\n", '<br>', $wrappedName);
                                return $htmlName;
                            })
                            ->asHtml(),
                    ])->setTitle(__('Title')),
                    DateTime::make(__('Created At'), 'created_at')
                        ->displayUsing(function ($date) {
                            return $date ? $date->format('d/m/Y H:i') : null;
                        })
                        ->onlyOnDetail()
                        ->sortable(),
                    Text::make(__('Status'), 'status')
                        ->displayUsing(function () {
                            $status = QuoteStatus::tryFrom($this->status);
                            return $status ? $status->label() : (string) $this->status;
                        })
                        ->onlyOnDetail(),
                    Status::make('Status')
                        ->loadingWhen([
                            QuoteStatus::New->value,
                            QuoteStatus::To_Present->value,
                            QuoteStatus::Presented->value,
                            QuoteStatus::Waiting_For_Order->value,
                            QuoteStatus::Cold->value
                        ])
                        ->failedWhen([
                            QuoteStatus::Closed_Lost->value,
                        ])
                        ->displayUsing(function () {
                            $status = QuoteStatus::tryFrom($this->status);
                            return $status ? $status->label() : (string) $this->status;
                        })
                        ->onlyOnIndex(),
                    Select::make('Status')->options(
                        collect(QuoteStatus::cases())->mapWithKeys(function (QuoteStatus $status) {
                            return [$status->value => $status->label()];
                        })->toArray()
                    )
                        ->onlyOnForms()
                        ->default(QuoteStatus::New->value),
                    BelongsTo::make(__('Customer'), 'customer', 'App\nova\Customer')
                        ->filterable()
                        ->searchable(),
                    Text::make('Google Drive Url', 'google_drive_url')->nullable()->hideFromIndex()->displayUsing(function () {
                        return '<a class="link-default" target="_blank" href="' . $this->google_drive_url . '">' . $this->google_drive_url . '</a>';
                    })->asHtml(),
                    Boolean::make(__('Template'), 'template')
                        ->help(__('Only one template per customer. If you enable this, it becomes the current template and the previous one will be automatically disabled.'))
                        ->hideFromIndex(),
                    BelongsTo::make(__('Owner'), 'user', 'App\nova\User')
                        ->searchable()
                        ->filterable()
                        ->nullable(),
                    Currency::make(__('Total'), 'total')
                        ->currency('EUR')
                        ->locale('it')
                        ->exceptOnForms()
                        ->displayUsing(function () {
                            $quotePrice = $this->getTotalPrice() + $this->getTotalRecurringPrice() + $this->getTotalAdditionalServicesPrice();
                            return number_format($quotePrice, 2, ',', '.') . ' €';
                        })->sortable(),
                    Currency::make(__('Discount'), 'discount')
                        ->currency('EUR')
                        ->locale('it')
                        ->hideFromIndex()
                        ->displayUsing(function () {
                            return number_format($this->discount, 2, ',', '.') . ' €';
                        }),
                    Currency::make('Additional Services Total Price')
                        ->currency('EUR')
                        ->locale('it')
                        ->onlyonDetail()
                        ->displayUsing(function () {
                            return number_format($this->getTotalAdditionalServicesPrice(), 2, ',', '.') . ' €';
                        }),
                    Currency::make('IVA')
                        ->currency('EUR')
                        ->locale('it')
                        ->onlyonDetail()
                        ->displayUsing(function () {
                            $iva = $this->getQuoteNetPrice() * \App\Models\Quote::VAT_RATE;
                            return number_format($iva, 2, ',', '.') . ' €';
                        }),
                    Currency::make('Final Price')
                        ->currency('EUR')
                        ->locale('it')
                        ->onlyonDetail()
                        ->displayUsing(function () {
                            $iva = $this->getQuoteNetPrice() * \App\Models\Quote::VAT_RATE;
                            return number_format($this->getQuoteNetPrice() + $iva, 2, ',', '.') . ' €';
                        }),
                    NovaTabTranslatable::make([
                        KeyValue::make(__('Additional Services'), 'additional_services')
                            ->hideFromIndex()
                            ->keyLabel(__('Description'))
                            ->valueLabel(__('Price') . '(€)')
                            ->hideFromIndex(),
                    ])->hideFromIndex(),
                    Text::make('PDF')
                        ->resolveUsing(function ($value, $resource, $attribute) {
                            $itaUrl = route('quote', ['id' => $resource->id]);
                            $enUrl = route('quote', ['id' => $resource->id, 'lang' => 'en']);

                            return $this->pdfButton($itaUrl, 'ITA') . $this->pdfButton($enUrl, 'EN');
                        })
                        ->asHtml()
                        ->exceptOnForms(),
                    NovaTabTranslatable::make([
                        Tiptap::make(__('Additional Info'), 'additional_info')
                            ->hideFromIndex()
                            ->buttons($allButtons),

                        Tiptap::make(__('Delivery Time'), 'delivery_time')
                            ->hideFromIndex()
                            ->buttons($allButtons),

                        Tiptap::make(__('Payment Plan'), 'payment_plan')
                            ->hideFromIndex()
                            ->buttons($allButtons),

                        Tiptap::make(__('Billing Plan'), 'billing_plan')
                            ->hideFromIndex()
                            ->buttons($allButtons),
                    ])->hideFromIndex(),
                    Files::make(__('Documents'), 'documents')
                        ->hideFromIndex(),
                    NovaTabTranslatable::make([
                        MarkdownTui::make(__('Notes'), 'notes')
                            ->hideFromIndex()
                            ->initialEditType(EditorType::MARKDOWN)
                            ->nullable(),
                    ])->hideFromIndex(),
                ]),
                Tab::make(__('Task'), [
                    HasMany::make(__('Tasks'), 'tasks', \App\Nova\Task::class),
                ]),
                Tab::make(__('Products'), [
                    BelongsToMany::make(__('Products'), 'products', 'App\nova\Product')->fields(function () {
                        return [
                            Number::make(__('Quantity'), 'quantity')->rules('required', 'numeric', 'min:1')
                                ->default(1)
                        ];
                    })
                        ->searchable(),
                    Currency::make(__('Products'))
                        ->currency('EUR')
                        ->locale('it')
                        ->exceptOnForms()
                        ->displayUsing(function () {
                            $price = empty($this->products) ? 0 : $this->getTotalPrice();
                            return number_format($price, 2, ',', '.') . ' €';
                        })->sortable(),
                ]),
                Tab::make(__('Recurring Products'), [
                    BelongsToMany::make('Recurring Products')->fields(function () {
                        return [
                            Number::make(__('Quantity'), 'quantity')->rules('required', 'numeric', 'min:1')
                                ->default(1)
                        ];
                    })
                        ->searchable(),
                    Currency::make(__('Recurring'), 'recurring')
                        ->currency('EUR')
                        ->locale('it')
                        ->exceptOnForms()
                        ->displayUsing(function () {
                            $price = empty($this->recurringProducts) ? 0 : $this->getTotalRecurringPrice();
                            return number_format($price, 2, ',', '.') . ' €';
                        })->sortable(),
                ]),
            ])->withToolbar(),
        ];
```

**Nota implementativa**: il campo `Notes` (`MarkdownTui`) è stato spostato in un secondo `NovaTabTranslatable::make([...])` separato da quello di `additional_info`/`delivery_time`/`payment_plan`/`billing_plan`, per rispettare l'ordine richiesto `..., Piano di fatturazione, Documents, Note` (nel codice originale erano tutti nello stesso blocco `NovaTabTranslatable`, che avrebbe reso impossibile inserire `Documents` in mezzo). Comportamento funzionale identico, nessuna logica cambiata.

**Nota implementativa 2**: la frase del ticket "Dettagli dei servizi aggiuntivi" e "Contenuti aggiuntivi" nell'elenco campi non corrispondono a field object distinti nel codice attuale — sono rispettivamente lo stesso campo di "Servizi aggiuntivi" (`additional_services`) e la stessa etichetta italiana di "Informazioni aggiuntive" (`additional_info`, tradotto `"Additional Info": "Informazioni aggiuntive"` in `lang/it.json:372`). Non creare field duplicati per questi due termini.

- [ ] **Step 3: Verifica sintassi PHP**

Run:
```bash
docker exec php81_orchestrator php -l app/Nova/Quote.php
```
Expected: `No syntax errors detected in app/Nova/Quote.php`

- [ ] **Step 4: Pulisci la cache di configurazione Nova**

Run:
```bash
docker exec php81_orchestrator php artisan config:clear
```

- [ ] **Step 5: Commit (testuale — non eseguire automaticamente)**

```bash
git add app/Nova/Quote.php
git commit -m "feat(oc:8407): reorganize Quote detail into Main/Task/Products/Recurring Products tabs"
```

---

### Task 3: Verifica manuale critica (da eseguire subito, prima di considerare il lavoro concluso)

**Files:** nessuna modifica di codice in questo task — solo verifica manuale in browser sull'ambiente Docker locale già attivo.

**Interfaces:** nessuna — task di verifica.

- [ ] **Step 1: Apri Nova in locale e naviga a una nuova Quote (Create)**

Apri il browser sull'URL Nova locale (porta da `.env` → `DOCKER_SERVE_PORT`, es. `http://localhost:8099/nova`... verifica la porta esatta con `grep DOCKER_SERVE_PORT .env`), vai su Resources → Quotes → Create.

Verifica: il tab "Main" è aperto di default; il campo Title (dentro `NovaTabTranslatable`) è visibile e editabile; compila Title + Cliente, salva. Expected: la Quote viene creata senza errori, il Title salvato è visibile nel dettaglio.

Se il campo Title NON è editabile o il salvataggio fallisce con un errore relativo al componente tab-translatable → **STOP, applica il fallback descritto in Step 4 sotto prima di proseguire**.

- [ ] **Step 2: Apri la Quote 212 (Update) e verifica i campi Tiptap**

Naviga a Resources → Quotes → 212 (Update). Nel tab "Main", verifica che i campi Tiptap (Contenuti aggiuntivi/Informazioni aggiuntive, Tempo di consegna, Piano di pagamento, Piano di fatturazione) siano editabili e che il campo Note (nel secondo blocco `NovaTabTranslatable`) sia editabile.

Expected: tutti i campi si aprono nell'editor Tiptap/Markdown correttamente, modifiche salvabili.

- [ ] **Step 3: Verifica rendering da Customer → tab Quotes (QuoteNoFilter)**

Apri un Customer con almeno una Quote associata (es. il cliente della Quote 212), vai al tab "Quotes" del dettaglio Customer, apri la Quote da lì (via `App\Nova\QuoteNoFilter`, che eredita `fields()` da `Quote` senza override).

Expected: il layout a 4 tab si apre correttamente, senza tab annidati rotti o errori JS in console. Se il rendering è visibilmente rotto (tab non renderizzati, errore console), documentalo in `notes.md` — non fixarlo qui (fuori scope), ma segnala all'utente prima di proseguire.

- [ ] **Step 4 (SOLO SE Step 1 o Step 2 falliscono in modo bloccante): fallback — Title fuori dal Tab::group**

Se il campo Title o un campo Tiptap risulta non editabile/non salvabile a causa del contesto tab, applica questo fallback (mirror esatto del pattern di `Customer.php`, che tiene questi campi fuori da `Tab::group` in un `Panel` separato):

Sposta il campo `NovaTabTranslatable::make([Text::make(__('Title'), ...)])->setTitle(__('Title'))` (e, se necessario, gli altri blocchi `NovaTabTranslatable` che falliscono) **fuori** dall'array passato a `Tab::group()`, in un `new Panel(__('Translations'), [...])` posizionato subito dopo la chiusura di `Tab::group(...)->withToolbar(),` nell'array di ritorno di `fields()`:

```php
        return [
            Tab::group(__('Quote Details'), [
                // ... tab Main (senza i blocchi NovaTabTranslatable falliti), Task, Products, Recurring Products
            ])->withToolbar(),
            new Panel(__('Translations'), [
                NovaTabTranslatable::make([
                    Text::make(__('Title'), 'title')
                        ->displayUsing(function ($name, $a, $b) {
                            $wrappedName = wordwrap($name, 50, "\n", true);
                            $htmlName = str_replace("\n", '<br>', $wrappedName);
                            return $htmlName;
                        })
                        ->asHtml(),
                ])->setTitle(__('Title')),
                // ... eventuali altri blocchi NovaTabTranslatable falliti
            ]),
        ];
```

Dopo aver applicato il fallback, ripeti Step 1 e Step 2. Se funziona, aggiorna `overview.md` (sezione "Cosa cambia") per riflettere la struttura ibrida effettivamente implementata, e registra la deviazione in `notes.md`.

---

### Task 4: Verifica finale complessiva e commit

**Files:** nessuna modifica di codice (a meno che Task 3 Step 4 sia stato applicato).

- [ ] **Step 1: Verifica visiva completa su Quote 212**

Apri Resources → Quotes → 212. Verifica, in ordine:
1. 4 tab visibili: Main, Task, Products, Recurring Products.
2. Tab Main: ordine campi corrisponde esattamente a quello di Task 2 Step 2 (ID, Title, Created At, Status ×3, Customer, Google Drive Url, Template, Owner, Total, Discount, Additional Services Total Price, IVA, Final Price, Additional Services, PDF, Additional Info, Delivery Time, Payment Plan, Billing Plan, Documents, Notes).
3. Tab Task: mostra il task collegato alla Quote 212 (1 task, verificato via tinker in fase di reverse-interaction).
4. Tab Products: mostra i 9 prodotti collegati + importo totale.
5. Tab Recurring Products: mostra i 3 recurring products collegati + importo totale.
6. Nessun campo Prodotti/Recurring visibile nel tab Main.

- [ ] **Step 2: Verifica che l'index Quotes sia invariato**

Apri Resources → Quotes (lista). Verifica: le colonne visibili sull'index (Title, Status badge, Total, Products currency, Recurring currency — quelle non `hideFromIndex`) sono identiche a prima della modifica, nessuna colonna sparita o aggiunta.

- [ ] **Step 3: Verifica etichette tradotte**

Verifica che il titolo del gruppo tab mostri "Dettagli Preventivo" (con `APP_LOCALE=it`) e che i 4 tab mostrino "Main", "Task", "Products", "Recurring Products" (o le rispettive traduzioni italiane già esistenti).

- [ ] **Step 4: Commit finale (testuale — non eseguire automaticamente, solo se Task 3 Step 4 è stato applicato e ha richiesto modifiche aggiuntive non già committate in Task 2)**

```bash
git add app/Nova/Quote.php
git commit -m "fix(oc:8407): move Title/NovaTabTranslatable fields outside Tab::group (fallback)"
```

Se nessuna modifica aggiuntiva è stata necessaria (Task 3 è passato senza attivare il fallback), questo step non produce alcun commit.
