# Deploy Produzione

Questo template automatizza il processo di deploy in produzione del progetto Orchestra.

## 📋 Prerequisiti

- Accesso al repository Git
- Docker e Docker Compose installati e funzionanti
- Accesso al server di produzione
- File `.env` presente nella root del progetto

## 🚀 Processo di Deploy

### Step 1: Spostati sul branch montagna-servizi

```bash
# Verifica il branch corrente
git branch --show-current

# Spostati sul branch montagna-servizi
git checkout montagna-servizi

# Verifica che il cambio sia avvenuto correttamente
git branch --show-current
```

**Verifica**: Il comando deve restituire `montagna-servizi`

### Step 2: Controlla che Docker sia funzionante

```bash
# Verifica lo stato dei container Docker
docker-compose ps

# Verifica che almeno il container phpfpm sia in esecuzione
if ! docker-compose ps | grep -q "phpfpm.*Up"; then
    echo "❌ ERRORE: Docker non è funzionante o il container phpfpm non è attivo"
    echo "Controlla lo stato con: docker-compose ps"
    echo "Avvia i container con: docker-compose up -d"
    exit 1
fi

echo "✅ Docker è funzionante"
```

**Se Docker non è funzionante**: Ferma la procedura e chiedi all'utente cosa fare. Possibili azioni:
- Avviare i container con `docker-compose up -d`
- Verificare la configurazione Docker
- Controllare i log con `docker-compose logs`

### Step 3: Esegui artisan down tramite Docker

```bash
# Metti l'applicazione in modalità manutenzione
docker-compose exec phpfpm php artisan down

# Verifica che il comando sia stato eseguito correttamente
if [ $? -eq 0 ]; then
    echo "✅ Applicazione messa in modalità manutenzione"
else
    echo "❌ ERRORE: Impossibile mettere l'applicazione in modalità manutenzione"
    exit 1
fi
```

### Step 4: Fai pull all'ultimo tag disponibile

```bash
# Recupera tutti i tag dal repository remoto
git fetch --tags

# Trova l'ultimo tag che inizia con MS-
LAST_TAG=$(git tag --sort=-version:refname | grep "^MS-" | head -1)

if [ -z "$LAST_TAG" ]; then
    echo "❌ ERRORE: Nessun tag MS-* trovato"
    exit 1
fi

echo "📦 Ultimo tag trovato: $LAST_TAG"

# Verifica che il tag esista nel repository remoto
if ! git tag -l | grep -q "^$LAST_TAG$"; then
    echo "⚠️  Il tag $LAST_TAG non è presente localmente, recupero dal remoto..."
    git fetch origin tag $LAST_TAG
fi

# Fai checkout del tag
git checkout $LAST_TAG

# Verifica che il checkout sia avvenuto correttamente
CURRENT_TAG=$(git describe --tags --exact-match HEAD 2>/dev/null)
if [ "$CURRENT_TAG" = "$LAST_TAG" ]; then
    echo "✅ Checkout del tag $LAST_TAG completato"
else
    echo "❌ ERRORE: Il checkout del tag non è avvenuto correttamente"
    exit 1
fi
```

### Step 5: Esegui lo script deploy_prod.sh tramite Docker

```bash
# Esegui lo script di deploy dentro il container Docker
docker-compose exec phpfpm bash scripts/deploy_prod.sh

# Verifica che lo script sia stato eseguito correttamente
if [ $? -eq 0 ]; then
    echo "✅ Deploy script eseguito con successo"
else
    echo "❌ ERRORE: Lo script di deploy ha restituito un errore"
    echo "Controlla i log con: docker-compose exec phpfpm tail -n 100 storage/logs/laravel.log"
    exit 1
fi
```

**Nota**: Lo script `deploy_prod.sh` contiene già `php artisan down` all'inizio, ma viene eseguito anche prima come richiesto per garantire che l'applicazione sia in modalità manutenzione prima di iniziare il deploy.

### Step 6: Controllo incrociato tra .env-example e .env

```bash
# Verifica che i file esistano
if [ ! -f .env-example ]; then
    echo "❌ ERRORE: File .env-example non trovato"
    exit 1
fi

if [ ! -f .env ]; then
    echo "⚠️  File .env non trovato, creazione da .env-example..."
    cp .env-example .env
    echo "✅ File .env creato da .env-example"
fi

# Estrai le variabili da .env-example (chiavi prima del =)
ENV_EXAMPLE_VARS=$(grep -E "^[A-Z_]+=" .env-example | cut -d'=' -f1 | sort)

# Estrai le variabili da .env (chiavi prima del =)
ENV_VARS=$(grep -E "^[A-Z_]+=" .env | cut -d'=' -f1 | sort)

# Trova le variabili presenti in .env-example ma non in .env
MISSING_VARS=$(comm -23 <(echo "$ENV_EXAMPLE_VARS") <(echo "$ENV_VARS"))

if [ -z "$MISSING_VARS" ]; then
    echo "✅ Tutte le variabili di .env-example sono presenti in .env"
    NEW_VARS_ADDED=false
else
    echo "⚠️  Variabili presenti in .env-example ma non in .env:"
    echo "$MISSING_VARS"
    echo ""
    NEW_VARS_ADDED=true
fi
```

### Step 7: Crea backup del file .env

```bash
# Crea un backup del file .env prima di modificarlo
if [ "$NEW_VARS_ADDED" = true ]; then
    BACKUP_FILE=".env.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Crea il backup
    cp .env "$BACKUP_FILE"
    
    if [ $? -eq 0 ]; then
        echo "✅ Backup del file .env creato: $BACKUP_FILE"
        echo "   In caso di problemi, puoi ripristinare con: cp $BACKUP_FILE .env"
    else
        echo "❌ ERRORE: Impossibile creare il backup del file .env"
        echo "   Interrompo la procedura per sicurezza"
        exit 1
    fi
else
    echo "ℹ️  Nessuna modifica necessaria al file .env, backup non creato"
fi
```

### Step 8: Inserimento variabili mancanti

```bash
# Se ci sono variabili mancanti, chiedi all'utente di inserirle
if [ "$NEW_VARS_ADDED" = true ]; then
    echo "📝 Inserimento variabili mancanti..."
    echo ""
    
    # Conta le variabili mancanti
    MISSING_COUNT=$(echo "$MISSING_VARS" | wc -l | tr -d ' ')
    CURRENT=0
    
    # Per ogni variabile mancante, chiedi il valore
    while IFS= read -r VAR_NAME; do
        CURRENT=$((CURRENT + 1))
        echo "[$CURRENT/$MISSING_COUNT] Variabile: $VAR_NAME"
        
        # Leggi il valore di esempio da .env-example se presente
        EXAMPLE_VALUE=$(grep "^$VAR_NAME=" .env-example | cut -d'=' -f2- | sed 's/^"\(.*\)"$/\1/')
        
        if [ -n "$EXAMPLE_VALUE" ]; then
            echo "   Valore di esempio: $EXAMPLE_VALUE"
        fi
        
        # Chiedi all'utente il valore
        read -p "   Inserisci il valore per $VAR_NAME (premi Invio per usare il valore di esempio o 'skip' per saltare): " USER_VALUE
        
        # Gestisci il valore inserito
        if [ -z "$USER_VALUE" ] && [ -n "$EXAMPLE_VALUE" ]; then
            # Usa il valore di esempio
            VALUE_TO_ADD="$EXAMPLE_VALUE"
            echo "   ✅ Usato valore di esempio: $VALUE_TO_ADD"
        elif [ "$USER_VALUE" = "skip" ]; then
            echo "   ⏭️  Variabile saltata"
            continue
        elif [ -n "$USER_VALUE" ]; then
            VALUE_TO_ADD="$USER_VALUE"
        else
            echo "   ⚠️  Nessun valore inserito, variabile saltata"
            continue
        fi
        
        # Aggiungi la variabile a .env
        # Se il valore contiene spazi o caratteri speciali, aggiungilo tra virgolette
        if [[ "$VALUE_TO_ADD" =~ [[:space:]] ]] || [[ "$VALUE_TO_ADD" =~ [\$\`] ]]; then
            echo "$VAR_NAME=\"$VALUE_TO_ADD\"" >> .env
        else
            echo "$VAR_NAME=$VALUE_TO_ADD" >> .env
        fi
        
        echo "   ✅ Variabile $VAR_NAME aggiunta a .env"
        echo ""
    done <<< "$MISSING_VARS"
    
    echo "✅ Inserimento variabili completato"
else
    echo "ℹ️  Nessuna variabile da inserire"
fi
```

**Nota**: Il processo è interattivo e chiede all'utente il valore per ogni variabile mancante. L'utente può:
- Inserire un valore personalizzato
- Premere Invio per usare il valore di esempio da `.env-example`
- Digitare `skip` per saltare una variabile

### Step 9: Pulisci la cache se sono state inserite nuove variabili

```bash
# Pulisci la cache solo se sono state aggiunte nuove variabili
if [ "$NEW_VARS_ADDED" = true ]; then
    echo "🧹 Pulizia cache dopo inserimento nuove variabili..."
    
    docker-compose exec phpfpm php artisan optimize:clear
    
    if [ $? -eq 0 ]; then
        echo "✅ Cache pulita con successo"
    else
        echo "❌ ERRORE: Impossibile pulire la cache"
        exit 1
    fi
else
    echo "ℹ️  Nessuna nuova variabile inserita, cache non pulita"
fi
```

## ✅ Verifica Finale

Dopo il deploy, verifica che tutto funzioni correttamente:

```bash
# Verifica che l'applicazione sia online
docker-compose exec phpfpm php artisan up

# Controlla lo stato di Horizon
docker-compose exec phpfpm php artisan horizon:status

# Verifica i log per eventuali errori
docker-compose exec phpfpm tail -n 50 storage/logs/laravel.log

# Verifica che i container siano tutti attivi
docker-compose ps
```

## 🔍 Troubleshooting

### Docker non si avvia
```bash
# Controlla i log
docker-compose logs

# Riavvia i container
docker-compose restart

# Se necessario, ricostruisci i container
docker-compose up -d --build
```

### Errore durante il deploy script
```bash
# Controlla i log dell'applicazione
docker-compose exec phpfpm tail -n 100 storage/logs/laravel.log

# Verifica lo stato delle migrazioni
docker-compose exec phpfpm php artisan migrate:status

# Verifica la connessione al database
docker-compose exec phpfpm php artisan tinker --execute="echo DB::connection()->getPdo();"
```

### Problemi con le variabili .env
```bash
# Ripristina il file .env dal backup (sostituisci con il nome del backup effettivo)
cp .env.backup.YYYYMMDD_HHMMSS .env

# Verifica che il file .env sia valido
docker-compose exec phpfpm php artisan config:clear
docker-compose exec phpfpm php artisan config:cache

# Controlla che tutte le variabili necessarie siano presenti
docker-compose exec phpfpm php artisan tinker --execute="echo config('app.name');"
```

## 📝 Note Importanti

1. **Backup**: Prima di eseguire il deploy, assicurati di avere un backup del database e dei file importanti
2. **Backup .env**: Un backup automatico del file `.env` viene creato prima di qualsiasi modifica con il formato `.env.backup.YYYYMMDD_HHMMSS`
3. **Ripristino .env**: In caso di problemi, puoi ripristinare il file `.env` dal backup con: `cp .env.backup.YYYYMMDD_HHMMSS .env`
4. **Modalità manutenzione**: L'applicazione viene messa in modalità manutenzione all'inizio del processo
5. **Tag**: Il processo usa l'ultimo tag disponibile che inizia con `MS-`
6. **Variabili .env**: Le nuove variabili vengono aggiunte alla fine del file `.env`
7. **Cache**: La cache viene pulita automaticamente solo se sono state aggiunte nuove variabili

## 🎯 Comando Completo (Script)

Per eseguire tutto il processo in un'unica volta, puoi creare uno script bash che esegue tutti i passaggi:

```bash
#!/bin/bash
set -e

# Step 1: Branch montagna-servizi
echo "📌 Step 1: Cambio branch..."
git checkout montagna-servizi

# Step 2: Verifica Docker
echo "🐳 Step 2: Verifica Docker..."
if ! docker-compose ps | grep -q "phpfpm.*Up"; then
    echo "❌ ERRORE: Docker non è funzionante"
    exit 1
fi

# Step 3: Artisan down
echo "🔒 Step 3: Artisan down..."
docker-compose exec phpfpm php artisan down

# Step 4: Pull ultimo tag
echo "📦 Step 4: Pull ultimo tag..."
git fetch --tags
LAST_TAG=$(git tag --sort=-version:refname | grep "^MS-" | head -1)
if [ -z "$LAST_TAG" ]; then
    echo "❌ ERRORE: Nessun tag MS-* trovato"
    exit 1
fi
git checkout $LAST_TAG
echo "✅ Checkout tag: $LAST_TAG"

# Step 5: Deploy script
echo "🚀 Step 5: Esecuzione deploy script..."
docker-compose exec phpfpm bash scripts/deploy_prod.sh

# Step 6-7-8: Verifica, backup e inserimento variabili .env
echo "📝 Step 6-7-8: Verifica variabili .env..."
# (Inserisci qui la logica del controllo, backup e inserimento variabili)

# Step 9: Pulizia cache
if [ "$NEW_VARS_ADDED" = true ]; then
    echo "🧹 Step 9: Pulizia cache..."
    docker-compose exec phpfpm php artisan optimize:clear
fi

echo "✅ Deploy completato!"
```

**Nota**: Lo script completo richiede l'implementazione interattiva per le variabili `.env`, quindi è meglio seguire i passaggi manualmente o adattare lo script per le proprie esigenze.

