# Test AI Ticket Intelligence

## 🧪 Come Testare le Funzionalità

### 1. Via Laravel Tinker (Test Rapido)

```bash
# Entra in tinker
docker exec -it php84_orchestrator php artisan tinker
```

Poi esegui:

```php
// Trova una storia con embedding
$story = App\Models\Story::whereNotNull('embedding')->first();

// Verifica che abbia l'embedding
$story->embedding; // Dovrebbe restituire un array di 1536 valori

// Trova storie simili
$similar = $story->findSimilar(limit: 5, threshold: 0.7);
$similar->each(fn($s) => print("Story #{$s->id}: {$s->name} (similarità: " . round(($s->similarity ?? 0) * 100, 1) . "%)\n"));

// Cerca storie simili a un testo
$results = App\Models\Story::findSimilarToText("problema con login utente", limit: 5);
$results->each(fn($s) => print("Story #{$s->id}: {$s->name} (similarità: " . round(($s->similarity ?? 0) * 100, 1) . "%)\n"));

// Genera embedding per una storia specifica
$story = App\Models\Story::find(1);
$story->generateEmbedding();
$story->refresh();
$story->embedding; // Dovrebbe essere popolato
```

### 2. Via API REST

#### Prerequisiti
Assicurati di avere un token Sanctum valido. Puoi ottenerlo tramite:
```bash
# Login via API (se disponibile)
curl -X POST http://localhost:8000/api/wm-geobox/login \
  -H "Content-Type: application/json" \
  -d '{"email": "tuo@email.com", "password": "tua-password"}'
```

#### Test 1: Trova storie simili a una storia specifica
```bash
# Sostituisci {story_id} con un ID reale e {token} con il tuo token
curl -X GET "http://localhost:8000/api/ai/stories/{story_id}/similar?limit=5&threshold=0.7" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

#### Test 2: Cerca storie simili a un testo
```bash
curl -X POST "http://localhost:8000/api/ai/stories/search" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "text": "problema con login utente non riesco ad accedere",
    "limit": 5,
    "threshold": 0.7
  }'
```

#### Test 3: Genera embedding per una storia
```bash
curl -X POST "http://localhost:8000/api/ai/stories/{story_id}/generate-embedding" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### 3. Via Nova (Interfaccia Web)

1. **Accedi a Nova**: Vai a `http://localhost:8000/nova`
2. **Apri una storia**: Vai su "Stories" e apri una storia qualsiasi
3. **Trova storie simili**: 
   - Nella pagina di dettaglio della storia, clicca sul menu azioni (tre puntini)
   - Seleziona "Trova Storie Simili"
   - Configura limit (es. 5) e threshold (es. 0.7)
   - Clicca "Cerca"
   - Vedrai un messaggio con le storie simili trovate e link per aprirle

### 4. Verifica Database

```bash
# Conta storie con embedding
docker exec postgres_orchestrator psql -U orchestrator -d orchestrator -c "
SELECT 
  COUNT(*) as total_stories,
  COUNT(embedding) as stories_with_embedding,
  COUNT(*) - COUNT(embedding) as stories_without_embedding
FROM stories;
"

# Mostra alcune storie con embedding
docker exec postgres_orchestrator psql -U orchestrator -d orchestrator -c "
SELECT id, name, 
  CASE WHEN embedding IS NOT NULL THEN 'Sì' ELSE 'No' END as has_embedding,
  LENGTH(embedding::text) as embedding_length
FROM stories 
WHERE embedding IS NOT NULL 
LIMIT 10;
"
```

### 5. Test Automatico con Script PHP

Crea un file `test_ai.php` nella root del progetto:

```php
<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Story;

echo "=== Test AI Ticket Intelligence ===\n\n";

// Test 1: Verifica storie con embedding
$storiesWithEmbedding = Story::whereNotNull('embedding')->count();
echo "1. Storie con embedding: {$storiesWithEmbedding}\n";

if ($storiesWithEmbedding === 0) {
    echo "   ⚠️  Nessuna storia con embedding trovata. Esegui prima:\n";
    echo "   php artisan stories:generate-embeddings --limit=10\n\n";
    exit(1);
}

// Test 2: Trova una storia con embedding
$story = Story::whereNotNull('embedding')->first();
echo "2. Storia di test: #{$story->id} - {$story->name}\n";

// Test 3: Trova storie simili
echo "3. Cercando storie simili...\n";
$similar = $story->findSimilar(limit: 3, threshold: 0.7);
echo "   Trovate {$similar->count()} storie simili:\n";
foreach ($similar as $s) {
    $similarity = round(($s->similarity ?? 0) * 100, 1);
    echo "   - Story #{$s->id}: {$s->name} (similarità: {$similarity}%)\n";
}

// Test 4: Cerca per testo
echo "\n4. Cercando storie simili al testo 'problema login'...\n";
$results = Story::findSimilarToText("problema login", limit: 3, threshold: 0.6);
echo "   Trovate {$results->count()} storie:\n";
foreach ($results as $s) {
    $similarity = round(($s->similarity ?? 0) * 100, 1);
    echo "   - Story #{$s->id}: {$s->name} (similarità: {$similarity}%)\n";
}

echo "\n✅ Tutti i test completati!\n";
```

Esegui:
```bash
docker exec php84_orchestrator php test_ai.php
```

### 6. Test End-to-End Completo

```bash
# 1. Genera embeddings per alcune storie di test
docker exec php84_orchestrator php artisan stories:generate-embeddings --limit=10

# 2. Verifica che siano stati generati
docker exec postgres_orchestrator psql -U orchestrator -d orchestrator -c "SELECT COUNT(*) FROM stories WHERE embedding IS NOT NULL;"

# 3. Testa via tinker
docker exec -it php84_orchestrator php artisan tinker
# Poi esegui il codice del Test 1 sopra
```

## 🔍 Verifica Configurazione

```bash
# Verifica che OPENAI_API_KEY sia configurato
docker exec php84_orchestrator php -r "echo 'OPENAI_API_KEY: ' . (env('OPENAI_API_KEY') ? 'Configurato (' . substr(env('OPENAI_API_KEY'), 0, 10) . '...)' : 'NON CONFIGURATO') . PHP_EOL;"

# Verifica configurazione AI
docker exec php84_orchestrator php artisan tinker
# Poi: config('ai.default_for_embeddings')
```

## 📊 Monitoraggio

```bash
# Conta embeddings generati nel tempo
watch -n 5 'docker exec postgres_orchestrator psql -U orchestrator -d orchestrator -t -c "SELECT COUNT(*) FROM stories WHERE embedding IS NOT NULL;"'
```

## ⚠️ Troubleshooting

### Se non trova storie simili:
- Verifica che ci siano almeno 2 storie con embedding
- Prova a ridurre il threshold (es. 0.5 invece di 0.7)
- Verifica che le storie abbiano contenuto testuale significativo

### Se l'embedding non viene generato:
- Verifica che OPENAI_API_KEY sia configurato nel .env
- Controlla i log: `tail -f storage/logs/laravel.log`
- Verifica che la storia abbia almeno uno tra: name, description, customer_request

### Se l'API restituisce 401:
- Verifica di avere un token Sanctum valido
- Controlla che l'utente sia autenticato
