> Ticket: oc:7977

# Notes — Invio email ticket al creator indipendentemente dal ruolo

## Deviazioni dal piano

### Ciclo 2 (2026-06-10) — secondo fix dopo PR #228

- **Root cause ciclo 2:** PR #228 aveva rimosso `hasRole(Customer)` ma lasciato guard `creator_id !== tester_id` e `creator_id !== user_id`. Per i developer il hook `created` auto-assegna `tester_id = creator_id`, quindi la guard bloccava sempre l'email. I test del ciclo 1 non coprivano questo scenario perché `makeStory()` non autentica l'utente alla creazione, bypassando l'auto-assign del tester.
- **Approccio scelto:** rimosse tutte le guard dal blocco creator-released, inclusa la self-notification. La logica è ora: "il creator riceve sempre l'email quando il ticket va in released, punto." Semplice e corretto.
- **Guard self-notification rimossa:** a differenza del piano originale, il creator riceve l'email anche se è lui stesso a mettere in released. Scelta deliberata: il creator vuole sempre sapere dello status change.

## Bug trovati

- **Bug latente preesistente (ciclo 1):** confronto enum/string in `$story->status === $releasedStatus` — sempre `false`. Risolto in PR #228.
- **Bug residuo (ciclo 2):** guard di deduplicazione `creator_id !== tester_id` bloccava l'email per tutti i developer-creator (tester auto-assegnato = creator in `created` hook). I test passavano perché non replicavano il comportamento reale del `created` hook.

## Decisioni

- La variabile `$customerRole` definita in `booted()` è rimasta nel closure anche dopo la rimozione del suo utilizzo — lasciata perché usata in altri blocchi del closure.
- Non modificato il hook `created` (auto-assign `tester_id = creator_id`): il bug era nella logica email, non nell'auto-assign.

## Follow-up

- **Audit log email:** nessun log delle email inviate esiste nel sistema. Valutare aggiunta log tabellare come tech debt separato.
- **DB test:** il database `orchestrator_test` richiedeva la creazione manuale + extension `pgvector` non disponibile su PostgreSQL 14. I test vanno runnati con `DB_DATABASE=orchestrator` (main DB, sicuro grazie a `DatabaseTransactions`). Documentare in CLAUDE.md.
