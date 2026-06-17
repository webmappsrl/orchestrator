> Ticket: oc:8091

# Fix invio email per ticket di tipo Scrum

## Cosa cambia

Alla creazione di un ticket di tipo `Scrum`, il sistema non invia più la mail `CustomerNewStoryCreated` ai developer. Tutti gli altri tipi di ticket (`Bug`, `Feature`, `Help desk`) continuano a generare la notifica email come prima.

## Perché

I ticket Scrum sono ticket interni di gestione/pianificazione del team. Non rappresentano richieste di lavoro esterne né task assegnati a singoli developer, quindi le notifiche email sono rumore inutile che distrae dal lavoro reale.

## Requisiti

- [ ] Alla creazione di un ticket con `type = Scrum`, nessuna mail viene inviata ai developer
- [ ] La logica di assegnazione (`creator_id`, `tester_id`) rimane invariata anche per i ticket Scrum
- [ ] Per tutti gli altri tipi (`Bug`, `Feature`, `Help desk`) il comportamento email è identico a prima
- [ ] Un test di regressione verifica esplicitamente il blocco email per i ticket Scrum

## Rischi

- **Falso negativo sulla guardia**: se `$story->type` è `null` al momento del check (tipo non settato), la guardia non scatta e l'email viene inviata. Mitigazione: la guardia usa `=== StoryType::Scrum->value`, quindi `null` e altri tipi non vengono bloccati — comportamento corretto per design.
- **Regressione su altri tipi**: una guardia troppo larga potrebbe silenziare email su `Bug`/`Feature`. Mitigazione: check esplicito solo su `Scrum`, test che verifica i tipi non-Scrum continuano a inviare.

## Out of scope

- Email di cambio status (todo, testing, tested, released) — restano invariate anche per ticket Scrum
- Notifiche in-app Nova — non toccate
- Creazione ticket Scrum da parte di customer — impossibile per vincolo di ruolo, nessuna logica aggiuntiva necessaria

## Moduli toccati

- `app/Models/Story.php` — hook `created`: aggiunta guardia prima del loop di invio mail
- `tests/Feature/StoryEmailTriggersTest.php` — aggiunto test di regressione
