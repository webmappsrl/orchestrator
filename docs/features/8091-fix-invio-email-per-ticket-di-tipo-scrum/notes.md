> Ticket: oc:8091

# Notes — Fix invio email per ticket di tipo Scrum

## Deviazioni dal piano

Nessuna deviazione strutturale. Il piano è stato seguito linearmente.

## Decisioni

- **`assertNothingSent()` invece di `assertNotSent(CustomerNewStoryCreated::class)`**: dopo la code review, rafforzata l'assertion del test per coprire qualsiasi mail class, non solo `CustomerNewStoryCreated`. Più aderente all'intenzione dichiarata dal nome del test.
- **Commento inline sulla guardia**: aggiunto `// Scrum tickets are internal planning tasks: no email notification on creation.` per rendere esplicito il perché del `return` anticipato in mezzo alla closure.

## Follow-up

- Il bypass customer via API (creazione ticket Scrum con type esplicito via `POST /api/stories`) è out of scope per questo ticket ma potrebbe essere indirizzato in futuro con una validation nel `StoryApiRequest`.
- Il campo `type` non ha un Eloquent cast nel modello `Story`. Se in futuro viene aggiunto `$casts = ['type' => StoryType::class]`, aggiornare la guardia per confrontare l'enum direttamente invece del valore stringa.
