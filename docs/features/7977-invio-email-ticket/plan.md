> Ticket: oc:7977

# Plan — Invio email ticket al creator indipendentemente dal ruolo

## Step 1 — Fix `app/Models/Story.php`

**File:** `app/Models/Story.php`
**Blocco da modificare:** hook `saved()`, righe 64-73

### Condizione attuale

```php
if (
    isset($story->creator_id)
    && $story->creator->hasRole($customerRole)
    && $story->status === $releasedStatus
    && $story->wasChanged('status')
) {
    $story->sendStatusUpdatedEmail($story, $story->creator_id, [
        'highlight_latest_response' => $story->wasChanged('customer_request'),
    ]);
}
```

### Condizione dopo il fix

```php
if (
    isset($story->creator_id)
    && $currentStatus === $releasedStatus
    && $story->wasChanged('status')
    && $story->creator_id !== $story->user_id
    && $story->creator_id !== $story->tester_id
    && (!$sender || $sender->id !== $story->creator_id)
) {
    $story->sendStatusUpdatedEmail($story, $story->creator_id, [
        'highlight_latest_response' => $story->wasChanged('customer_request'),
    ]);
}
```

**Modifiche:**
1. `$story->creator->hasRole($customerRole)` → rimosso (email a qualsiasi ruolo)
2. `$story->status === $releasedStatus` → `$currentStatus === $releasedStatus` (usa la variabile normalizzata, corregge bug latente enum-vs-string)
3. `$story->creator_id !== $story->user_id` → deduplicazione con assignee
4. `$story->creator_id !== $story->tester_id` → deduplicazione con tester
5. `(!$sender || $sender->id !== $story->creator_id)` → non notificare chi ha fatto l'azione (pattern identico agli altri blocchi)

---

## Step 2 — Test `tests/Feature/StoryEmailTriggersTest.php`

Aggiungere una nuova sezione di test in coda al file, seguendo le convenzioni esistenti (`Bus::fake()`, `DatabaseTransactions`, metodi helper `makeCustomer()`, `makeDeveloper()`).

### Test da aggiungere

#### 2a — Developer-creator riceve email su Released

```php
/** @test */
public function creator_developer_receives_email_when_status_set_to_released(): void
{
    Bus::fake();
    $actor = $this->makeDeveloper();
    $creator = $this->makeDeveloper();
    $story = $this->makeStory([
        'creator_id' => $creator->id,
        'status' => StoryStatus::Progress->value,
    ]);

    Auth::login($actor);
    $story->status = StoryStatus::Released->value;
    $story->save();

    $this->assertCount(1, $this->jobsDispatchedTo($creator->id));
}
```

#### 2b — Nessuna email duplicata se creator == assignee

```php
/** @test */
public function creator_receives_no_duplicate_email_when_also_assignee(): void
{
    Bus::fake();
    $actor = $this->makeDeveloper();
    $creator = $this->makeDeveloper();
    $story = $this->makeStory([
        'creator_id' => $creator->id,
        'user_id'    => $creator->id,
        'status'     => StoryStatus::Progress->value,
    ]);

    Auth::login($actor);
    $story->status = StoryStatus::Released->value;
    $story->save();

    // Al massimo 1 email totale al creator (non 2)
    $this->assertLessThanOrEqual(1, $this->jobsDispatchedTo($creator->id)->count());
}
```

#### 2c — Nessuna email duplicata se creator == tester

```php
/** @test */
public function creator_receives_no_duplicate_email_when_also_tester(): void
{
    Bus::fake();
    $actor = $this->makeDeveloper();
    $creator = $this->makeDeveloper();
    $story = $this->makeStory([
        'creator_id' => $creator->id,
        'tester_id'  => $creator->id,
        'status'     => StoryStatus::Progress->value,
    ]);

    Auth::login($actor);
    $story->status = StoryStatus::Released->value;
    $story->save();

    $this->assertLessThanOrEqual(1, $this->jobsDispatchedTo($creator->id)->count());
}
```

#### 2d — Nessuna email al creator se è lui stesso a fare l'azione (self-release)

```php
/** @test */
public function creator_receives_no_email_when_they_set_released_themselves(): void
{
    Bus::fake();
    $creator = $this->makeDeveloper();
    $story = $this->makeStory([
        'creator_id' => $creator->id,
        'status'     => StoryStatus::Progress->value,
    ]);

    Auth::login($creator);
    $story->status = StoryStatus::Released->value;
    $story->save();

    $this->assertCount(0, $this->jobsDispatchedTo($creator->id));
}
```

#### 2e — Customer-creator continua a ricevere email (regressione)

```php
/** @test */
public function customer_creator_still_receives_email_when_status_set_to_released(): void
{
    Bus::fake();
    $actor = $this->makeDeveloper();
    $customer = $this->makeCustomer();
    $story = $this->makeStory([
        'creator_id' => $customer->id,
        'status'     => StoryStatus::Progress->value,
    ]);

    Auth::login($actor);
    $story->status = StoryStatus::Released->value;
    $story->save();

    $this->assertCount(1, $this->jobsDispatchedTo($customer->id));
}
```

---

## Step 3 — Verifica test esistenti

Dopo le modifiche, eseguire dentro il container:

```bash
php artisan test --filter=StoryEmailTriggersTest
```

Tutti i test esistenti devono restare verdi. I nuovi test devono passare.

---

## Commit convention

```
fix(oc:7977): send released email to story creator regardless of role
```
