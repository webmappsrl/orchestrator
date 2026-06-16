> Ticket: oc:8040

# Plan — Invio email a tutti i dev alla creazione di qualsiasi ticket

## Step 1 — Modifica `app/Mail/CustomerNewStoryCreated.php`

Aggiungere la proprietà `$novaUrl` calcolata nel costruttore in base al ruolo del creator. Passarla al template.

```php
use App\Enums\UserRole;

class CustomerNewStoryCreated extends Mailable
{
    public $story;
    public $creator;
    public string $novaUrl;

    public function __construct(Story $story)
    {
        $this->story = $story;
        $this->creator = $story->creator;
        $this->novaUrl = $story->creator->hasRole(UserRole::Customer)
            ? '/resources/customer-stories/' . $story->id
            : '/resources/stories/' . $story->id;
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.customer-new-story-created',
            with: [
                'story' => $this->story,
                'creator' => $this->creator,
                'novaUrl' => $this->novaUrl,
            ],
        );
    }
}
```

---

## Step 2 — Aggiorna template `resources/views/mails/customer-new-story-created.blade.php`

Corpo condizionale su `customer_request` (se vuoto non mostra nulla). Link dinamico via `$novaUrl`.

```blade
<div class="content">
    @if($story->customer_request)
        {!! $story->customer_request !!}
    @endif
    <p><a href="{{ url($novaUrl) }}">Ticket {{ $story->id }}</a></p>
</div>
```

---

## Step 3 — Modifica `app/Models/Story.php` — hook `created()`

Rimuovere il branch condizionale per mail class. Usare sempre `CustomerNewStoryCreated`. Rimuovere l'import di `DevNewStoryCreated`.

```php
$developers = User::whereJsonContains('roles', UserRole::Developer)->get();
foreach ($developers as $developer) {
    try {
        Mail::to($developer->email)->send(new CustomerNewStoryCreated($story));
    } catch (\Exception $e) {
        Log::error($e->getMessage());
    }
}
```

---

## Step 4 — Test `tests/Feature/StoryEmailTriggersTest.php`

Rimuovere import `DevNewStoryCreated`. Aggiornare i due test oc:8040 per assertire `CustomerNewStoryCreated` in entrambi i casi.

```php
/** @test */
public function dev_creator_sends_new_story_mail_to_all_developers(): void
{
    Mail::fake();
    $creator = $this->makeDeveloper();

    Auth::login($creator);
    Story::query()->create([...]);

    Mail::assertSent(CustomerNewStoryCreated::class);
}

/** @test */
public function customer_creator_sends_new_story_mail_to_all_developers(): void
{
    Mail::fake();
    $customer = $this->makeCustomer();

    Auth::login($customer);
    Story::query()->create([...]);

    Mail::assertSent(CustomerNewStoryCreated::class);
}
```

---

## Step 5 — Verifica test

Dentro il container:

```bash
DB_DATABASE=orchestrator php artisan test --filter=StoryEmailTriggersTest
```

24 test verdi attesi.

---

## Commit convention

```
refactor(oc:8040): unify new story email into CustomerNewStoryCreated
```
