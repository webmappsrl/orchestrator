# Modifiche Automatiche dello Stato dei Ticket

Questo documento elenca tutti i punti nel codice dove lo stato di un ticket viene modificato automaticamente, senza intervento diretto dell'utente.

## 📋 Indice {#indice}

1. [Comandi Schedulati](#1-comandi-schedulati)
   - 1.1. [Story Progress to Todo (18:00)](#11-story-progress-to-todo-1800)
   - 1.2. [Story Scrum to Done (16:00)](#12-story-scrum-to-done-1600)
   - 1.3. [Story Auto Update Status (07:45)](#13-story-auto-update-status-0745)
2. [StoryObserver](#2-storyobserver)
   - 2.1. [Saving Event - Solo un ticket in Progress per utente](#21-saving-event---solo-un-ticket-in-progress-per-utente)
   - 2.2. [Updating Event - Aggiornamento richiesta cliente](#22-updating-event---aggiornamento-richiesta-cliente)
   - 2.3. [Updated Event - Aggiornamento date Released/Done](#23-updated-event---aggiornamento-date-releaseddone)
     - [Quando lo status cambia a Released](#quando-lo-status-cambia-a-released)
     - [Quando lo status cambia a Done](#quando-lo-status-cambia-a-done)
3. [Story Model - Eventi](#3-story-model---eventi)
   - 3.1. [Saving Event - Assegnazione automatica](#31-saving-event---assegnazione-automatica)
     - [Assegnazione Developer](#assegnazione-developer)
     - [Rimozione Developer da New](#rimozione-developer-da-new)
   - 3.2. [Saved Event - Aggiornamento Epic](#32-saved-event---aggiornamento-epic)
     - [Logica Epic Status](#logica-epic-status)
   - 3.3. [Updated Event - Sincronizzazione Story Figlie](#33-updated-event---sincronizzazione-story-figlie)
4. [Job per Creazione Story](#4-job-per-creazione-story)
   - 4.1. [Process Inbound Emails](#41-process-inbound-emails)
5. [Comandi Manuali](#5-comandi-manuali)
   - 5.1. [Update Story Status](#51-update-story-status)
6. [Creazione e Aggiornamento Story Logs](#6-creazione-e-aggiornamento-story-logs)
   - 6.1. [StoryObserver::createStoryLog()](#61-storyobservercreatestorylog)
   - 6.2. [LogStory Middleware](#62-logstory-middleware)
   - 6.3. [SendWaitingStoryReminder Command](#63-sendwaitingstoryreminder-command)
7. [Riepilogo Tabellare](#-riepilogo-tabellare)
8. [Note Importanti](#-note-importanti)
9. [Debugging](#️-debugging)

---

## 1. Comandi Schedulati

↑ [Torna all'indice](#indice)

I seguenti comandi vengono eseguiti automaticamente in base alla configurazione in `app/Console/Kernel.php`:

### 1.1. Story Progress to Todo (18:00)

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

Gli eventi gestiti da `app/Observers/StoryObserver.php` che modificano automaticamente lo stato:

### 2.1. Saving Event - Solo un ticket in Progress per utente

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

**Metodo:** `StoryObserver::updateReleaseAndDoneDates()`  
**Trigger:** Dopo l'aggiornamento di un ticket

**Comportamento:**

#### Quando lo status cambia a `Released`:

↑ [Torna all'indice](#indice)
- Imposta `released_at = now()` se non è già valorizzato
- Salva con `saveQuietly()` (NON triggera eventi)

#### Quando lo status cambia a `Done`:

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

Gli eventi gestiti direttamente nel modello `app/Models/Story.php`:

### 3.1. Saving Event - Assegnazione automatica

↑ [Torna all'indice](#indice)

**Metodo:** `Story::boot()` -> `saving()`  
**Trigger:** Prima del salvataggio di un ticket

**Comportamento:**

#### Assegnazione Developer:

↑ [Torna all'indice](#indice)
- Se lo status è `New` e viene assegnato un `user_id` (developer)
- Imposta automaticamente lo status a `Assigned`

```php
if ($story->status == StoryStatus::New->value && $story->user_id && $story->isDirty('user_id')) {
    $story->status = StoryStatus::Assigned->value;
}
```

#### Rimozione Developer da New:

↑ [Torna all'indice](#indice)

- Se lo status è `New` e viene cambiato lo status
- Rimuove l'assegnazione del developer (`user_id = null`)

```php
if ($story->status == StoryStatus::New->value && $story->isDirty('status')) {
    $story->user_id = null;
}
```

---

### 3.2. Saved Event - Aggiornamento Epic

↑ [Torna all'indice](#indice)

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

#### Logica Epic Status:

↑ [Torna all'indice](#indice)
- Nessuna storia -> `New`
- Tutte `New` -> `New`
- Tutte `Test` -> `Test`
- Tutte `Done` -> `Done`
- Almeno una `Rejected` -> `Rejected`
- Almeno una diversa da `New` -> `Progress`

---

### 3.3. Updated Event - Sincronizzazione Story Figlie

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

Questi job non modificano lo stato ma creano nuove story con uno status iniziale:

### 4.1. Process Inbound Emails

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

Questi comandi non sono schedulati ma possono essere eseguiti manualmente:

### 5.1. Update Story Status

↑ [Torna all'indice](#indice)

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

## 6. Creazione e Aggiornamento Story Logs

↑ [Torna all'indice](#indice)

La tabella `story_logs` viene aggiornata in diversi punti per tracciare le modifiche e le visualizzazioni dei ticket. Questa sezione descrive tutti i punti dove viene creata o aggiornata una entry in `story_logs`.

### 6.1. StoryObserver::createStoryLog()

↑ [Torna all'indice](#indice)

**Metodo:** `StoryObserver::createStoryLog()`  
**File:** `app/Observers/StoryObserver.php`  
**Trigger:** Chiamato da `StoryObserver::updated()` dopo che una story viene aggiornata

**Comportamento:**
- Viene eseguito quando una story viene aggiornata (non alla creazione)
- Non viene eseguito se la story è stata appena creata (`wasRecentlyCreated` o flag interno)
- Crea una entry in `story_logs` per ogni modifica ai campi della story

**Campi registrati:**
- `story_id`: ID della story modificata
- `user_id`: ID dell'utente che ha effettuato la modifica (o `orchestrator_artisan@webmapp.it` se nessun utente autenticato)
- `viewed_at`: Timestamp della modifica (formato `Y-m-d H:i`)
- `changes`: JSON con tutti i campi modificati e i loro nuovi valori

**Dettagli:**
- Il campo `description` viene registrato come `'change description'` invece del valore completo
- Se ci sono modifiche, viene anche:
  - Creato un log in `activity.log`
  - Eseguito `StoryTimeService::run()` per calcolare i tempi
  - Dispatchato il job `UpdateUsersStoriesLogJob` per aggiornare `users_stories_log`
  - Chiamato `saveQuietly()` sulla story per evitare loop infiniti

**Esempio di entry creata:**
```php
StoryLog::create([
    'story_id' => $story->id,
    'user_id' => $user->id,
    'viewed_at' => now()->format('Y-m-d H:i'),
    'changes' => [
        'status' => 'progress',
        'user_id' => 123,
        'description' => 'change description'
    ]
]);
```

**Quando NON viene creata una entry:**
- Se la story è appena stata creata (`wasRecentlyCreated`)
- Se non ci sono campi modificati (`getDirty()` è vuoto)
- Se viene usato `saveQuietly()` sulla story (non triggera eventi Eloquent)

---

### 6.2. LogStory Middleware

↑ [Torna all'indice](#indice)

**Middleware:** `LogStory`  
**File:** `app/Http/Middleware/LogStory.php`  
**Registrato in:** `app/Http/Kernel.php` (gruppi `web` e `api`)  
**Trigger:** Ad ogni richiesta HTTP che corrisponde al pattern `/resources/*stories*/{id}`

**Comportamento:**
- Traccia le visualizzazioni delle story da parte degli utenti
- Crea o aggiorna una entry in `story_logs` quando un utente visualizza una story

**Logica:**
1. Estrae l'ID della story dall'URL (`/resources/*stories*/{id}`)
2. Verifica se esiste già una entry per oggi per lo stesso utente e story
3. Se esiste:
   - Aggiorna `updated_at` solo se sono passati almeno 30 minuti dall'ultima visualizzazione
   - Usa `touch()` per aggiornare il timestamp
4. Se non esiste:
   - Crea una nuova entry con `changes['watch']` = timestamp corrente

**Esempio di entry creata:**
```php
StoryLog::create([
    'story_id' => $storyId,
    'user_id' => $userId,
    'viewed_at' => now(),
    'changes' => ['watch' => now()->format('Y-m-d H:i:s')]
]);
```

**Note:**
- Questo middleware NON modifica lo stato della story
- Serve solo per tracciare le visualizzazioni (analytics)
- Le entry con solo `watch` vengono usate per determinare se ci sono state modifiche rilevanti (vedi `SendWaitingStoryReminder`)

---

### 6.3. SendWaitingStoryReminder Command

↑ [Torna all'indice](#indice)

**Comando:** `story:send-waiting-reminder`  
**File:** `app/Console/Commands/SendWaitingStoryReminder.php`  
**Schedule:** Configurabile (non schedulato di default)  
**Trigger:** Manuale o schedulato

**Comportamento:**
- Trova tutte le story con status `Waiting` create almeno 3 giorni lavorativi fa
- Verifica se devono ricevere un reminder email (basandosi sull'ultima modifica rilevante in `story_logs`)
- Invia un'email di reminder al creator della story
- Crea una entry in `story_logs` per tracciare l'invio del reminder

**Creazione StoryLog:**
Il comando crea una entry dopo aver inviato il reminder:

```php
StoryLog::create([
    'story_id' => $story->id,
    'user_id' => 1, // User ID fisso (sistema)
    'viewed_at' => now()->format('Y-m-d H:i'),
    'changes' => ['status' => StoryStatus::Waiting->value]
]);
```

**Logica per determinare se inviare il reminder:**
1. Cerca in `story_logs` l'ultima entry dove `changes->status = Waiting`
2. Cerca l'ultima modifica rilevante dopo quel punto (escludendo le entry con solo `watch`)
3. Se l'ultima modifica rilevante è più vecchia di 3 giorni lavorativi, invia il reminder

**Note:**
- Questo comando NON modifica lo stato della story
- Serve solo per inviare reminder e tracciare l'invio in `story_logs`
- Usa un `user_id` fisso (1) per identificare le entry create dal sistema

---

### Riepilogo Creazione Story Logs

| Punto | Trigger | Tipo Entry | Quando NON viene creata |
|-------|---------|------------|-------------------------|
| **StoryObserver::createStoryLog()** | Dopo aggiornamento story | Modifiche ai campi | Se `wasRecentlyCreated`, se `getDirty()` vuoto, se `saveQuietly()` |
| **LogStory Middleware** | Visualizzazione story | Tracciamento visualizzazione | Se già esiste entry oggi e < 30 minuti |
| **SendWaitingStoryReminder** | Dopo invio reminder | Tracciamento reminder | Se reminder non viene inviato |

**Importante:**
- Le entry create tramite `saveQuietly()` NON vengono registrate in `story_logs` perché non triggerano eventi Eloquent
- Le modifiche automatiche dello stato (come quelle dei comandi schedulati) spesso usano `saveQuietly()` e quindi NON creano entry in `story_logs`

---

## 📊 Riepilogo Tabellare

↑ [Torna all'indice](#indice)

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

↑ [Torna all'indice](#indice)

1. **`save()` vs `saveQuietly()`:**
   - `save()` triggera tutti gli eventi Eloquent (created, updated, saving, etc.)
   - `saveQuietly()` salva il record senza triggerare eventi
   - I comandi schedulati usano spesso `saveQuietly()` per evitare loop infiniti o eventi non desiderati

2. **Creazione Story Log:**
   - Tutte le modifiche (tranne quelle con `saveQuietly()`) vengono registrate in `story_logs` tramite `StoryObserver::createStoryLog()`
   - Questo significa che i comandi schedulati con `saveQuietly()` NON creano entry in `story_logs`
   - Le visualizzazioni delle story vengono tracciate dal middleware `LogStory` (vedi sezione 6.2)
   - Il comando `SendWaitingStoryReminder` crea entry in `story_logs` per tracciare l'invio dei reminder (vedi sezione 6.3)

3. **Story Figlie:**
   - Quando cambia lo status di un ticket padre, tutte le story figlie vengono aggiornate automaticamente
   - Questo può generare molte modifiche in cascata

4. **Epic Status:**
   - Lo status dell'Epic viene ricalcolato automaticamente quando una story viene salvata
   - La logica è complessa e considera lo stato di tutte le story figlie

---

## 🛠️ Debugging

↑ [Torna all'indice](#indice)

Per verificare quale meccanismo ha causato un cambio di stato:

1. Controllare `story_logs` per vedere quando e da chi è stato modificato
2. Verificare i log di Laravel (`storage/logs/laravel.log`) per i comandi schedulati
3. Controllare la tabella `schedules` o eseguire `php artisan schedule:list` per vedere i task configurati

---

**Ultimo aggiornamento:** Gennaio 2025  
**Versione:** MS-1.20.0

