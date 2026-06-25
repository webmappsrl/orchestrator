> Ticket: oc:8136

# Notes — Auto-revert progress tickets when developer goes offline on Slack

## Deviazioni dal piano

- **`everyTwentyMinutes()` non esiste in questa versione di Laravel** — sostituito con `->cron('*/20 12-18 * * *')` che è equivalente.
- **`orchestrator_artisan@webmapp.it` gestito con `firstOrCreate`** — il piano assumeva che l'utente esistesse sempre, ma nel DB di test non era presente. Usato `firstOrCreate` nel comando per sicurezza.
- **Migration applicata via psql diretto** — il container aveva `wm/team-performance` mancante dal vendor che bloccava `php artisan`. Risolto con `composer install`, poi la migration è stata applicata manualmente via psql su entrambi i DB (`orchestrator` e `orchestrator_test`).

## Bug trovati

- **Slack User ID `D034TBTH5K2` era un DM channel ID**, non uno User ID. Gli User ID iniziano con `U`. Documentato nel piano per chi configura i developer.

## Decisioni

- **`->cron('*/20 12-18 * * *')`** invece di `everyTwentyMinutes()` — Laravel 10 non espone questo metodo helper.
- **`firstOrCreate` per system user** — più robusto di `->first()` che restituisce null in ambienti senza seeder.

## Follow-up

- Popolare `slack_user_id` per tutti i developer attivi (admin lo fa manualmente da Nova: profilo Slack → ⋯ → "Copia ID membro", deve iniziare con `U`).
- oc:8137 — StoryLog mancante in altri comandi schedulati (bug correlato scoperto durante questa feature).
