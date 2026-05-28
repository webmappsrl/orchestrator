> Ticket: oc:7944

# Plan — Ottimizzazione Costi Hetzner

## Architettura

La feature segue il pattern esistente di `nova-components/kanban-card`:
- **Backend (main app):** config, service, controller, export
- **Frontend (nova-component):** Card Vue self-contained + ServiceProvider
- **Nova:** Dashboard che usa la Card, registrata nel menu

```
config/hetzner.php                                    ← token ENV → array progetti
app/Services/HetznerApiService.php                    ← API Hetzner + cache Redis
app/Http/Controllers/HetznerMonitoringController.php  ← data / refresh / export / note
app/Models/HetznerMonitoring.php                      ← persistenza note in properties jsonb
database/migrations/2026_05_28_150847_create_hetzner_monitoring_table.php
app/Exports/HetznerExport.php                         ← CSV via maatwebsite/excel
nova-components/hetzner-monitoring/
  composer.json
  src/
    HetznerMonitoringCard.php                         ← Card class Nova
    HetznerServiceProvider.php                        ← registra JS + routes
  routes/api.php                                      ← POST/DELETE note
  dist/js/card.js                                     ← Vue component self-contained
app/Nova/Dashboards/HetznerMonitoring.php             ← Dashboard Nova
app/Providers/NovaServiceProvider.php                 ← registra dashboard + menu
composer.json                                         ← path repository nova-component
.env.example                                          ← placeholder token
```

---

## Step 1 — Config ENV

**File:** `config/hetzner.php`

Legge dinamicamente tutte le variabili d'ambiente con prefisso `HETZNER_TOKEN_` e le mappa in un array `projects`:

```php
<?php

return [
    'projects' => collect($_ENV ?? [])
        ->filter(fn($v, $k) => str_starts_with($k, 'HETZNER_TOKEN_'))
        ->mapWithKeys(fn($token, $key) => [
            strtolower(str_replace('HETZNER_TOKEN_', '', $key)) => $token
        ])
        ->toArray(),

    'cache_ttl' => env('HETZNER_CACHE_TTL', 900), // 15 minuti
];
```

Aggiornare `.env.example` aggiungendo una sezione:

```
# Hetzner Cloud API tokens (one per project)
# HETZNER_TOKEN_WEBMAPP=your_token_here
# HETZNER_TOKEN_CLIENTE_X=your_token_here
```

**Commit:** `feat(oc:7944): add hetzner config with dynamic ENV token loading`

---

## Step 2 — HetznerApiService

**File:** `app/Services/HetznerApiService.php`

Service che interroga l'API Hetzner Cloud v1 con caching Redis per progetto.

**Metodi pubblici:**
- `getAllProjectsData(): array` — recupera dati di tutti i progetti (da cache o API)
- `getProjectData(string $slug, string $token): array` — singolo progetto
- `refreshAll(): void` — invalida tutta la cache Hetzner e ricarica

**Struttura dati per progetto:**
```php
[
    'slug'     => 'webmapp',
    'status'   => 'ok' | 'error',
    'error'    => null | 'messaggio errore',
    'servers'  => [...],
    'floating_ips' => [...],
    'volumes'  => [...],
    'load_balancers' => [...],
    'snapshots' => [...],
    'monthly_cost_estimate' => 0.0,  // somma prezzi di listino
]
```

**Endpoint API Hetzner da chiamare per ogni progetto** (GET su `https://api.hetzner.cloud/v1/`):
- `servers` — nome, status, server_type (cores, memory, disk, prices), datacenter, public_net (ipv4), created
- `floating_ips` — ip, type, server (null = non assegnato = spreco), prices
- `volumes` — name, size, status, server (null = non montato = spreco), prices
- `load_balancers` — name, load_balancer_type, targets count, prices
- `images?type=snapshot` — name, image_size, created, prices

**Gestione errori:** ogni progetto è indipendente. Se un token restituisce 401/403/timeout, il progetto viene marcato `status: 'error'` con il messaggio. Gli altri progetti continuano normalmente.

**Cache Redis:** chiave `hetzner_project_{slug}`, TTL da `config('hetzner.cache_ttl')`. Usare `Cache::remember()` per ogni progetto separatamente.

**Rate limiting:** le chiamate per i 5 endpoint di un progetto sono sequenziali (non parallele). I progetti stessi possono essere processati in sequenza.

**Logging:** usare `Log::channel('stack')`. NON loggare il token — passarlo come variabile locale, non come parte del messaggio di log. Configurare Guzzle senza `debug => true` (mai in production).

**Commit:** `feat(oc:7944): add HetznerApiService with Redis caching and per-project error handling`

---

## Step 3 — Persistenza note (DB + Model)

**File:** `database/migrations/2026_05_28_150847_create_hetzner_monitoring_table.php`

Tabella minimale: solo `id`, `properties` (jsonb), `timestamps`. Tutti i metadati vivono in `properties` per evitare migration future quando si aggiungono campi.

**Schema `properties` per una risorsa con nota:**

```json
{
  "project_slug": "default",
  "resource_type": "server",
  "resource_id": 60430948,
  "note": {
    "text": "Da spegnere a fine mese",
    "user_id": 104,
    "user_name": "Mario Rossi",
    "updated_at": "2026-05-28T16:10:12+02:00"
  }
}
```

**Indici PostgreSQL** (raw `DB::statement`, Laravel non supporta indici funzionali su JSON):

- Unique: `(properties->>'project_slug', properties->>'resource_type', (properties->>'resource_id')::bigint)` — una riga per risorsa
- Btree: `(properties->>'project_slug', properties->>'resource_type')` — lookup bulk in `mergeNotes`

**File:** `app/Models/HetznerMonitoring.php`

- `$fillable = ['properties']`, cast `properties` → `array`
- Accessor virtuali: `project_slug`, `resource_type`, `resource_id` (lettura da `properties`)
- `findResource()` / `findOrCreateResource()` via `where('properties->project_slug', ...)` (pattern find + create, non `firstOrCreate` su colonne dedicate)
- `setNote(text, userId, userName)` / `deleteNote()` / `getNote()` — manipolano solo `properties`, preservando le chiavi identificative della risorsa

**Commit:** `feat(oc:7944): add hetzner_monitoring table with jsonb properties for resource notes`

---

## Step 4 — Controller

**File:** `app/Http/Controllers/HetznerMonitoringController.php`

Cinque endpoint, tutti protetti da middleware Nova (`nova`) + `auth`:

```php
// GET  /nova-vendor/hetzner-monitoring/data
// Risposta: JSON progetti da cache Redis + note da DB (mergeNotes)
public function data(): JsonResponse

// POST /nova-vendor/hetzner-monitoring/refresh
// Invalida cache, ricarica da API, merge note, restituisce dati freschi
public function refresh(): JsonResponse

// GET  /nova-vendor/hetzner-monitoring/export
// Scarica CSV (forza download), include note
public function export(): BinaryFileResponse

// POST /nova-vendor/hetzner-monitoring/note
// Body: { project_slug, resource_type, resource_id, text }
// Salva/aggiorna nota in properties, restituisce { ok, note }
public function saveNote(): JsonResponse

// DELETE /nova-vendor/hetzner-monitoring/note
// Body: { project_slug, resource_type, resource_id }
// Rimuove properties.note
public function deleteNote(): JsonResponse
```

**`mergeNotes()`:** dopo il fetch da Redis/API, carica in bulk le righe `hetzner_monitoring` con `whereIn('properties->project_slug', $slugs)` e inietta `note` su ogni risorsa nel JSON di risposta. Usare indicizzazione esplicita su `$projects[$pIndex][$resourceType][$rIndex]` — **non** `foreach ($project[$key] ?? [] as &$resource)` (l'operatore `??` crea una copia e le modifiche non si propagano).

Autorizzazione: verificare che l'utente autenticato abbia ruolo `Admin`, `Manager` o `Developer` (usare `UserRole` enum esistente). Restituire 403 altrimenti.

**Commit:** `feat(oc:7944): add HetznerMonitoringController with data, refresh, export and note endpoints`

---

## Step 5 — Export CSV

**File:** `app/Exports/HetznerExport.php`

Implementare `FromCollection`, `WithHeadings`, `WithMultipleSheets` di Maatwebsite Excel.

Un foglio per tipo di risorsa: **Servers**, **Floating IPs**, **Volumes**, **Load Balancers**, **Snapshots**.

Colonne comuni: Progetto, Nome, Status/Tipo, Costo Mensile Stimato (€), Note.

Nota in intestazione CSV: `"Prezzi di listino Hetzner — escludono sconti, crediti e costi non inclusi nell'API Cloud"`.

**Commit:** `feat(oc:7944): add HetznerExport with multi-sheet CSV per resource type`

---

## Step 6 — Nova Component (Card + ServiceProvider)

### 6a — Struttura package

**File:** `nova-components/hetzner-monitoring/composer.json`

```json
{
    "name": "wm/hetzner-monitoring",
    "description": "Hetzner Cloud monitoring card for Laravel Nova",
    "type": "library",
    "autoload": {
        "psr-4": {
            "Webmapp\\HetznerMonitoring\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Webmapp\\HetznerMonitoring\\HetznerServiceProvider"
            ]
        }
    }
}
```

### 6b — Card class

**File:** `nova-components/hetzner-monitoring/src/HetznerMonitoringCard.php`

Estende `Laravel\Nova\Card`. Width `full`. Nessuna configurazione aggiuntiva necessaria — il componente Vue fa tutto.

### 6c — ServiceProvider

**File:** `nova-components/hetzner-monitoring/src/HetznerServiceProvider.php`

Pattern identico a `kanban-card/src/CardServiceProvider.php`:

```php
Nova::serving(function (ServingNova $event) {
    Nova::script('hetzner-monitoring', __DIR__ . '/../dist/js/card.js');
    Nova::style('hetzner-monitoring', __DIR__ . '/../dist/css/card.css');
});
```

Routes registrate sotto `nova-vendor/hetzner-monitoring`, middleware `nova` + `auth`, che delegano a `HetznerMonitoringController` nel main app:

- `GET /data`, `POST /refresh`, `GET /export`
- `POST /note`, `DELETE /note`

### 6d — Vue component (dist/js/card.js)

Componente self-contained (no build step — scritto come JavaScript puro con Vue 3 definito inline, seguendo il pattern di `kanban-card/dist/js/card.js`).

**Struttura UI:**

```
[Header] Hetzner Monitoring
         Last updated: 5 minutes ago  [↻ Refresh]  [⬇ Export CSV]

[Per ogni progetto — card/tab]
  Progetto: webmapp ───────────────────────── Costo stimato: €XX.XX/mese

  SERVERS (N)
  | Nome | Status 🟢/⚫/🟡 | Tipo | CPU | RAM | Disk | IP | Creato | €/mese | Note |
  | server-01 | 🟢 running | cx22 | 2 | 4GB | 40GB | 1.2.3.4 | 2023-01 | 5.83 | [+] |
  | (riga espansa) 📝 Testo nota — Autore, data |
  
  FLOATING IPs (N)  ⚠️ N non assegnati
  | IP | Tipo | Assegnato a | €/mese |
  
  VOLUMES (N)  ⚠️ N non montati  
  | Nome | Size | Status | Montato su | €/mese |
  
  LOAD BALANCERS (N)
  | Nome | Tipo | Targets | €/mese |
  
  SNAPSHOTS (N)
  | Nome | Size | Creato | €/mese |
```

**Comportamenti:**
- Al mount: fetch `/nova-vendor/hetzner-monitoring/data`
- Stato loading/error gestiti per progetto (non blocca gli altri)
- Server con `status !== 'running'` → riga evidenziata in giallo/grigio
- Floating IP con `server: null` → riga evidenziata in arancione (spreco)
- Volume con `server: null` → riga evidenziata in arancione (spreco)
- Bottone Refresh: POST `/nova-vendor/hetzner-monitoring/refresh`, aggiorna lo stato
- Bottone Export: GET `/nova-vendor/hetzner-monitoring/export` (download diretto)
- Colonna Note: icona `+` per aggiungere, click per modificare; riga espansa con textarea + Salva / Elimina
- Salvataggio nota: POST `/nova-vendor/hetzner-monitoring/note` con `{ project_slug, resource_type, resource_id, text }` — aggiorna UI locale e DB
- Eliminazione nota: DELETE `/nova-vendor/hetzner-monitoring/note` con stesso identificativo risorsa
- `resource_type` ammessi: `server`, `floating_ip`, `volume`, `load_balancer`, `snapshot`
- Nota disclaimer visibile: *"Prezzi di listino — esclusi sconti, crediti, IP aggiuntivi non rilevati dall'API"*

**Commit:** `feat(oc:7944): add hetzner-monitoring nova component with Vue card`

---

## Step 7 — Nova Dashboard

**File:** `app/Nova/Dashboards/HetznerMonitoring.php`

```php
class HetznerMonitoring extends Dashboard
{
    public function cards()
    {
        return [(new HetznerMonitoringCard)->width('full')];
    }

    public static function label()
    {
        return 'Hetzner Monitoring';
    }
}
```

**Commit:** `feat(oc:7944): add HetznerMonitoring Nova dashboard`

---

## Step 8 — NovaServiceProvider

**File:** `app/Providers/NovaServiceProvider.php`

1. Aggiungere `HetznerMonitoring::class` all'array dei dashboards in `dashboards()`.
2. Aggiungere voce di menu nella `mainMenu()` — visibile solo a `Admin`, `Manager`, `Developer`:

```php
MenuSection::dashboard(HetznerMonitoring::class)
    ->icon('server')
    ->canSee(function ($request) {
        return $request->user() && (
            $request->user()->hasRole(UserRole::Admin) ||
            $request->user()->hasRole(UserRole::Manager) ||
            $request->user()->hasRole(UserRole::Developer)
        );
    }),
```

**Commit:** `feat(oc:7944): register HetznerMonitoring dashboard and menu entry in NovaServiceProvider`

---

## Step 9 — Composer path repository

**File:** `composer.json` (root)

Aggiungere il path repository per il nova-component:

```json
"repositories": [
    {
        "type": "path",
        "url": "nova-components/hetzner-monitoring"
    }
]
```

Aggiungere in `require`: `"wm/hetzner-monitoring": "*"`

Eseguire: `composer require wm/hetzner-monitoring`

**Commit:** `feat(oc:7944): register hetzner-monitoring as local composer path dependency`

---

## Ordine di esecuzione consigliato

1. Step 1 (config)
2. Step 2 (service)
3. Step 3 (migration + model note)
4. Step 4 (controller + mergeNotes)
5. Step 5 (export)
6. Step 6a–6c (nova-component structure + service provider + routes note)
7. Step 9 (composer)
8. Step 7 (dashboard)
9. Step 8 (NovaServiceProvider)
10. Step 6d (Vue component — dopo che tutti gli endpoint sono verificabili)

## Test di accettazione

- [ ] Navigare a "Hetzner Monitoring" come Admin → la pagina mostra le tabelle per ogni progetto
- [ ] Status server visualizzati con colore corretto (verde/grigio/giallo)
- [ ] Risorse non assegnate (Floating IP, Volumes) evidenziate in arancione
- [ ] Bottone Refresh aggiorna i dati (verificare `Cache::forget()` prima, poi nuova chiamata API)
- [ ] Export CSV scarica un file con tutti i dati per progetto
- [ ] Utente con ruolo Customer → 403 (non vede il menu)
- [ ] Token non valido in ENV → progetto mostra errore inline senza bloccare gli altri
- [ ] Salvare una nota su un server → compare subito in tabella con autore e data
- [ ] Ricaricare la pagina → la nota è ancora visibile (letta da `hetzner_monitoring.properties`)
- [ ] Modificare la stessa nota → aggiorna la riga esistente (non crea duplicati; indice unique su JSON)
- [ ] Eliminare la nota → scompare dopo refresh
- [ ] Verificare in DB: colonna `properties` contiene `project_slug`, `resource_type`, `resource_id` e `note`
