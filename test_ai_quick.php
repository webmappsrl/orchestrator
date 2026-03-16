#!/usr/bin/env php
<?php

/**
 * Script rapido per testare AI Ticket Intelligence
 * 
 * Uso: docker exec php84_orchestrator php test_ai_quick.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Story;

echo "=== Test AI Ticket Intelligence ===\n\n";

// Test 1: Verifica storie con embedding
$storiesWithEmbedding = Story::whereNotNull('embedding')->count();
$totalStories = Story::count();

echo "📊 Statistiche:\n";
echo "   - Storie totali: {$totalStories}\n";
echo "   - Storie con embedding: {$storiesWithEmbedding}\n";
echo "   - Percentuale: " . round(($storiesWithEmbedding / $totalStories) * 100, 1) . "%\n\n";

if ($storiesWithEmbedding === 0) {
    echo "⚠️  Nessuna storia con embedding trovata.\n";
    echo "   Esegui: php artisan stories:generate-embeddings --limit=10\n\n";
    exit(1);
}

// Test 2: Trova una storia con embedding
$story = Story::whereNotNull('embedding')->first();
echo "🎯 Storia di test:\n";
echo "   ID: #{$story->id}\n";
echo "   Nome: {$story->name}\n";
echo "   Descrizione: " . substr($story->description ?? 'N/A', 0, 80) . "...\n\n";

// Test 3: Trova storie simili
echo "🔍 Cercando storie simili (limit: 5, threshold: 0.7)...\n";
$similar = $story->findSimilar(limit: 5, threshold: 0.7);

if ($similar->isEmpty()) {
    echo "   Nessuna storia simile trovata con threshold 0.7.\n";
    echo "   Provo con threshold più basso (0.5)...\n";
    $similar = $story->findSimilar(limit: 5, threshold: 0.5);
}

if ($similar->isEmpty()) {
    echo "   ⚠️  Nessuna storia simile trovata.\n";
    echo "   Potrebbe essere necessario generare più embeddings.\n\n";
} else {
    echo "   ✅ Trovate {$similar->count()} storie simili:\n";
    foreach ($similar as $s) {
        $similarity = round(($s->similarity ?? 0) * 100, 1);
        echo "   - Story #{$s->id}: {$s->name}\n";
        echo "     Similarità: {$similarity}%\n";
        echo "     Status: {$s->status}\n\n";
    }
}

// Test 4: Cerca per testo
echo "📝 Cercando storie simili al testo 'problema login'...\n";
$results = Story::findSimilarToText("problema login", limit: 3, threshold: 0.6);

if ($results->isEmpty()) {
    echo "   Nessuna storia trovata. Provo con threshold più basso...\n";
    $results = Story::findSimilarToText("problema login", limit: 3, threshold: 0.4);
}

if ($results->isEmpty()) {
    echo "   ⚠️  Nessuna storia trovata per questo testo.\n\n";
} else {
    echo "   ✅ Trovate {$results->count()} storie:\n";
    foreach ($results as $s) {
        $similarity = round(($s->similarity ?? 0) * 100, 1);
        echo "   - Story #{$s->id}: {$s->name}\n";
        echo "     Similarità: {$similarity}%\n\n";
    }
}

echo "✅ Test completati!\n\n";
echo "💡 Prossimi passi:\n";
echo "   1. Prova via Nova: Apri una storia e usa l'azione 'Trova Storie Simili'\n";
echo "   2. Prova via API: Usa i comandi curl nel file TEST_AI_TICKET_INTELLIGENCE.md\n";
echo "   3. Genera più embeddings: php artisan stories:generate-embeddings\n";
