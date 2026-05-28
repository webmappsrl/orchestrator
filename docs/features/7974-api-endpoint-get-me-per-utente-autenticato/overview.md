> Ticket: oc:7974

# API endpoint GET /me per utente autenticato

## Cosa cambia
Viene aggiunto un endpoint `GET /api/me` che restituisce `id`, `name` ed `email` dell'utente autenticato tramite token Sanctum.

## Perché
Le applicazioni client che usano l'API hanno bisogno di identificare l'utente corrente dopo il login senza dover fare assunzioni sul token o chiamare endpoint di terze parti.

## Requisiti
- [ ] `GET /api/me` accessibile solo con guard `auth:sanctum`
- [ ] Risposta JSON pura: `{ "id": ..., "name": "...", "email": "..." }`
- [ ] Implementato come closure inline in `routes/api.php`, nello stesso gruppo `auth:sanctum` delle route stories
- [ ] Nessun wrapper, nessun prefisso aggiuntivo — coerente con le route esistenti

## Rischi
Nessun rischio rilevante: l'endpoint è in sola lettura, protetto da Sanctum, e restituisce solo dati già in possesso dell'utente autenticato.

## Out of scope
- Campi aggiuntivi (ruoli, permessi, token info)
- Versioning API

## Moduli toccati
- `routes/api.php` — aggiunta closure nel gruppo `auth:sanctum`
- `tests/Feature/Api/MeEndpointTest.php` — test minimo: 200 con id/name/email per utente autenticato, 401 per utente non autenticato
