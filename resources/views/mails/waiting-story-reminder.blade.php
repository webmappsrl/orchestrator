@component('mail::message')
# Promemoria: La tua storia è in attesa

Caro cliente,

Il ticket "{{ $story->name }}" (ID: {{ $story->id }}) è in attesa del tuo input.

Per favore, accedi alla piattaforma per fornire le informazioni necessarie al completamento del ticket.

@component('mail::button', ['url' => url('/resources/story-showed-by-customers/' . $story->id)])
Visualizza Ticket
@endcomponent

Grazie per la tua attenzione.

Cordiali saluti,
Il team di {{ config('app.name') }}
@endcomponent
