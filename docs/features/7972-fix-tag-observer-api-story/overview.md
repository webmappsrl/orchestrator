> Ticket: oc:7972

# Fix applicazione automatica tag via Observer nelle API Story

## Cosa cambia

Dopo questa fix, quando una Story viene creata o aggiornata tramite le API REST, i tag automatici (trimestre, customer, tag da testo) vengono applicati esattamente come avviene nel flusso Nova.

## Perché

Il metodo `attachAutoTags` (che chiama `TagService::attachQuarterTagToStory`, `attachCustomerTagToStory`, `attachTagsFromTextToStory`) è definito in `app/Nova/Story.php` e viene invocato solo dagli hook `afterCreate`/`afterUpdate` di Nova. Il controller API (`app/Http/Controllers/Api/StoryController.php`) non chiama questi metodi, quindi le story create/aggiornate via API non ricevono mai i tag automatici.

## Requisiti

- [ ] La creazione di una Story via API applica i tag automatici (trimestre, customer, testo)
- [ ] L'aggiornamento di una Story via API applica i tag automatici
- [ ] Il flusso Nova continua a funzionare esattamente come prima
- [ ] I tag manuali passati nel payload API (`tags[]`) non vengono sovrascritti dalla logica automatica

## Rischi

- **Doppia applicazione in Nova:** risolto con un unico commit atomico che aggiunge la logica nell'Observer e rimuove `afterCreate`/`afterUpdate` da Nova contemporaneamente.
- **Ordine operazioni nel controller:** `tags()->sync()` scatta dopo `save()`, che triggera l'Observer — i tag automatici verrebbero poi cancellati dal sync. Soluzione: nell'Observer gestire solo `created`; nel controller chiamare `attachAutoTags` esplicitamente **dopo** il sync manuale.
- **Eccezione in TagService blocca il save:** wrappare la chiamata in `try/catch` nell'Observer per evitare rollback della Story intera.
- **Trigger inutile su ogni update minore:** risolto non usando l'Observer per `updated`; il controller chiama `attachAutoTags` esplicitamente solo quando serve.

## Out of scope

- Modifica alla logica interna di `TagService`
- Aggiunta di tag automatici basati su nuove regole
- Migrazione dei tag storici su story già create via API

## Moduli toccati

- `app/Observers/StoryObserver.php` — aggiunta chiamata a `TagService` nell'evento `created` (con try/catch)
- `app/Nova/Story.php` — rimozione di `afterCreate`/`afterUpdate` (la logica passa all'Observer + controller)
- `app/Http/Controllers/Api/StoryController.php` — aggiunta chiamata esplicita ad `attachAutoTags` dopo `tags()->sync()` in `store()` e `update()`
