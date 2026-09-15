> Ticket: oc:8549

# API: PATCH story deve sostituire la description, non appenderla

## Cosa cambia

`PATCH /api/stories/{id}` smette di anteporre al valore esistente di `description` una nota
formattata (con firma, data e riquadro grigio) e comincia a sostituire semplicemente il valore
con quello ricevuto. Nessuna intestazione, nessuna firma, nessuna data, nessuna concatenazione.

Il fix riguarda solo `app/Http/Controllers\Api\StoryController.php::update()`: la chiamata
`$story->addDevNote($validated['description'], false)` viene sostituita con un'assegnazione
diretta `$story->description = $validated['description']`.

## Perché

Oggi chi corregge un refuso o un dato mancante nella `description` di una story ottiene due
versioni sovrapposte dello stesso testo, spesso in contraddizione, senza modo di capire quale
valga. L'unica alternativa attuale è cancellare il ticket e ricrearlo. `PATCH /api/tags/{id}`
sostituisce già regolarmente: l'incoerenza fra le due risorse è essa stessa fonte di errore.

## Requisiti

- [ ] `PATCH /api/stories/{id}` con `description` valorizzata sostituisce il campo con il valore
      ricevuto, senza alcuna formattazione aggiuntiva (niente firma, data, riquadro).
- [ ] `customer_request` non viene toccata: il suo comportamento di append (via `addResponse()`)
      resta identico a oggi.
- [ ] Il comportamento di append su `description` viene rimosso, non reso configurabile (nessun
      parametro/flag per scegliere append vs replace).

## Rischi

Il bug è confermato solo lato API (verificato dall'utente): l'editing di `description` da Nova
usa già un fill/save diretto (`descriptionField()` in `app/Traits/fieldTrait.php`, nessun
`fillUsing` verso `addDevNote()`) e non riproduce il problema. Il fix resta quindi confinato al
controller API, senza toccare `app/Nova/`.

## Out of scope

- Comportamento di `customer_request` / `addResponse()` — resta invariato.
- Qualunque modifica a `app/Nova/Story.php` o `app/Traits/fieldTrait.php` — l'editing da interfaccia
  non è affetto dal bug.
- Preservazione dello storico delle note già accumulate in `description` su story esistenti.

## Moduli toccati

- `app/Http/Controllers/Api/StoryController.php` (metodo `update()`)
