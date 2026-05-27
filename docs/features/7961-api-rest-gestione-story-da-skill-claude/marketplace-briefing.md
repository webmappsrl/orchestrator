> Ticket: oc:7961

# Briefing per wm-plan — Skill Orchestrator Story (claude-marketplace)

## Contesto

Sul repo `orchestrator` (Laravel) è stata implementata una REST API autenticata
per gestire le Story. Questo briefing descrive il lavoro da fare sul repo
`webmappsrl/claude-marketplace` per aggiornare la skill `wm-plan` e creare
una nuova sub-skill `wm-skills:orchestrator-story` che usa quegli endpoint.

---

## API Orchestrator già disponibile

**Base URL:** `https://orchestrator.webmapp.it` (unico server condiviso)

### Autenticazione

```
POST /api/auth/login
Body: { "email": "...", "password": "..." }
Response 200: { "token": "...", "user": { "id", "name", "email" } }
Response 401: { "message": "Invalid credentials" }
```

Il token va persistito in `~/.config/webmapp/orchestrator-token` come JSON:
```json
{ "token": "1|abc123..." }
```

Tutte le chiamate successive usano `Authorization: Bearer <token>`.
Se una chiamata restituisce 401, la skill deve guidare il developer a
ri-autenticarsi (re-login) e sovrascrivere il file.

### Endpoint Story

```
GET    /api/stories/{id}
POST   /api/stories
PATCH  /api/stories/{id}
```

**Campi disponibili in risposta (GET):**
```json
{
  "id": 1,
  "name": "Titolo ticket",
  "status": "new",
  "type": "Feature",
  "description": "Note tecniche / dev notes",
  "customer_request": "Testo richiesta cliente",
  "user_id": null,
  "tester_id": null,
  "creator_id": null,
  "parent_id": null,
  "estimated_hours": null,
  "hours": null,
  "tags": [{ "id": 1, "name": "25Q2" }],
  "created_at": "2026-05-27T...",
  "updated_at": "2026-05-27T..."
}
```

**Campi scrivibili (POST / PATCH):**
`name`, `description`, `customer_request`, `type`, `status`, `user_id`,
`tester_id`, `creator_id`, `parent_id`, `estimated_hours`, `tags` (array di ID)

**Valori validi per `status`:**
`backlog`, `new`, `assigned`, `todo`, `progress`, `testing`, `tested`,
`waiting`, `done`, `rejected`, `released`

**Valori validi per `type`:**
`Bug`, `Feature`, `Help desk`, `Scrum`

---

## Cosa costruire nel repo claude-marketplace

### 1. Nuova sub-skill: `wm-skills:orchestrator-story`

File: `plugins/wm-skills/skills/orchestrator-story/SKILL.md`

La skill deve fornire a Claude le istruzioni per:

1. **Leggere il file token** da `~/.config/webmapp/orchestrator-token`
   - Se non esiste → eseguire il flusso di login
2. **Login** via `POST /api/auth/login` → salvare il token nel file
3. **Leggere una story** via `GET /api/stories/{id}` → restituire i campi
4. **Creare una story** via `POST /api/stories` → restituire l'ID creato
5. **Aggiornare una story** via `PATCH /api/stories/{id}` → confermare update
6. **Gestire il 401** → re-login automatico, poi ripetere la chiamata

La skill usa `curl` (disponibile ovunque) per le chiamate HTTP.

### 2. Aggiornamento `wm-plan` — Fase 0

Modificare `plugins/wm-skills/skills/wm-plan/SKILL.md` per integrare
`orchestrator-story` nella **Fase 0**:

**Flusso Fase 0 aggiornato:**

```
Hai un ID ticket?
  ├── SÌ → invoca orchestrator-story per leggere la story con quell'ID
  │         estrai: name (titolo), customer_request (richiesta),
  │                 description (note di sviluppo), type, status
  │         procedi con il workflow usando questi dati
  │
  └── NO → procedi con le fasi 1-3 normalmente
            al termine della Fase 3, proponi il testo del ticket
            chiedi conferma all'utente
            invoca orchestrator-story per CREARE la story
            usa l'ID restituito come oc:<ID> per tutti i documenti
```

**Aggiornamento Fase 8** (fine workflow):
Dopo aver aggiornato `CLAUDE.md`, invoca `orchestrator-story` per aggiornare
la story su Orchestrator con le note finali (campo `description` con
l'approccio tecnico scelto).

---

## Note implementative

- La skill usa `Bash` tool di Claude Code per eseguire `curl` e leggere/scrivere
  `~/.config/webmapp/orchestrator-token`
- Il file token non va mai committato — è fuori da qualsiasi repo
- La skill deve essere idempotente: se il token è già valido, non re-autentica
- Testare con un account developer reale su orchestrator.webmapp.it
