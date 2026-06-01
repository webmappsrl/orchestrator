> Ticket: oc:7977

# Invio email ticket al creator indipendentemente dal ruolo

## Cosa cambia

Quando lo status di una Story cambia a `released`, l'email di notifica viene inviata al `creator_id` **qualunque sia il suo ruolo** (customer, developer, admin, manager, editor). In precedenza l'email partiva solo se il creator aveva il ruolo `Customer`.

## Perché

Un developer che apre un ticket per conto proprio non riceveva alcuna notifica quando il ticket veniva rilasciato. Il creatore del ticket deve sempre essere informato dell'aggiornamento, indipendentemente dal suo ruolo nel sistema.

## Requisiti

- [ ] L'email al creator viene inviata su status → `released` per qualsiasi ruolo (rimozione del check `hasRole(Customer)`)
- [ ] Se `creator_id == user_id` (creator è anche l'assignee), viene inviata una sola email — non due
- [ ] Se `creator_id == tester_id` (creator è anche il tester), viene inviata una sola email — non due
- [ ] Test aggiuntivi in `StoryEmailTriggersTest`: creator developer riceve email su Released, deduplicazione creator=assignee, deduplicazione creator=tester

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
