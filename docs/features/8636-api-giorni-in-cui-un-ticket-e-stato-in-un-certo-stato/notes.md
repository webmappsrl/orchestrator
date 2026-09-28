> Ticket: oc:8636

# Notes — API: giorni in cui un ticket è stato in un certo stato

## Divergenze dal piano, task per task

### Task 3 test

I 12 casi del piano sono diventati 15 test, perché tre casi sono divisi in due test ciascuno:
l'esempio del ticket (senza filtro e con `?status=progress`), il cambio d'ora (oc:2685 a ottobre,
oc:5090 a marzo) e gli errori di accesso (401 senza token, 404 per story inesistente).

## Bug trovati

Nessuno nel codice nuovo: i test sono passati alla prima esecuzione dopo l'implementazione.

## Decisioni

- **Tag Orchestrator rimandati alla chiusura del ticket** (decisione della dev in
  `environment-setup: tag-ambiente` e `overview: tag-contenuto`): nessun tag associato. Candidato
  trovato e non ancora associato: `orchestrator` (id 590).
- **Regola sull'ultimo periodo proposta e scartata.** Dalla challenge era emerso che circa 1239
  story hanno l'ultimo log di stato diverso da `stories.status`. Era stata proposta la chiusura
  dell'ultimo periodo a `stories.updated_at`; una revisione indipendente ha mostrato che
  `updated_at` è esatta per i passaggi Scrum `progress/todo → done` ma in ritardo fino a 5 mesi per
  circa 464 `released → done` (ricalcolo delle ore del 10/12/2024, oc:4432). La dev ha scartato la
  regola: sono ticket chiusi, e una riapertura scrive un log nuovo. Resta come limite noto, fissato
  dal test su oc:4689.
- **Primo periodo `new`, non `created`.** Allo scrum del 28/09 (14:08) Giuseppe ha accennato a
  «created»; la dev ha deciso di restare sul `new` del ticket.
- **Ticket dei test scelti dalla dev con Claude.** Allo scrum del 28/09 (14:08) Giuseppe ha detto
  «i ticket sceglieteli assieme», cioè la dev insieme a Claude. Una prima versione di overview e
  piano lo aveva letto come «insieme a Giuseppe»: corretto su indicazione della dev. Usati tutti
  quelli del Task 1; come ticket senza log di stato oc:836 (creato il 09/05/2023, in `testing`).
- **Test su ticket veri** (richiesta di Giuseppe allo scrum del 28/09, 14:08): fixture in
  `tests/Fixtures/story-status-history/`, ripulite dai testi dei clienti (di ogni riga di log resta
  il valore di `status` e il nome delle altre chiavi con valore `null`; tolto `updated_at`).
- **DB locale fermo a giugno 2025.** I log di stato del DB locale arrivano a giugno 2025; il
  «09/09/2026» inizialmente indicato come data del DB era attività locale. `db:sync` è fallito per
  credenziali AWS dei dump assenti nel `.env` (download mai partito, DB non toccato).
- **Stima**: 6h totali (misurato 2,09h + stimato 3,91h), valore scelto dalla dev su una stima
  `wm-estimate` di 8,34h.
- **Commit dell'overview** con prefisso `docs(oc:8636)`: il `CLAUDE.md` prevede `feat`/`fix`/
  `refactor`; il commit non è pushato e il messaggio si può correggere.

## Verifiche

- `php artisan test --filter=StoryStatusHistoryApiTest`: 15 passati.
- Suite completa: 639 passati, nessun fallimento.
- Specifica Scramble esportata: l'endpoint compare con i parametri `story`, `status`, i campi
  `story_id`, `timezone`, `current_status`, `intervals`, `days` e la risposta 422.
- Sul DB locale, per tutte le 2142 story con log di stato: nessun giorno di cambio stato assente da
  `days`, nessuna story senza il giorno di creazione. Controprova a mano su oc:5602 con
  `?status=progress`.

## Follow-up

- **Verifica su dati recenti**: servono le credenziali AWS dei dump per `db:sync`; poi ripetere la
  verifica su oc:8631 (indicato da Giuseppe) e su ticket recenti, e ricontrollare che dopo il
  01/07/2026 non esistano ticket con l'ultimo log diverso da `current_status`. Segnalare a Giuseppe
  ciò che non torna, compreso il limite noto (lui riteneva il DB già consistente).
- **Lato skill** (repo `claude-marketplace`, fuori da questo ticket): alle 18:00 i ticket in
  `progress` tornano automaticamente in `todo`; i cambi automatici non si distinguono da quelli
  manuali; «mai in quello stato» non si distingue da «nessun dato». Da considerare quando la skill
  userà l'endpoint.
