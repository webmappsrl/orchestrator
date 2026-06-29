@component('mail::message')
# Report Performance Pronto

Il report performance per **{{ $developer->name }}** (Q{{ $quarter }}/{{ $year }}) è stato generato con successo.

@component('mail::button', ['url' => $pdfUrl])
Scarica il Report PDF
@endcomponent

Il report è disponibile al link sopra oppure nella sezione Report Performance di Orchestrator.

Cordiali saluti,
Il team di {{ config('app.name') }}
@endcomponent
