#!/usr/bin/env php
<?php

/**
 * Script interattivo per testare la ricerca AI Ticket Intelligence
 * 
 * Uso: docker exec php84_orchestrator php test_ai_search.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Story;

echo "=== AI Ticket Intelligence - Ricerca Interattiva ===\n\n";

// Se viene passato un argomento dalla riga di comando, usa quello
$searchText = $argv[1] ?? null;

if (!$searchText) {
    echo "Inserisci una domanda o descrizione del problema che vuoi cercare:\n";
    echo "> ";
    $searchText = trim(fgets(STDIN));
}

if (empty($searchText)) {
    echo "⚠️  Testo di ricerca vuoto. Uso un esempio di default...\n\n";
    $searchText = "problema con login utente";
}

echo "🔍 Cercando storie simili a: \"{$searchText}\"\n\n";

// Cerca storie simili
$results = Story::findSimilarToText($searchText, limit: 10, threshold: 0.5);

if ($results->isEmpty()) {
    echo "❌ Nessuna storia trovata con threshold 0.5.\n";
    echo "   Provo con threshold più basso (0.3)...\n\n";
    $results = Story::findSimilarToText($searchText, limit: 10, threshold: 0.3);
}

if ($results->isEmpty()) {
    echo "❌ Nessuna storia trovata.\n";
    echo "\n💡 Suggerimenti:\n";
    echo "   - Verifica che ci siano storie con embedding: " . Story::whereNotNull('embedding')->count() . "\n";
    echo "   - Prova con un testo più descrittivo\n";
    echo "   - Genera più embeddings: php artisan stories:generate-embeddings --limit=100\n";
    exit(1);
}

echo "✅ Trovate {$results->count()} storie simili:\n\n";

foreach ($results as $index => $story) {
    $similarity = round(($story->similarity ?? 0) * 100, 1);
    $status = $story->status ?? 'N/A';
    $type = $story->type ?? 'N/A';
    
    echo str_repeat("=", 80) . "\n";
    echo "📋 Storia #{$story->id} (Similarità: {$similarity}%)\n";
    echo str_repeat("-", 80) . "\n";
    echo "Nome: {$story->name}\n";
    echo "Status: {$status} | Tipo: {$type}\n";
    
    if ($story->description) {
        $desc = substr(strip_tags($story->description), 0, 200);
        echo "Descrizione: {$desc}" . (strlen($story->description) > 200 ? '...' : '') . "\n";
    }
    
    if ($story->customer_request) {
        $req = substr(strip_tags($story->customer_request), 0, 150);
        echo "Richiesta cliente: {$req}" . (strlen($story->customer_request) > 150 ? '...' : '') . "\n";
    }
    
    echo "Creato: " . ($story->created_at ? $story->created_at->format('d/m/Y H:i') : 'N/A') . "\n";
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "💡 Per vedere i dettagli completi, apri le storie in Nova:\n";
echo "   http://localhost:8000/nova/resources/stories/{$results->first()->id}\n\n";
