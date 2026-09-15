> Ticket: oc:8549

# Piano — API: PATCH story deve sostituire la description, non appenderla

## Task 1 — Sostituire `addDevNote()` con assegnazione diretta

File: `app/Http/Controllers/Api/StoryController.php`, metodo `update()`.

Sostituire:
```php
if (array_key_exists('description', $validated)) {
    $story->addDevNote($validated['description'], false);
}
```
con:
```php
if (array_key_exists('description', $validated)) {
    $story->description = $validated['description'];
}
```

Nessuna modifica a `customer_request` (resta `$story->addResponse(...)`), nessuna modifica a
`app/Models/Story.php` né a `app/Nova/`.

Commit: `fix(oc:8549): sostituisci description invece di appenderla in PATCH story`

## Task 2 — Aggiornare/aggiungere i test su `PATCH /api/stories/{id}`

File: `tests/Feature/Api/StoryApiTest.php`.

I test esistenti (`aggiorna_story_con_campi_validi`, `aggiorna_story_non_tocca_campi_non_passati`)
fanno il PATCH su `description` ma non asseriscono mai il contenuto esatto del campo dopo il
salvataggio — passerebbero identici sia con append che con replace. Aggiungere le asserzioni
mancanti perché il comportamento corretto sia effettivamente verificato dalla CI:

1. In `aggiorna_story_con_campi_validi`, aggiungere `assertDatabaseHas(['description' => 'Note aggiornate'])` (valore esatto, non un frammento con firma/data anteposta).
2. Nuovo test `aggiorna_story_description_sostituisce_valore_precedente`: crea una story con una `description` esistente, fa un PATCH con un nuovo valore, verifica che `description` nel db sia **esattamente** il nuovo valore (non lo contenga soltanto) — questo è il test che fallisce oggi e passa dopo il Task 1.

Commit: `fix(oc:8549): copri con test la sostituzione di description`

## Note

- `Story::addDevNote()` resta nel codice: dopo questo fix non ha più chiamanti in `app/`, ma il
  ticket non ne richiede la rimozione. Non toccarlo in questo ciclo (vedi `notes.md` per il
  follow-up).
- Nessuna migration, nessuna modifica a `StoryApiRequest` (la validazione `sometimes|nullable|string`
  di `description` resta invariata, non richiesta dal ticket).
