# PDF Export Troubleshooting Guide

## Problema: PDF Export non funziona in produzione

Se l'export PDF della documentazione non funziona in produzione, seguire questa guida per diagnosticare e risolvere il problema.

### 1. Verifica che il file sia stato deployato

Controlla che il controller esista in produzione:

```bash
# Verifica che il controller esista
ls -la app/Http/Controllers/DocumentationPdfController.php

# Verifica che la route sia registrata
php artisan route:list | grep documentation-pdf
```

**Soluzione**: Se il file non esiste, assicurati di aver fatto il pull del codice più recente e di aver eseguito il deploy.

### 2. Pulisci tutte le cache

Le cache di Laravel possono causare problemi dopo un deploy:

```bash
# Pulisci tutte le cache
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Ricarica le ottimizzazioni
php artisan optimize
```

**Soluzione**: Esegui questi comandi dopo ogni deploy in produzione.

### 3. Verifica la configurazione

Controlla che le variabili d'ambiente siano configurate correttamente:

```bash
# Verifica le variabili nel .env
grep PDF_FOOTER .env
grep PDF_LOGO_PATH .env
```

**Verifica i valori**:
- `PDF_FOOTER`: Deve contenere il testo del footer (supporta `<br>` per interruzioni di riga)
- `PDF_LOGO_PATH`: Deve contenere il percorso assoluto al file logo, o lasciare commentato per usare il default

**Default** (se non specificato):
- `PDF_FOOTER`: Viene usato il valore di default in `config/orchestrator.php`
- `PDF_LOGO_PATH`: Default è `storage/app/pdf-logo/logo.png`

### 4. Verifica i permessi delle directory DomPDF (IMPORTANTE in Docker)

DomPDF ha bisogno di directory scrivibili per:
- Font e cache dei font: `storage/app/dompdf/fonts`
- File temporanei: `storage/app/dompdf/tmp`

**In Docker, questo è spesso la causa principale del problema!**

```bash
# Verifica che le directory esistano
ls -la storage/app/dompdf/fonts
ls -la storage/app/dompdf/tmp

# Verifica i permessi
ls -ld storage/app/dompdf/fonts
ls -ld storage/app/dompdf/tmp

# Crea le directory se non esistono (con permessi corretti)
mkdir -p storage/app/dompdf/fonts storage/app/dompdf/tmp
chmod -R 755 storage/app/dompdf
```

**Soluzione Docker**:
- Il controller ora crea automaticamente le directory se non esistono
- Se il problema persiste, verifica i permessi dal container:
  ```bash
  docker-compose exec phpfpm ls -la storage/app/dompdf
  docker-compose exec phpfpm chmod -R 755 storage/app/dompdf
  ```

### 5. Verifica il percorso del logo

Se hai configurato un logo personalizzato:

```bash
# Verifica che il file esista
ls -la storage/app/pdf-logo/logo.png

# O se hai un percorso personalizzato
ls -la /path/to/your/logo.png

# Verifica i permessi
chmod 644 storage/app/pdf-logo/logo.png
```

**Soluzione**: 
- Se la directory non esiste, creala: `mkdir -p storage/app/pdf-logo`
- Assicurati che il file abbia i permessi corretti (leggibile dal webserver)

### 6. Controlla i log

Il controller ora registra errori dettagliati nei log:

```bash
# Visualizza gli ultimi errori
tail -n 100 storage/logs/laravel.log | grep -i pdf

# Oppure filtra per DocumentationPdfController
tail -n 100 storage/logs/laravel.log | grep DocumentationPdfController
```

**Messaggi di log comuni**:
- `PDF Logo path does not exist`: Il percorso del logo non è corretto (non è un errore critico, il PDF viene generato senza logo)
- `Error loading PDF logo`: Errore durante il caricamento del logo
- `Error generating PDF for documentation`: Errore durante la generazione del PDF
- `Documentation not found for PDF export`: La documentazione con l'ID specificato non esiste

### 7. Verifica il middleware Nova

La route è protetta dal middleware `nova`. Verifica che:

1. L'utente sia autenticato in Nova
2. L'utente abbia i permessi per accedere alla risorsa Documentation

**Problema comune**: Se vedi un errore 403 o un redirect alla login, il middleware Nova sta bloccando la richiesta.

### 8. Test manuale della route

Testa manualmente la route in produzione:

```bash
# Sostituisci {id} con un ID valido di documentazione
curl -I https://your-domain.com/download-documentation-pdf/{id} \
  -H "Cookie: your-session-cookie"
```

O naviga direttamente all'URL nel browser dopo aver fatto login in Nova.

### 9. Verifica DomPDF

Se il problema persiste, potrebbe essere un problema con DomPDF:

```bash
# Verifica che DomPDF sia installato
composer show barryvdh/laravel-dompdf

# Controlla i requisiti di sistema
php -m | grep -i gd
php -m | grep -i mbstring
php -m | grep -i xml
```

**Requisiti DomPDF**:
- PHP GD extension
- PHP MBString extension
- PHP XML extension

### 10. Problemi comuni e soluzioni

#### Errore: "Permission denied" o directory non scrivibile (Docker)
- **Causa**: Le directory DomPDF non esistono o non hanno i permessi corretti
- **Soluzione Docker**: 
  ```bash
  docker-compose exec phpfpm mkdir -p storage/app/dompdf/fonts storage/app/dompdf/tmp
  docker-compose exec phpfpm chmod -R 755 storage/app/dompdf
  ```
- **Soluzione Produzione**: Lo script di deploy ora crea automaticamente queste directory. Se il problema persiste, esegui manualmente i comandi sopra.

#### Errore 404 - Route non trovata
- **Causa**: Route non registrata o cache delle route non pulita
- **Soluzione**: Esegui `php artisan route:clear && php artisan optimize`

#### Errore 500
- **Causa**: Errore PHP durante la generazione del PDF
- **Soluzione**: Controlla i log (`storage/logs/laravel.log`) per il dettaglio dell'errore

#### PDF vuoto o malformato
- **Causa**: Problema con il contenuto HTML o con DomPDF
- **Soluzione**: Verifica che il contenuto della documentazione sia valido HTML

#### Logo non appare nel PDF
- **Causa**: Percorso del logo errato o file non esistente
- **Soluzione**: Verifica il percorso e i permessi del file (vedi punto 4)

### 11. Deploy corretto

Per assicurarti che tutto funzioni dopo il deploy, esegui questo script:

```bash
#!/bin/bash
# Script di verifica post-deploy

echo "Verificando PDF Export..."

# 1. Verifica file
if [ ! -f "app/Http/Controllers/DocumentationPdfController.php" ]; then
    echo "ERRORE: Controller non trovato!"
    exit 1
fi

# 2. Pulisci cache
php artisan optimize:clear
php artisan route:clear
php artisan config:clear

# 3. Verifica route
if ! php artisan route:list | grep -q "documentation-pdf"; then
    echo "ERRORE: Route non registrata!"
    exit 1
fi

# 4. Ricarica ottimizzazioni
php artisan optimize

echo "Verifica completata!"
```

### Comandi rapidi per la risoluzione

```bash
# Reset completo delle cache
php artisan optimize:clear && php artisan optimize

# Verifica route
php artisan route:list | grep documentation

# Controlla log
tail -f storage/logs/laravel.log
```

### Contatto

Se il problema persiste dopo aver seguito questa guida, consulta i log dettagliati in `storage/logs/laravel.log` e verifica che tutte le dipendenze siano installate correttamente.

