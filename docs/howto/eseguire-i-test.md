# Eseguire i test

I test girano sul DB di supporto `orchestrator_test`, configurato in `phpunit.xml`: nessun override
di ambiente è necessario. **Non passare `DB_DATABASE=orchestrator`**, punterebbe al DB reale.

## Suite completa o singola classe

```bash
docker exec php81_orchestrator php artisan test
docker exec php81_orchestrator php artisan test --filter=TestClassName
```

La maggior parte dei test Feature usa `DatabaseTransactions` e fa quindi rollback automatico: su
61 file, 46 la usano. I restanti 15 no, e **10 di questi usano `RefreshDatabase`, che droppa e
rimigra il DB di test** — fra loro `StoryChildFieldTest`, `UserAccessNovaOverrideTest`,
`Api/StoryApiTest` e `Api/AuthApiTest`. Eseguirli è più lento e azzera i dati di
`orchestrator_test`: una ragione in più per non puntare mai al DB reale (locale, 2026-09-12).

## Se il DB di test resta indietro con le migration

```bash
docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"
```

## Ambiente di riferimento

PostgreSQL 17.5 con `pgvector` 0.8.2 e PostGIS 3.5.2; `orchestrator_test` esiste con tutte le
migration applicate.

## Note su singoli test

- `tests/Feature/StoryChildFieldTest.php` è **flaky e dipendente dall'ordine** nella suite completa:
  il fallimento si riproduce anche a codice invariato, ma non sempre. Non è una regressione
  introdotta dal lavoro in corso (verificato in oc:8505 eseguendo la suite più volte con e senza le
  modifiche del ticket).
- Lo stesso test richiede `Nova::resourcesIn(app_path('Nova'))` in `setUp()`: senza,
  `detailFields()` solleva `ResourceMissingException` sui campi `BelongsTo`.
