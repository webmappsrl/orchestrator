@component('mail::message')
# Aggiornamento di stato della storia

Ciao {{ $user->name }},

Lo stato di una storia a te assegnata come {{$userType}} è stato aggiornato a {{ $story->status }}.

Puoi visualizzare i dettagli della storia a questo [link]({{ url('resources/stories/' . $story->id) }}).

Grazie,<br>
{{ config('app.name') }}
@endcomponent
