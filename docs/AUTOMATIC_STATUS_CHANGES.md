# Modifiche Automatiche dello Stato dei Ticket

Questo documento elenca tutti i punti nel codice dove lo stato di un ticket viene modificato automaticamente, senza intervento diretto dell'utente.

## 📋 Indice

1. [Comandi Schedulati](#comandi-schedulati)
2. [StoryObserver](#storyobserver)
3. [Story Model - Eventi](#story-model---eventi)
4. [Job per Creazione Story](#job-per-creazione-story)
5. [Comandi Manuali](#comandi-manuali)

---

## 1. Comandi Schedulati

I seguenti comandi vengono eseguiti automaticamente in base alla configurazione in `app/Console/Kernel.php`:

### 1.1. Story Progress to Todo (18:00)

**Comando:** `story:progress-to-todo`  
**File:** `app/Console/Commands/MoveProgressStoriesInTodoCommand.php`  
**Schedule:** Giornaliero alle 18:00 (timezone: Europe/Rome)  
**Config:** `config('orchestrator.tasks.story_progress_to_todo')`

**Comportamento:**
- Trova tutti i ticket con status `Progress`
- Imposta lo status a `Todo`
- Salva il ticket (triggera eventi)

```php
Story::where('status', StoryStatus::Progress->value)
    ->each(function ($story) {
        $story->status = StoryStatus::Todo->value;
        $story->save(); // Triggera eventi
    });
```

---

### 1.2. Story Scrum to Done (16:00)

**Comando:** `story:scrum-to-done`  
**File:** `app/Console/Commands/MoveScrumStoriesInDoneCommand.php`  
**Schedule:** Giornaliero alle 16:00 (timezone: Europe/Rome)  
**Config:** `config('orchestrator.tasks.story_scrum_to_done')`

**Comportamento:**
- Trova tutti i ticket di tipo `Scrum` creati o aggiornati oggi
- Imposta lo status a `Done`
- Salva il ticket con `saveQuietly()` (NON triggera eventi)

```php
Story::where('type', StoryType::Scrum->value)
    ->where(function ($query) use ($today) {
        $query->whereDate('created_at', '=', $today)
            ->orWhereDate('updated_at', '=', $today);
    })
    ->each(function ($story) {
        $story->status = StoryStatus::Done->value;
        $story->saveQuietly(); // NON triggera eventi
    });
```

---

### 1.3. Story Auto Update Status (07:45)

**Comando:** `story:auto-update-status`  
**File:** `app/Console/Commands/AutoUpdateStoryStatus.php`  
**Schedule:** Giornaliero alle 07:45 (timezone: Europe/Rome)  
**Config:** `config('orchestrator.tasks.story_auto_update_status')`

**Comportamento:**
- Trova tutti i ticket con status `Released` aggiornati almeno 3 giorni lavorativi fa (escludendo weekend)
- Imposta lo status a `Done`
- Salva il ticket con `saveQuietly()` (NON triggera eventi)

```php
Story::where('status', StoryStatus::Released->value)
    ->whereDate('updated_at', '<=', $daysAgo) // 3 giorni lavorativi fa
    ->each(function ($story) {
        $story->status = StoryStatus::Done->value;
        $story->saveQuietly(); // NON triggera eventi
    });
```

**Nota:** Il calcolo dei "3 giorni lavorativi" esclude sabato e domenica.

---

## 2. StoryObserver

Gli eventi gestiti da `app/Observers/StoryObserver.php` che modificano automaticamente lo stato:

### 2.1. Saving Event - Solo un ticket in Progress per utente

**Metodo:** `StoryObserver::saving()`  
**Trigger:** Prima del salvataggio di un ticket

**Comportamento:**
- Quando un ticket viene impostato a status `Progress`
- Trova tutti gli altri ticket dello stesso utente (`user_id`) con status `Progress`
- Imposta il loro status a `Todo`
- Salva ogni ticket (triggera eventi come aggiornamento Google Calendar)

```php
if ($story->isDirty('status') && $story->status === StoryStatus::Progress->value) {
    Story::where('user_id', $story->user_id)
        ->where('status', StoryStatus::Progress->value)
        ->whereNot('id', $story->id)
        ->each(function (Story $progressStory) {
            $progressStory->status = StoryStatus::Todo->value;
            $progressStory->save(); // Triggera eventi
        });
}
```

---

### 2.2. Updating Event - Aggiornamento richiesta cliente

**Metodo:** `StoryObserver::updating()`  
**Trigger:** Prima dell'aggiornamento di un ticket

**Comportamento:**
- Quando un utente diverso dal developer assegnato modifica il campo `customer_request`
- Se il developer assegnato ha ruolo `Customer`
- Imposta lo status del ticket a `Todo`

```php
if (!$story->wasRecentlyCreated
    && $story->isDirty('customer_request')
    && $user && $story->user
    && $user->id != $story->user->id
    && $story->user->hasRole(UserRole::Customer)) {
    $story->status = StoryStatus::Todo->value;
}
```

---

### 2.3. Updated Event - Aggiornamento date Released/Done

**Metodo:** `StoryObserver::updateReleaseAndDoneDates()`  
**Trigger:** Dopo l'aggiornamento di un ticket

**Comportamento:**

#### Quando lo status cambia a `Released`:
- Imposta `released_at = now()` se non è già valorizzato
- Salva con `saveQuietly()` (NON triggera eventi)

#### Quando lo status cambia a `Done`:
1. Chiama `StoryDateService::updateDates()` per cercare le date dai log
2. Se `done_at` è ancora null dopo la ricerca nei log:
   - Usa `released_at` se disponibile
   - Altrimenti usa `now()`
3. Salva con `saveQuietly()` (NON triggera eventi)

```php
// Status -> Released
if ($statusChanged && $story->status === StoryStatus::Released->value && !$story->released_at) {
    $story->released_at = now();
    $story->saveQuietly();
}

// Status -> Done
if ($statusChanged && $story->status === StoryStatus::Done->value && !$story->done_at) {
    $dateService->updateDates($story); // Cerca nei log
    
    if (!$story->done_at && $story->released_at) {
        $story->done_at = $story->released_at;
    } else if (!$story->done_at) {
        $story->done_at = now();
    }
    
    $story->saveQuietly();
}
```

---

## 3. Story Model - Eventi

Gli eventi gestiti direttamente nel modello `app/Models/Story.php`:

### 3.1. Saving Event - Assegnazione automatica

**Metodo:** `Story::boot()` -> `saving()`  
**Trigger:** Prima del salvataggio di un ticket

**Comportamento:**

#### Assegnazione Developer:
- Se lo status è `New` e viene assegnato un `user_id` (developer)
- Imposta automaticamente lo status a `Assigned`

```php
if ($story->status == StoryStatus::New->value && $story->user_id && $story->isDirty('user_id')) {
    $story->status = StoryStatus::Assigned->value;
}
```

#### Rimozione Developer da New:
- Se lo status è `New` e viene cambiato lo status
- Rimuove l'assegnazione del developer (`user_id = null`)

```php
if ($story->status == StoryStatus::New->value && $story->isDirty('status')) {
    $story->user_id = null;
}
```

---

### 3.2. Saved Event - Aggiornamento Epic

**Metodo:** `Story::booted()` -> `saved()`  
**Trigger:** Dopo il salvataggio di un ticket

**Comportamento:**
- Se il ticket appartiene a un Epic
- Aggiorna lo status dell'Epic basandosi sugli status delle story figlie
- Salva l'Epic (triggera eventi)

```php
if (!empty($story->epic)) {
    $epic = $story->epic;
    $epic->status = $epic->getStatusFromStories()->value;
    $epic->save();
}
```

**Logica Epic Status:**
- Nessuna storia -> `New`
- Tutte `New` -> `New`
- Tutte `Test` -> `Test`
- Tutte `Done` -> `Done`
- Almeno una `Rejected` -> `Rejected`
- Almeno una diversa da `New` -> `Progress`

---

### 3.3. Updated Event - Sincronizzazione Story Figlie

**Metodo:** `Story::booted()` -> `updated()`  
**Trigger:** Dopo l'aggiornamento di un ticket

**Comportamento:**
- Se lo status del ticket padre cambia
- Aggiorna lo status di tutte le story figlie allo stesso status del padre
- Salva ogni story figlia (triggera eventi)

```php
if ($story->isDirty('status')) {
    foreach ($story->childStories as $child) {
        $child->status = $story->status;
        $child->save(); // Triggera eventi
    }
}
```

---

## 4. Job per Creazione Story

Questi job non modificano lo stato ma creano nuove story con uno status iniziale:

### 4.1. Process Inbound Emails

**Job:** `ProcessInboundEmails`  
**File:** `app/Jobs/ProcessInboundEmails.php`  
**Schedule:** Ogni 5 minuti (se abilitato in `app/Console/Kernel.php`)  
**Config:** `config('orchestrator.tasks.process_inbound_emails')`

**Comportamento:**
- Legge email non lette dalla casella configurata (IMAP)
- Per ogni email da un utente registrato, crea una nuova story con:
  - `status = New`
  - `type = Helpdesk`
  - `creator_id = user_id` (dall'email mittente)
  - `name = subject` dell'email
  - `customer_request = body` dell'email
- Salva la story (triggera eventi, incluso `StoryObserver::created()`)

```php
$story = new Story();
$story->name = $subject;
$story->customer_request = $body;
$story->type = StoryType::Helpdesk;
$story->status = StoryStatus::New;
$story->creator_id = $user->id;
$story->save(); // Triggera eventi
```

**Note:**
- Questo job NON modifica lo stato di story esistenti, ma crea nuove story
- Lo status iniziale è sempre `New`
- Gli allegati vengono associati alla story tramite media collection

---

## 5. Comandi Manuali

Questi comandi non sono schedulati ma possono essere eseguiti manualmente:

### 4.1. Update Story Status

**Comando:** `story:update-status`  
**File:** `app/Console/Commands/UpdateStoryStatusCommand.php`

**Comportamento:**
- Trova tutti i ticket con status `New` che hanno un `user_id` assegnato
- Imposta lo status a `Assigned`
- Salva il ticket (triggera eventi)

```php
Story::where('status', StoryStatus::New->value)
    ->whereNotNull('user_id')
    ->each(function ($story) {
        $story->status = StoryStatus::Assigned->value;
        $story->save(); // Triggera eventi
    });
```

---

## 📊 Riepilogo Tabellare

| Punto | Trigger | Status Da | Status A | Save Method | Triggera Eventi |
|-------|---------|-----------|----------|-------------|-----------------|
| **Comandi Schedulati** |
| `story:progress-to-todo` | 18:00 (daily) | `Progress` | `Todo` | `save()` | ✅ Sì |
| `story:scrum-to-done` | 16:00 (daily) | Qualsiasi | `Done` | `saveQuietly()` | ❌ No |
| `story:auto-update-status` | 07:45 (daily) | `Released` (3+ giorni) | `Done` | `saveQuietly()` | ❌ No |
| **StoryObserver** |
| `saving()` | Prima salvataggio | `Progress` (altri) | `Todo` | `save()` | ✅ Sì |
| `updating()` | Prima aggiornamento | Qualsiasi | `Todo` | Implicito | ✅ Sì |
| `updated()` | Dopo aggiornamento | `Released` | (set `released_at`) | `saveQuietly()` | ❌ No |
| `updated()` | Dopo aggiornamento | `Done` | (set `done_at`) | `saveQuietly()` | ❌ No |
| **Story Model** |
| `saving()` | Prima salvataggio | `New` + `user_id` | `Assigned` | Implicito | ✅ Sì |
| `saving()` | Prima salvataggio | `New` + status change | (rimuove `user_id`) | Implicito | ✅ Sì |
| `saved()` | Dopo salvataggio | - | (aggiorna Epic) | `save()` | ✅ Sì |
| `updated()` | Dopo aggiornamento | (parent) | (figlie) | `save()` | ✅ Sì |
| **Job per Creazione** |
| `ProcessInboundEmails` | Ogni 5 minuti | (nuova story) | `New` | `save()` | ✅ Sì |
| **Comandi Manuali** |
| `story:update-status` | Manuale | `New` + `user_id` | `Assigned` | `save()` | ✅ Sì |

---

## 🔍 Note Importanti

1. **`save()` vs `saveQuietly()`:**
   - `save()` triggera tutti gli eventi Eloquent (created, updated, saving, etc.)
   - `saveQuietly()` salva il record senza triggerare eventi
   - I comandi schedulati usano spesso `saveQuietly()` per evitare loop infiniti o eventi non desiderati

2. **Creazione Story Log:**
   - Tutte le modifiche (tranne quelle con `saveQuietly()`) vengono registrate in `story_logs` tramite `StoryObserver::createStoryLog()`
   - Questo significa che i comandi schedulati con `saveQuietly()` NON creano entry in `story_logs`

3. **Story Figlie:**
   - Quando cambia lo status di un ticket padre, tutte le story figlie vengono aggiornate automaticamente
   - Questo può generare molte modifiche in cascata

4. **Epic Status:**
   - Lo status dell'Epic viene ricalcolato automaticamente quando una story viene salvata
   - La logica è complessa e considera lo stato di tutte le story figlie

---

## 🛠️ Debugging

Per verificare quale meccanismo ha causato un cambio di stato:

1. Controllare `story_logs` per vedere quando e da chi è stato modificato
2. Verificare i log di Laravel (`storage/logs/laravel.log`) per i comandi schedulati
3. Controllare la tabella `schedules` o eseguire `php artisan schedule:list` per vedere i task configurati

---

**Ultimo aggiornamento:** Gennaio 2025  
**Versione:** MS-1.20.0

