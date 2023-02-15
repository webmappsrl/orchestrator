# Laravel Postgis Boilerplate

Webmapp's Starting point

## Laravel 9 Project based on Nova 4

### Inizializzazione tramite boilerplate

-   Download del codice del boilerplate in una nuova cartella `nuovoprogetto` e disattivare il collegamento tra locale/remote:
    ```sh
    git clone https://github.com/webmappsrl/laravel-postgis-boilerplate.git nuovoprogetto
    cd nuovoprogetto
    git remote remove origin
    ```
-   Effettuare il link tra la repository locale e quella remota (repository vuota github)

    ```sh
    git remote add origin git@github.com:username/repo.git
    ```

-   Copy file `.env-example` to `.env`

    Questi valori nel file .env sono necessari per avviare l'ambiente docker. Hanno un valore di default e delle convenzioni associate, valutare la modifica:

    -   `APP_NAME` (it's php container name and - postgrest container name, no space)
    -   `DOCKER_PHP_PORT` (Incrementing starting from 9100 to 9199 range for MAC check with command "lsof -iTCP -sTCP:LISTEN")
    -   `DOCKER_SERVE_PORT` (always 8000)
    -   `DOCKER_PROJECT_DIR_NAME` (it's the folder name of the project)
    -   `DB_DATABASE`
    -   `DB_USERNAME`
    -   `DB_PASSWORD`

-   Creare l'ambiente docker
    ```sh
    bash docker/init-docker.sh
    ```
-   Digitare `y` durante l'esecuzione dello script per l'installazione di xdebug

-   Verificare che i container si siano avviati

    ```sh
    docker ps
    ```

-   Avvio di una bash all'interno del container php per installare tutte le dipendenze e lanciare il comando php artisan serve (utilizzare `APP_NAME` al posto di `$nomeApp`):

    ```sh
    docker exec -it php81_$nomeApp bash
    composer install
    php artisan key:generate
    php artisan optimize
    php artisan migrate
    php artisan serve --host 0.0.0.0
    ```

-   A questo punto l'applicativo è in ascolto su <http://127.0.0.1:8000>

### Configurazione xdebug

Una volta avviato il container con xdebug configurare il file `.vscode/launch.json` con la path della cartella del progetto sul sistema host. Eg:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9200,
            "pathMappings": {
                "/var/www/html/geomixer2": "${workspaceRoot}"
            }
        }
    ]
}
```

Aggiornare `/var/www/html/geomixer2` con la path della cartella sul proprio computer.

### Scripts

Ci sono vari scripts per il deploy nella cartella `scripts`. Per lanciarli basta lanciare una bash con la path dello script dentro il container php, eg (utilizzare `APP_NAME` al posto di `$nomeApp`):

```bash
docker exec -it php81_$nomeApp bash scripts/deploy_dev.sh
```

### Artisan commands

-   `db:dump_db`
    Create a new sql file exporting all the current database in the local disk under the `database` directory
-   `db:download`
    download a dump.sql from server
-   `db:restore`
    Restore a last-dump.sql file (must be in root dir)

### Problemi noti

Durante l'esecuzione degli script potrebbero verificarsi problemi di scrittura su certe cartelle, questo perchè di default l'utente dentro il container è `www-data (id:33)` quando invece nel sistema host l'utente ha id `1000`. Ci sono 2 possibili soluzioni:

-   Chown/chmod della cartella dove si intende scrivere, eg:

    ```bash
      chown -R 33 storage
    ```

-   Utilizzare il parametro `-u` per il comando `docker exec` così da specificare l'id utente, eg come utente root (utilizzare `APP_NAME` al posto di `$nomeApp`):
    ```bash
    docker exec -u 0 -it php81_$nomeApp bash scripts/deploy_dev.sh
    ```
