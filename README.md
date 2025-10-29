# ORCHESTRATOR

Introduction to ORCHSTRATOR (TBD)


## INSTALL

First of all install the [GEOBOX](https://github.com/webmappsrl/geobox) repo and configure the ALIASES command.
Replace `${instance name}` with the instance name (APP_NAME in .env file)

```sh
git clone git@github.com:webmappsrl/${instance name}.git orchestrator
git flow init
```

*Important NOTE*: remember to checkout the develop branch.

```sh
cd ${instance name}
bash docker/init-docker.sh
docker exec -u 0 -it php81_${instance name} bash
chown -R 33 storage
```

*Important NOTE*: if you have installed XDEBUG you need to create the xdebug.log file on the docker:

```bash
docker exec -u 0 -it php81_${instance name} bash
touch /var/log/xdebug.log
chown -R 33 /var/log/
```
Before running the install, ensure that the submodule are initialized and updated correctly with this command
```bash
git submodule update --init --recursive
```

At the end run install command to for this instance
```bash
geobox_install ${instance name}
```

*Important NOTE*: 
- Update your local repository of Geobox following its [Aliases instructions](https://github.com/webmappsrl/geobox#aliases-and-global-shell-variable). Make sure that you have set the environment variable GEOBOX_PATH correctly.
- Make sure that the version of wm-package of your instance is at leaset 1.1. Use command:
```bash
composer update wm/wp-package
```

Finally to import a fresh copy of database use Geobox restore command:

```bash
geobox_dump_restore ${instance name}
```


### Problemi noti

Durante l'esecuzione degli script potrebbero verificarsi problemi di scrittura su certe cartelle, questo perchè di default l'utente dentro il container è `www-data (id:33)` quando invece nel sistema host l'utente ha id `1000`. Ci sono 2 possibili soluzioni:

-   Chown/chmod della cartella dove si intende scrivere, eg:

    ```bash
      chown -R 33 storage
    ```
    NOTA: per eseguire il comando chown potrebbe essere necessario avere i privilegi di root. In questo caso si deve effettuare l'accesso al cointainer del docker utilizzando lo specifico utente root (-u 0). Questo è valido anche sbloccare la possibilità di scrivere nella cartella /var/log per il funzionamento di Xdedug

-   Utilizzare il parametro `-u` per il comando `docker exec` così da specificare l'id utente, eg come utente root (utilizzare `APP_NAME` al posto di `$nomeApp`):
    `bash
docker exec -u 0 -it php81_$nomeApp bash scripts/deploy_dev.sh
`

Xdebug potrebbe non trovare il file di log configurato nel .ini, quindi generare vari warnings

-   creare un file in `/var/log/xdebug.log` all'interno del container phpfpm. Eseguire un `chown www-data /var/log/xdebug.log`. Creare questo file solo se si ha esigenze di debug errori xdebug (impossibile analizzare il codice tramite breakpoint) visto che potrebbe crescere esponenzialmente nel tempo

## Scheduled Tasks Configuration

The orchestrator includes several scheduled tasks that can be enabled or disabled via environment variables in your `.env` file.

### Available Tasks

| Variable | Task | Schedule | Description |
|----------|------|----------|-------------|
| `ENABLE_STORY_PROGRESS_TO_TODO` | Story progress to todo | Daily at 18:00 | Moves stories from progress to todo status |
| `ENABLE_STORY_SCRUM_TO_DONE` | Story scrum to done | Daily at 16:00 | Processes scrum meetings stories |
| `ENABLE_SYNC_STORIES_CALENDAR` | Sync stories calendar | Daily at 07:45 | Syncs stories with Google Calendar |
| `ENABLE_STORY_AUTO_UPDATE_STATUS` | Story auto update status | Daily at 07:45 | Automatically updates story status |
| `ENABLE_PROCESS_INBOUND_EMAILS` | Process inbound emails | Every 5 minutes | Processes incoming emails and creates tickets |

### How to Enable Tasks

By default, all scheduled tasks are **disabled** (`false`). To enable a task:

1. Open your `.env` file
2. Add the corresponding environment variable with value `true`:

```bash
# Enable email processing (creates tickets from incoming emails)
ENABLE_PROCESS_INBOUND_EMAILS=true

# Enable story progress automation
ENABLE_STORY_PROGRESS_TO_TODO=true

# Enable scrum story processing
ENABLE_STORY_SCRUM_TO_DONE=true

# Enable calendar sync
ENABLE_SYNC_STORIES_CALENDAR=true

# Enable auto status update
ENABLE_STORY_AUTO_UPDATE_STATUS=true
```

3. Clear and rebuild the configuration cache:

```bash
docker-compose exec phpfpm php artisan config:cache
```

4. Verify the scheduled tasks:

```bash
docker-compose exec phpfpm php artisan schedule:list
```

### Running Tasks Manually

You can run any scheduled task manually using:

```bash
# Process inbound emails
docker-compose exec phpfpm php artisan orchestrator:process-inbound-emails

# Story progress to todo
docker-compose exec phpfpm php artisan story:progress-to-todo

# Story scrum to done
docker-compose exec phpfpm php artisan story:scrum-to-done

# Sync stories calendar
docker-compose exec phpfpm php artisan sync:stories-calendar

# Story auto update status
docker-compose exec phpfpm php artisan story:auto-update-status
```

### Cron Job Configuration

For scheduled tasks to run automatically, ensure Laravel's scheduler is configured in your crontab:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

Or run the scheduler manually:

```bash
docker-compose exec phpfpm php artisan schedule:run
```
