> Ticket: oc:8287

# Documentazione API con Scramble (OpenAPI/Swagger)

## Cosa cambia
Le API REST di Orchestrator (stories, tags, quotes, products, recurring-products, auth, `/me`, `/app/{id}/config.json`) diventano consultabili tramite una pagina di documentazione interattiva generata automaticamente, accessibile pubblicamente su una route dedicata e linkata dal menu Nova.

## Perché
Le API attuali non hanno documentazione consultabile: chi le integra (Apps, partner, sviluppatori interni) non ha un riferimento centralizzato su endpoint, parametri e formato risposte.

## Requisiti
- [ ] Installare `dedoc/scramble` e configurarlo per generare la spec OpenAPI dai controller/Form Request esistenti
- [ ] Esporre la UI di documentazione su una route pubblica (es. `/docs/api`), accessibile senza autenticazione
- [ ] Aggiungere una voce nel menu Nova (`MenuItem::externalLink`, stesso pattern usato per SCRUM/MEET in `NovaServiceProvider.php`) che punta a `/docs/api`
- [ ] Includere tutti gli endpoint attualmente esposti in `routes/api.php`, raggruppati per risorsa: Public (`/app/{id}/config.json`), Auth (`/auth/login`, `/me`), Stories, Tags, Quotes, Products, Recurring Products
- [ ] Abilitare il pulsante "Authorize" nella UI per testare le chiamate autenticate con Bearer token Sanctum, ma limitare il "Try it out" alle sole richieste **GET** — disabilitato per endpoint mutanti (POST/PATCH/DELETE) per evitare che un token trapelato diventi una console di scrittura pubblica
- [ ] Aggiungere annotazioni PHPDoc `@response` minime sui metodi controller la cui risposta non è inferibile automaticamente da Scramble (es. quelli che usano helper come `formatStory()` senza type hint di ritorno)
- [ ] Titoli, descrizioni e summary degli endpoint in inglese (convenzione standard OpenAPI/Swagger)
- [ ] Limitare la spec generata alle sole route definite in `routes/api.php` del repo principale, escludendo tutte le route `/api/*` registrate da `wm-package` (mobile app, ec/ugc, wallet, elasticsearch, export) che Scramble includerebbe di default

## Rischi
- Scramble non riesce a inferire automaticamente la struttura di risposta dei controller che non usano API Resource classes (es. `StoryController::formatStory()`) — mitigato con annotazioni `@response` mirate solo dove necessario, senza refactoring dei controller
- La route docs pubblica espone la struttura interna delle API a chiunque, incluso `/app/{id}/config.json` — accettato esplicitamente dall'utente come scelta di accesso; nessun rate limiting dedicato oltre al `throttle:api` globale già esistente
- "Try it out" con Bearer auth su una route pubblica rischierebbe di trasformare la doc in una console di scrittura se un token trapelasse — mitigato limitando "Try it out" alle sole richieste GET (vedi Requisiti)
- Le annotazioni `@response` scritte a mano possono disallinearsi silenziosamente dalla risposta reale nel tempo (nessun contract test) — rischio accettato per questo ciclo, nessuna verifica automatica pianificata
- Scramble potrebbe non inferire correttamente Form Request con regole condizionali (`sometimes`, `Rule::requiredIf`) — rischio accettato, da verificare manualmente in esecuzione sugli endpoint principali
- Rollback "sociale": se partner esterni iniziano a linkare `/docs/api` come riferimento ufficiale, disattivarla in futuro rompe le loro integrazioni senza preavviso; un futuro `composer update` di Scramble potrebbe cambiare formato/UI senza processo di verifica dedicato — rischio accettato, nessuna deprecation policy in questo ciclo

## Out of scope
- Creazione di API Resource classes per formalizzare le risposte (refactoring dei controller esistenti)
- Versionamento della API (`/v1`, `/v2`) o breaking change agli endpoint esistenti
- Restrizione di accesso alla route docs (basic auth, ambiente-specific) — la doc resta pubblica per scelta esplicita

## Moduli toccati
- `composer.json` / `composer.lock` — nuova dipendenza `dedoc/scramble`
- `config/scramble.php` — configurazione pubblicata (route path, grouping, security scheme Bearer)
- `app/Providers/NovaServiceProvider.php` — nuova voce menu con link a `/docs/api`
- `app/Http/Controllers/Api/*.php` — annotazioni PHPDoc `@response` dove necessario (Story, Tag, Quote, Product, RecurringProduct, Auth)
- `routes/api.php` — nessuna modifica strutturale, solo lettura per la generazione della spec
