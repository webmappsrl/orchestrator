@component('mail::message')
 # Ciao {{ $recipient->name }},

**Storia:** {{ $story->title }}

**Risposta da:** {{ $sender->name }}

**Risposta:**

{!! $response !!}

---

Puoi visualizzare la storia e tutte le risposte accedendo a questo [link]({{ url('resources/stories/' . $story->id) }}).

Cordiali saluti,

Il team di {{ config('app.name') }}
@endcomponent
