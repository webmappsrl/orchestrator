# Database Initialization Script

## Overview

Lo script di inizializzazione `app:initialize-database` permette di svuotare completamente il database e ricreare i dati di base per l'applicazione Orchestrator.

## Utilizzo

### Comando base
```bash
docker compose exec phpfpm php artisan app:initialize-database
```

### Comando con conferma automatica
```bash
docker compose exec phpfpm php artisan app:initialize-database --force
```

## Cosa fa lo script

1. **🗑️ Svuota il database**: Rimuove tutti i dati dalle tabelle (tranne migrations)
2. **📋 Esegue le migrazioni**: Ricrea la struttura del database
3. **🌱 Crea i dati iniziali**:
   - Utente admin di base
   - Utenti developer (da configurazione)
   - Utenti customer (da configurazione)
   - Tag (da configurazione)

## Configurazione

I dati vengono letti dal file `config/initialization.php`. Aggiorna questo file con i tuoi dati di produzione:

### Utenti Developer
```php
'developers' => [
    [
        'name' => 'Nome Cognome',
        'email' => 'email@webmapp.it',
        'roles' => [UserRole::Developer, UserRole::Manager]
    ],
    // ... altri developer
],
```

### Utenti Customer
```php
'customers' => [
    [
        'name' => 'Nome Cliente',
        'email' => 'cliente@example.com'
    ],
    // ... altri customer
],
```

### Tag
```php
'tags' => [
    [
        'name' => 'Nome Tag',
        'description' => 'Descrizione del tag',
        'estimate' => 5
    ],
    // ... altri tag
],
```

## Credenziali di default

- **Admin**: `admin@webmapp.it` / `admin123`
- **Developer**: `developer123` (per tutti i developer)
- **Customer**: `customer123` (per tutti i customer)

## Sicurezza

⚠️ **ATTENZIONE**: Questo script elimina TUTTI i dati dal database. Usa solo in ambiente di sviluppo o quando necessario.

## Esempi di utilizzo

### Sviluppo locale
```bash
# Con conferma interattiva
docker compose exec phpfpm php artisan app:initialize-database

# Senza conferma (per script automatizzati)
docker compose exec phpfpm php artisan app:initialize-database --force
```

### Verifica dei dati creati
```bash
# Controlla gli utenti creati
docker compose exec phpfpm php artisan tinker --execute="echo json_encode(App\Models\User::all(['name', 'email', 'roles'])->toArray(), JSON_PRETTY_PRINT);"

# Controlla i tag creati
docker compose exec phpfpm php artisan tinker --execute="echo json_encode(App\Models\Tag::all(['name', 'description', 'estimate'])->toArray(), JSON_PRETTY_PRINT);"
```
