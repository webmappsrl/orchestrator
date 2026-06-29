> Ticket: oc:8155

# API CRUD per Tag con attach/detach stories

## Cosa cambia
Il sistema espone un set di API REST per il modello Tag, speculare a quello già esistente per Story.
È possibile listare, leggere, creare e aggiornare tag, cercarli per nome, e collegare/scollegare
ticket (stories) da un tag — tutto via API autenticata Sanctum.

## Perché
wm-plan e tool simili usano le API di Orchestrator per gestire i ticket programmaticamente.
Serve la stessa capacità per i tag: creare tag di analisi/preventivo e associarvi ticket
senza passare dall'interfaccia Nova.

## Requisiti
- [ ] `GET /api/tags` — lista tutti i tag; supporta `?search=<name>` per filtrare per nome (case-insensitive, LIKE)
- [ ] `GET /api/tags/{tag}` — dettaglio tag con lista stories associate (id, name, status, customer_request, description)
- [ ] `POST /api/tags` — crea tag; campi accettati: `name` (required), `description` (optional)
- [ ] `PATCH /api/tags/{tag}` — aggiorna tag; campi accettati: `name`, `description` (entrambi opzionali)
- [ ] `POST /api/tags/{tag}/stories/{story}` — attach idempotente (nessun errore se già collegato); crea `StoryLog` con `changes = ['tag_attached' => $tag->id]`
- [ ] `DELETE /api/tags/{tag}/stories/{story}` — detach idempotente (nessun errore se già scollegato); crea `StoryLog` con `changes = ['tag_detached' => $tag->id]`
- [ ] Tutti gli endpoint protetti da `auth:sanctum` + controllo ruolo: solo `Developer` e `Admin` possono accedere (403 per tutti gli altri ruoli)
- [ ] `TagApiRequest` con validazione: `name` required in POST, `name`/`description` opzionali in PATCH
- [ ] `taggable_type`/`taggable_id` non gestiti via API (i tag creati via API sono sempre globali)

## Rischi
- Il modello `Tag` ha due relazioni morfiche distinte con lo stesso nome base `taggable`:
  - `taggable()` → `morphTo`: lega il tag a un singolo parent (es. Project) tramite `tags.taggable_type`/`tags.taggable_id`. **Non toccare** via API.
  - `tagged()` → `morphedByMany(Story::class, 'taggable')`: relazione many-to-many tramite pivot `taggables`. **Usare questa** per attach/detach.
  Confondere le due relazioni nell'implementazione produce bug silenziosi (sovrascrittura del parent o record duplicati nella pivot).
- Il campo `type` non esiste nella colonna DB (`tags` ha: id, name, taggable_id, taggable_type, created_at, updated_at, description, estimate)
  — non va incluso nel FormRequest né nel fillable.

## Out of scope
- Paginazione della lista tag (volumi contenuti, search per nome sufficiente)
- Endpoint `GET /api/tags/{tag}/stories` dedicato
- Gestione `taggable_type`/`taggable_id` via API
- Filtri sulla lista tag diversi dal nome

## Moduli toccati
- `app/Http/Controllers/Api/TagController.php` — nuovo
- `app/Http/Requests/Api/TagApiRequest.php` — nuovo
- `routes/api.php` — aggiunta route group `/tags`
- `tests/Feature/Api/TagApiTest.php` — nuovo
