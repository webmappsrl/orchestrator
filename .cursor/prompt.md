# Prompt Template per Orchestra (Laravel Nova Ticket System)

## Contesto del Progetto
Sei un assistente AI che lavora su **Orchestra**, un sistema di gestione ticket basato su **Laravel Nova**. Il progetto utilizza Docker per lo sviluppo locale.

## Stack Tecnologico
- **Backend**: Laravel 10+ (PHP 8.1)
- **Admin Panel**: Laravel Nova 4
- **Database**: PostgreSQL + PostGIS
- **Cache**: Redis
- **Mail**: Mailpit per lo sviluppo locale
- **Containerizzazione**: Docker Compose
- **Framework Frontend**: Blade templates

## Struttura del Progetto
```
app/
├── Models/          # Eloquent models
├── Nova/           # Risorse Nova (Resources, Filters, Actions, etc.)
├── Traits/         # Traits condivisi
├── Enums/          # Enumerazioni (StoryStatus, UserRole, etc.)
├── Jobs/           # Queue jobs
├── Mail/           # Mailables
└── Console/        # Artisan commands

resources/views/    # Blade templates
database/migrations/ # Database migrations
config/             # File di configurazione
```

## Convenzioni di Codice

### Commit Messages
Usa **Conventional Commits**:
- `feat:` per nuove feature
- `fix:` per bug fix
- `refactor:` per refactoring
- `docs:` per documentazione
- `chore:` per task di manutenzione

### Docker Usage
**SEMPRE** usa Docker per i comandi Artisan:
```bash
docker-compose exec phpfpm php artisan <command>
```

Non usare mai `php artisan` direttamente dalla shell host.

### Naming Conventions
- **Storie/Ticket**: utilizzare il termine "Ticket" nelle interfacce utente
- **Ruoli**: `UserRole` enum (Admin, Developer, Manager, Customer)
- **Stati**: `StoryStatus` enum (New, Assigned, Todo, Progress, Testing, Tested, Waiting, Problem, Done, Rejected, Backlog, Released)

## Workflow Tipici

### Aggiungere un Campo a una Tabella
1. Creare migration: `docker-compose exec phpfpm php artisan make:migration add_field_to_table --table=table_name`
2. Modificare il Model aggiungendo il campo a `$fillable`
3. Aggiornare le risorse Nova se necessario
4. Eseguire la migration: `docker-compose exec phpfpm php artisan migrate`

### Modificare Interfacce Nova
1. Modificare la Resource in `app/Nova/`
2. Usare i Traits in `app/Traits/fieldTrait.php` per i campi comuni
3. Pulire la cache: `docker-compose exec phpfpm php artisan optimize:clear`

### Gestione Email
Le email sono configurate in `app/Mail/` e vengono inviate tramite Jobs in `app/Jobs/`.
Mailpit è configurato per lo sviluppo locale (porta 8025).

### Database Migrations
Le migrations devono essere applicate con Docker:
```bash
docker-compose exec phpfpm php artisan migrate
```

Per rollback:
```bash
docker-compose exec phpfpm php artisan migrate:rollback
```

## Note Importanti

1. **Tester Id**: Usa sempre `tester_id` per il tester, non `tester`
2. **Creator Id**: Usa sempre `creator_id` per il creatore
3. **User Id**: Rappresenta lo sviluppatore assegnato al ticket
4. **Validation**: Le validazioni custom vanno in `app/Models/Story::boot()`
5. **Nova Fields**: Usa sempre `$this->resource->field_name` per accedere ai dati nella Resource
6. **Cache**: Dopo modifiche a configurazioni, sempre pulire la cache

## Best Practices

- Mantenere il codice DRY usando Traits
- Usare Type Hints ovunque possibile
- Documentare metodi complessi con PHPDoc
- Testare le modifiche prima di committare
- Seguire il pattern Repository quando necessario
- Usare Enums invece di stringhe per stati e ruoli

## Comandi Utili

```bash
# Pulizia cache completa
docker-compose exec phpfpm php artisan optimize:clear

# Cache della configurazione
docker-compose exec phpfpm php artisan config:cache

# Visualizzare le tabelle in docker
docker-compose ps

# Log dell'applicazione
docker-compose exec phpfpm tail -n 100 storage/logs/laravel.log

# Tinker
docker-compose exec phpfpm php artisan tinker

# Comandi custom del progetto
docker-compose exec phpfpm php artisan orchestrator:process-inbound-emails
```

## Struttura Git

- **Branch principale**: `main`
- **Branch sviluppo**: `montagna-servizi`
- **Convention**: Conventional Commits per tutti i commit

