@component('mail::message')
    <!-- IMPORTANT: DO NOT INDENT MARKDOWN OR IT WILL NOT BE RENDERED CORRECTLY-->

    # Ciao {{ $recipient->name }},

    **Storia:** {{ $story->name }}

    **Risposta da:** {{ $sender->name }}

    **Risposta:**

    {!! $response !!}

    ---

    Puoi visualizzare la storia e tutte le risposte accedendo a questo [link]({{ url('resources/stories/' . $story->id) }}).

    Cordiali saluti,

    Il team di {{ config('app.name') }}
@endcomponent
