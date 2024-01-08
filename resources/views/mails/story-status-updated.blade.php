@component('mail::message')
# Aggiornamento di stato della storia [{{$story->id}}]({{ url('resources/stories/' . $story->id) }}).

Ciao {{ $user->name }},

Lo stato di una storia a te assegnata come {{$userType}} è stato aggiornato a <span style="background-color:{{ $colorMapping[$status] ?? 'black' }}; color: white; padding: 2px 4px;">{{ $status }}</span>.

@if (count($story->deadlines) > 0)
La storia è inserita nella deadline : [{{ $story->deadlines->first()->title }}]( {{ url('resources/deadlines/' . $story->deadlines->first()->id) }} ).
    
@endif
Puoi visualizzare i dettagli della storia a questo [link]({{ url('resources/stories/' . $story->id) }}).

Grazie,<br>
{{ config('app.name') }}
@endcomponent