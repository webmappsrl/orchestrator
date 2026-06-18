> Ticket: oc:8051

# Notes — [oc] tag automatici

## Deviazioni dal piano

Nessuna deviazione rilevante.

## Bug trovati

- `app/Nova/Story.php` non aveva né `afterCreate` né `afterUpdate` — entrambi rimossi in oc:7972 e mai ripristinati. Il piano prevedeva di verificare la presenza di `afterCreate`; era assente.

## Decisioni

- **`afterUpdate` in Nova invece che `StoryObserver::updated()`**: scelto per rispettare la decisione di oc:7972 (non usare l'observer per update) e non introdurre doppio tagging sull'API. La via Nova UI è ora coperta da `afterUpdate`, la via API dal controller.
- **`afterCreate` aggiunto**: era assente da `Nova/Story.php` (rimosso in oc:7972). L'observer `created()` garantiva il tagging per i create Nova, ma `afterCreate` aggiunge un secondo livello di sicurezza idempotente.
- **Try/catch isolati anche in `Nova/Story.php::attachAutoTags`**: coerenza con la correzione all'observer — un fallimento su `attachCustomerTagToStory` non blocca `attachTagsFromTextToStory`.

## Follow-up

- **Logica dispersa in tre posti** (`StoryObserver::created`, `Nova/Story.php`, `StoryController`): debito tecnico accettato. Un refactor verso un trait o un metodo centralizzato ridurrebbe il rischio di dimenticare aggiornamenti futuri.
- **Bulk edit Nova**: `afterUpdate` gira per ogni ticket modificato in bulk. Con volumi alti potrebbe diventare lento. Valutare job asincrono (pattern di oc:8044) se si manifestano timeout.
- **Retroactive tagging**: i ticket storici senza tag non vengono toccati. Se serve, aggiungere un comando Artisan separato.
