> Ticket: oc:8091

# Plan — Fix invio email per ticket di tipo Scrum

## Task 1 — Aggiungere la guardia nell'hook `created` di `Story.php`

**File:** `app/Models/Story.php`

Nel metodo `booted()`, dentro l'hook `static::created(function (Story $story) { ... })`, aggiungere il check subito dopo `$story->save()` (riga ~167) e prima del loop `foreach ($developers as $developer)`:

```php
if ($story->type === StoryType::Scrum->value) {
    return;
}
```

La guardia deve stare **dopo** `$story->save()` (che assegna `creator_id`, `tester_id`) e **prima** di `$developers = User::whereJsonContains(...)`. In questo modo:
- L'assegnazione dei metadati avviene sempre
- La query sui developer non viene nemmeno eseguita per ticket Scrum
- L'invio mail è bloccato

## Task 2 — Aggiungere il test di regressione in `StoryEmailTriggersTest.php`

**File:** `tests/Feature/StoryEmailTriggersTest.php`

Aggiungere un test nella sezione della creazione ticket (dopo i test esistenti per oc:8040):

```php
/** @test */
public function scrum_story_creation_does_not_send_email_to_developers(): void
{
    Mail::fake();
    $developer = $this->makeDeveloper();
    Auth::login($developer);

    Story::query()->create([
        'name' => 'Scrum ticket',
        'type' => StoryType::Scrum->value,
        'status' => StoryStatus::New->value,
    ]);

    Mail::assertNotSent(CustomerNewStoryCreated::class);
}
```

Il developer viene creato esplicitamente per garantire che il loop `User::whereJsonContains('roles', Developer)` abbia almeno un risultato — l'assertion `assertNotSent` è significativa solo se c'è qualcuno a cui potenzialmente inviare.

## Task 3 — Commit

```
fix(oc:8091): skip creation email for Scrum-type stories
```
