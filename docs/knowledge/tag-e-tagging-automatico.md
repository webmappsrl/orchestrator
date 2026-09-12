# Tag: API e tagging automatico

## Stato attuale

### API CRUD (oc:8155)
- `GET/POST/PATCH /api/tags`, `GET /api/tags/{tag}`, `POST/DELETE /api/tags/{tag}/stories/{story}`.
- **Il modello `Tag` ha due relazioni morfiche distinte**: `taggable()` (morphTo su
  `tags.taggable_type/id`, lega il tag a un parent come Project — **non toccare via API**) e
  `tagged()` (morphedByMany sul pivot `taggables` — è questa che si usa per attach/detach con
  Story).
- **Autorizzazione per ruolo nel controller**, solo `Developer` e `Admin`, via
  `abort_unless($user->hasRole(...))` nel metodo `authorizeRole()`. **`isAdmin()` non esiste su
  `User`**: il check corretto è `hasRole(UserRole::Admin)`.
- **Sanitize LIKE obbligatorio** prima di qualsiasi query sul nome tag:
  `str_replace(['%', '_'], ['\%', '\_'], $search)`.
- **Lo `StoryLog` di attach/detach è manuale** e va tenuto tale: è un log relazionale, non un cambio
  di campo del modello, quindi l'override di `Story::save()` non lo copre (vedi
  [Story: cambi di stato, log e notifiche](story-status-e-notifiche.md)). `changes` vale
  `['tag_attached' => $tag->id]` / `['tag_detached' => $tag->id]`.

### Tagging automatico da Nova (oc:8051)
- `afterCreate` e `afterUpdate` sono presenti in `app/Nova/Story.php`, con **try/catch isolati per
  ogni chiamata a `TagService`**: il blocco monolitico precedente faceva cadere tutte e tre le
  funzioni per una sola eccezione.
- `afterCreate` è un secondo livello idempotente sopra l'observer `created()`, che già garantiva il
  tagging da Nova.

## Come ci siamo arrivati

`afterCreate`/`afterUpdate` erano stati rimossi da Nova in oc:7972, lasciando completamente
scoperta la via Nova UI per gli update; la via API era già coperta da
`StoryController::attachAutoTags()`, e quella parte della scelta di oc:7972 resta valida e non è
stata toccata.
