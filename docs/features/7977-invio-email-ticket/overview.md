> Ticket: oc:7977

# Invio email ticket al creator indipendentemente dal ruolo

## Cosa cambia

Quando lo status di una Story cambia a `released`, l'email di notifica viene inviata al `creator_id` **sempre e incondizionatamente** — qualunque sia il suo ruolo e chiunque abbia eseguito l'azione. In precedenza l'email partiva solo se il creator aveva il ruolo `Customer`, e guard di deduplicazione (creator=tester, creator=assignee) bloccavano erroneamente l'email in molti casi reali.

## Perché

Un developer che apre un ticket per conto proprio non riceveva alcuna notifica quando il ticket veniva rilasciato. La causa radice era duplice: (1) guard `hasRole(Customer)` rimosso nel ciclo precedente (PR #228), ma (2) le guard di deduplicazione `creator_id !== tester_id` e `creator_id !== user_id` bloccavano comunque l'email perché per i developer il hook `created` auto-assegna `tester_id = creator_id`. Il creatore del ticket deve sempre essere informato del rilascio.

## Requisiti

- [x] L'email al creator viene inviata su status → `released` per qualsiasi ruolo
- [x] L'email al creator viene inviata anche se creator == tester (caso reale: developer crea ticket)
- [x] L'email al creator viene inviata anche se creator == assignee
- [x] L'email al creator viene inviata anche se è il creator stesso a mettere in released
- [x] Test di regressione: `creator_receives_no_duplicate_email_when_also_tester` → `assertCount(1)` (non più `assertLessThanOrEqual`)
- [x] Nuovo test canary: `creator_receives_email_when_tester_sets_released`

## Rischi

- **Aumento volume email**: rimuovendo il filtro sul ruolo, nuovi utenti (developer, admin, manager) riceveranno email che prima non arrivavano. Mitigato dalla deduplicazione e dal fatto che il trigger rimane limitato al solo status `released`.
- **Regressione sul caso customer-creator esistente**: la modifica tocca la stessa condizione usata per i customer. Mitigato mantenendo i test esistenti verdi.

## Out of scope

- Notifiche push o in-app al creator (solo email)
- Cambio del comportamento su status diversi da `released`
- Modifica al template email `StoryStatusUpdate`

## Moduli toccati

- `app/Models/Story.php` — condizione nel hook `saved()`, righe 64-73
- `tests/Feature/StoryEmailTriggersTest.php` — nuovi test cases
