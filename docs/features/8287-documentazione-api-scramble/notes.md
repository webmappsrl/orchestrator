> Ticket: oc:8287

# Notes — Documentazione API con Scramble (OpenAPI/Swagger)

## Deviazioni dal piano
- Il piano (Task 2) presupponeva che `security_strategy` fosse già abilitato: in realtà `config/scramble.php` lo pubblica disabilitato (`null`) di default. È stato necessario abilitare `MiddlewareAuthSecurityStrategy` esplicitamente perché le operazioni GET avessero un requisito di sicurezza Bearer da poter poi rimuovere selettivamente sulle mutanti.
- I test di Task 2 sono stati corretti in corso d'opera: l'assunzione iniziale (le operazioni GET espongono esplicitamente `security` nel JSON) era sbagliata — Scramble imposta la security **a livello di documento** (`$spec.security`) e le operazioni la ereditano senza ripetere la chiave, tranne quelle esplicitamente escluse. Il test ora verifica l'assenza della chiave `security` sull'operazione GET e la presenza della security globale.

## Bug trovati
- **Scoperta non prevista in overview/challenge**: Scramble documenta di default **tutta** la superficie `/api/*`, incluse decine di route registrate da `wm-package` (mobile app v1/v2/v3, ec/poi, ec/track, ugc, wallet, elasticsearch, search, export) — non solo le route di `routes/api.php` del repo principale. Segnalato all'utente durante l'esecuzione (Task 6); l'utente ha scelto di limitare la doc alle sole route Orchestrator tramite `Scramble::routes()` con un filtro su namespace controller (`App\Http\Controllers\Api\*`) + whitelist esplicita per `AppController::config` e la closure `/me`. Requisito aggiunto a `overview.md` a posteriori.

## Decisioni
- Filtro route implementato per action-name (`App\Http\Controllers\Api\*`) invece che per prefisso URI, perché `wm-package` registra anche proprie route `auth/*` (login/logout/refresh/signup/user/delete) che collidono per prefisso con la nostra `POST /auth/login` — solo il controller distingue in modo affidabile le due famiglie di route.
- Il pulsante "Authorize" con Bearer resta abilitato per tutte le operazioni GET (tramite `security_strategy` globale); le operazioni mutanti (POST/PATCH/DELETE) hanno `security: []` esplicito via document transformer in `AppServiceProvider::boot()` — verificato con `curl`/test che il "Try it out" non propone l'header Authorization su di esse.

## Follow-up
- Nessun contract test automatico verifica che le annotazioni PHPDoc `@response` restino allineate alla risposta reale nel tempo — rischio accettato in Fase: challenge, da monitorare in cicli futuri se emergono disallineamenti.
- La route `/me` (closure inline in `routes/api.php`) resta senza un tag/gruppo nella UI — comportamento noto di Scramble per le route non basate su controller, non un difetto.
