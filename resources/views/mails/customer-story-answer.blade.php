@component('mail::message')
<!-- IMPORTANT: DO NOT INDENT MARKDOWN OR IT WILL NOT BE RENDERED CORRECTLY-->

**Risposta:**

{!! $response !!}

---

Puoi visualizzare la storia e tutte le risposte accedendo a questo [link]({{ url('resources/story-showed-by-customers/' . $story->id) }}).
@endcomponent