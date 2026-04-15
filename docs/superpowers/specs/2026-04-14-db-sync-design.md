# DB Sync & Backup Design

## Obiettivo

Automatizzare il backup del database in produzione e il sync da produzione verso tutti gli altri ambienti (dev, local, uat, staging).

## Comportamento per ambiente

- **Production**: lo scheduler esegue `db:upload_db_aws` ogni giorno alle 02:00 → crea dump e lo carica su AWS S3
- **Tutti gli altri ambienti**: lo scheduler esegue `db:sync` ogni giorno alle 03:00 → scarica il dump da S3 e ripristina il DB locale

## Infrastruttura esistente (wm-package)

I seguenti comandi esistono già e sono registrati nell'orchestrator tramite `WmPackageServiceProvider`:

- `db:upload_db_aws` — crea dump PostgreSQL e lo carica su S3 nel path `maphub/{app_name}/last-dump.sql.gz`
- `db:download` — scarica `last-dump.sql.gz` da S3 in `storage/app/database/last-dump.sql.gz`

## Nuovo comando: `db:sync`

**File:** `app/Console/Commands/SyncDatabaseCommand.php`  
**Signature:** `db:sync`  
**Description:** Scarica il dump di produzione da AWS S3 e lo ripristina nel database locale.

### Passi

1. **Guard** — se `APP_ENV === production` termina con errore (sicurezza)
2. **Download** — chiama `Artisan::call('db:download')`
3. **Restore** — esegue via `Symfony\Process`:
   ```
   gunzip -c storage/app/database/last-dump.sql.gz | psql -U {user} -h {host} -p {port} -d {database}
   ```
   Le credenziali vengono lette da `config('database.connections.pgsql')`.
4. **Log** — ogni step loggato via `Log::info()` e output su console, in linea con lo stile degli altri comandi wm-package.

## Scheduler

In `app/Console/Kernel.php`, dentro `schedule()`:

```php
if (app()->environment('production')) {
    $schedule->command('db:upload_db_aws')->dailyAt('02:00');
} else {
    $schedule->command('db:sync')->dailyAt('03:00');
}
```

## Variabili d'ambiente richieste

Su tutti gli ambienti (incluso locale per il download):

```
AWS_DUMPS_ACCESS_KEY_ID=
AWS_DUMPS_SECRET_ACCESS_KEY=
AWS_DUMPS_BUCKET=
```

`AWS_DEFAULT_REGION` già presente gestisce la region (default `eu-central-1`).

## Sicurezze

- `db:sync` non può girare in production (guard esplicito)
- `db:upload_db_aws` (wm-package) ha già un guard identico per non girare fuori da production
