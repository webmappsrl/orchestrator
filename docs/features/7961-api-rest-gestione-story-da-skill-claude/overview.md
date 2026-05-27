> Ticket: oc:7961

# API REST per gestione Story da skill Claude

## Cosa cambia
Orchestrator espone un set di endpoint REST autenticati che permettono alle skill
Claude di leggere, creare e aggiornare Story senza intervento manuale del developer.

## Perché
Le skill Claude (wm-plan) richiedono al developer di copiare/incollare manualmente
il contenuto dei ticket. Con questi endpoint la skill può interagire direttamente
con Orchestrator: leggere il ticket in Fase 0, crearne uno nuovo se assente, e
aggiornarlo durante il workflow (es. aggiungere note di sviluppo, cambiare stato)
— tutto tracciato per autore grazie al token personale Sanctum.

## Requisiti
- [ ] Endpoint `POST /api/auth/login` — prende email + password, restituisce token Sanctum
- [ ] Endpoint `GET /api/stories/{id}` — restituisce tutti i campi della Story
- [ ] Endpoint `POST /api/stories` — crea una nuova Story
- [ ] Endpoint `PATCH /api/stories/{id}` — aggiorna solo i campi passati nel payload
- [ ] Autenticazione via Bearer token (middleware `auth:sanctum`)
- [ ] Form Request `StoryApiRequest` con validazione enum per `status` e `type`
- [ ] Campi CRUD allineati all'edit form developer di Nova
- [ ] Token persistito in `~/.config/webmapp/orchestrator-token` (gestito dalla skill)

## Rischi
- **Side effects Eloquent** — affidarsi agli observer `saving`/`saved` del modello
  invece di replicare la logica Nova; verificare che `attachAutoTags` venga triggerato
- **Token invalido** — la skill intercetta 401 e guida il developer a ri-autenticarsi
- **Sovrascrittura accidentale** — `PATCH` aggiorna solo i campi esplicitamente passati
  (`fill($validated)->save()`)

## Out of scope
- Gestione allegati (Documents / Media Library)
- Endpoint per Epic, Milestone, Customer
- Rate limiting (rimandato a ciclo successivo)
- Revoca automatica token

## Moduli toccati
- `routes/api.php` — nuove route
- `app/Http/Controllers/Api/StoryController.php` — da creare
- `app/Http/Requests/Api/StoryApiRequest.php` — da creare
- `app/Http/Controllers/Api/AuthController.php` — da creare
