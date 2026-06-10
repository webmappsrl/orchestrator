> Ticket: oc:8040

# Plan — Invio email a tutti i dev alla creazione di qualsiasi ticket

## Step 1 — Nuova mail class `app/Mail/DevNewStoryCreated.php`

Creare il file modellato su `CustomerNewStoryCreated`, con queste differenze:
- template: `mails.dev-new-story-created`
- subject: `[new][NomeCreatore]: TitoloTicket` (stesso formato)

```php
<?php

namespace App\Mail;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DevNewStoryCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $story;
    public $creator;

    public function __construct(Story $story)
    {
        $this->story = $story;
        $this->creator = $story->creator;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: '[new][' . $this->creator->name . ']: ' . $this->story->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.dev-new-story-created',
            with: [
                'story' => $this->story,
                'creator' => $this->creator,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
```

---

## Step 2 — Nuovo template `resources/views/mails/dev-new-story-created.blade.php`

Modellato su `customer-new-story-created.blade.php`, con:
- corpo: `description` con fallback a `customer_request`, fallback a testo neutro
- link: `/resources/stories/{id}` (rotta Nova per developer)

```blade
<!DOCTYPE html>
<html>
<head>
    <title>New Story Created</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { width: 100%; max-width: 600px; margin: 0 auto; }
        .header, .footer { background-color: #f5f5f5; padding: 16px; }
        .content { padding: 16px; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <strong>{{ $story->name }}</strong>
        </div>
        <div class="content">
            @if($story->description)
                {!! $story->description !!}
            @elseif($story->customer_request)
                {!! $story->customer_request !!}
            @else
                <p>Nessun dettaglio aggiunto.</p>
            @endif
            <p><a href="{{ url('/resources/stories/' . $story->id) }}">Ticket {{ $story->id }}</a></p>
        </div>
        <div class="footer">
            <p>Orchestrator©</p>
        </div>
    </div>
</body>
</html>
```

---

## Step 3 — Modifica `app/Models/Story.php` — hook `created()`

**File:** `app/Models/Story.php`
**Blocco da modificare:** hook `created()`, righe 168-177

### Codice attuale

```php
if ($user->hasRole(UserRole::Customer)) {
    $developers = User::whereJsonContains('roles', UserRole::Developer)->get();
    foreach ($developers as $developer) {
        try {
            Mail::to($developer->email)->send(new CustomerNewStoryCreated($story));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
```

### Codice dopo il fix

```php
$developers = User::whereJsonContains('roles', UserRole::Developer)->get();
$mailClass = $user->hasRole(UserRole::Customer)
    ? CustomerNewStoryCreated::class
    : DevNewStoryCreated::class;

foreach ($developers as $developer) {
    try {
        Mail::to($developer->email)->send(new $mailClass($story));
    } catch (\Exception $e) {
        Log::error($e->getMessage());
    }
}
```

Aggiungere l'import in testa al file:
```php
use App\Mail\DevNewStoryCreated;
```

---

## Step 4 — Test `tests/Feature/StoryEmailTriggersTest.php`

Aggiungere una nuova sezione in fondo al file. Usare `Mail::fake()` (non `Bus::fake()`) perché `CustomerNewStoryCreated` e `DevNewStoryCreated` usano `Mail::send()` direttamente.

Aggiungere l'import in testa al file:
```php
use Illuminate\Support\Facades\Mail;
use App\Mail\DevNewStoryCreated;
use App\Mail\CustomerNewStoryCreated;
```

### Test da aggiungere

```php
// =========================================================================
// OC:8040 — Notifica a tutti i dev alla creazione di qualsiasi ticket
// =========================================================================

/** @test */
public function dev_creator_sends_dev_mail_to_all_developers(): void
{
    Mail::fake();
    $creator = $this->makeDeveloper();
    $otherDev = $this->makeDeveloper();

    Auth::login($creator);
    Story::query()->create([
        'name' => 'Test story',
        'type' => StoryType::Helpdesk->value,
        'status' => StoryStatus::New->value,
        'customer_request' => '<p>hello</p>',
    ]);

    Mail::assertSent(DevNewStoryCreated::class);
}

/** @test */
public function customer_creator_still_sends_customer_mail_to_all_developers(): void
{
    Mail::fake();
    $customer = $this->makeCustomer();

    Auth::login($customer);
    Story::query()->create([
        'name' => 'Test story',
        'type' => StoryType::Helpdesk->value,
        'status' => StoryStatus::New->value,
        'customer_request' => '<p>hello</p>',
    ]);

    Mail::assertSent(CustomerNewStoryCreated::class);
    Mail::assertNotSent(DevNewStoryCreated::class);
}
```

---

## Step 5 — Verifica test

Dentro il container:

```bash
DB_DATABASE=orchestrator php artisan test --filter=StoryEmailTriggersTest
```

Tutti i test esistenti devono restare verdi. I due nuovi test devono passare.

---

## Commit convention

```
feat(oc:8040): notify all devs on story creation regardless of creator role
```
