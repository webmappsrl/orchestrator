> Ticket: oc:8155

# Notes — API CRUD per Tag con attach/detach stories

## Deviazioni dal piano
- Il piano prevedeva 4 task separati (TagApiRequest, index/show, store/update, attach/detach). Tutti i file sono stati implementati in un'unica sessione senza step intermedi di commit — la logica è identica al piano.

## Bug trovati
- `isAdmin()` non esiste sul modello `User`: il metodo corretto è `hasRole(UserRole::Admin)`. Aggiornato in `TagController::authorizeRole()` e documentato nell'overview come decisione.
- Named argument `withStories: true` causava un diagnostic sintattico nell'IDE — sostituito con argomento posizionale `true`.
- Le route restituivano 404 durante i test a causa della cache delle route — risolto con `php artisan route:clear`.

## Decisioni
- **Nessun StoryLog per store/update del tag**: coerente con `StoryController` che non loga la modifica dei campi del ticket stesso.
- **Nessun versionamento API**: accettato consapevolmente, in linea con le API Story esistenti.
- **Autorizzazione via `hasRole()` nel controller**: nessun Gate registrato, stesso stile del resto del progetto.
- **Test con `DatabaseTransactions`**: coerente con tutti gli altri Feature test del progetto.

## Follow-up
- Se in futuro serve paginazione su `GET /api/tags`, aggiungere `?per_page=` al controller index.
- `DELETE /api/tags/{tag}` (eliminazione del tag) non è incluso — se necessario, aggiungere in un ticket separato.
