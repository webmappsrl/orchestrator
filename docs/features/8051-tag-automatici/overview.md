> Ticket: oc:8051

# [oc] tag automatici

## Cosa cambia

L'auto-tagging (quarter tag, customer tag, tag da testo) viene eseguito sia alla **creazione** che all'**aggiornamento** di una Story via Nova UI. Ogni chiamata al TagService è isolata in un proprio try/catch così un fallimento su una funzione non blocca le altre.

## Perché

Il commit `bcb68ad6` (oc:7972) ha rimosso `afterCreate`/`afterUpdate` da `Nova/Story.php` spostando la logica nell'observer (`created`) e nel controller API (`update`). La via Nova UI per gli update è rimasta però scoperta: `afterUpdate` fu rimosso ma non sostituito. Da quel momento l'aggiornamento di un ticket via Nova non aggiorna mai i tag automatici. Il try/catch monolitico in `StoryObserver::created()` causa inoltre fallimenti intermittenti silenziosi: un'eccezione in uno dei tre metodi blocca gli altri due.

## Requisiti

- [ ] `afterUpdate` ripristinato in `app/Nova/Story.php` con le stesse tre chiamate di `afterCreate`
- [ ] Ogni chiamata al TagService in `StoryObserver::created()` wrappata in try/catch indipendente
- [ ] Il log di un fallimento è `Log::error` (non `warning`) per renderlo visibile in monitoring
- [ ] Test Feature che verifica auto-tagging su `created` e su `updated` via Nova (quarter tag, customer tag, tag da testo)

## Rischi

- **Doppio tagging su Nova create**: `afterCreate` in Nova + observer `created()` entrambi chiamano le tre funzioni. Idempotente (`syncWithoutDetaching`), nessun effetto concreto. Accettato: era già così prima di oc:7972.
- **API update invariata**: `StoryController::update()` continua a chiamare `attachAutoTags()` esplicitamente. Nessun impatto.

## Out of scope

- Rimozione di tag che non sono più validi (es. URL rimosso dalla description)
- Retroactive tagging di ticket storici senza tag
- Modifica al `StoryController` API

## Moduli toccati

- `app/Nova/Story.php` — ripristino `afterUpdate` (e `afterCreate` se assente) con le tre chiamate TagService
- `app/Observers/StoryObserver.php` — isola try/catch in `created()`
- `tests/Feature/StoryAutoTaggingTest.php` — nuovo test Feature
