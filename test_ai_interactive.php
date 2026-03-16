#!/usr/bin/env php
<?php

/**
 * Script interattivo per testare la ricerca AI Ticket Intelligence
 * Permette di fare più domande in sequenza
 * 
 * Uso: docker exec -it php84_orchestrator php test_ai_interactive.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Story;

function printHeader() {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "🤖 AI Ticket Intelligence - Ricerca Interattiva\n";
    echo str_repeat("=", 80) . "\n\n";
}

function printStats() {
    $total = Story::count();
    $withEmbedding = Story::whereNotNull('embedding')->count();
    $percentage = $total > 0 ? round(($withEmbedding / $total) * 100, 1) : 0;
    
    echo "📊 Statistiche Database:\n";
    echo "   - Storie totali: {$total}\n";
    echo "   - Storie con embedding: {$withEmbedding} ({$percentage}%)\n\n";
}

function searchStories($searchText, $limit = 10, $threshold = 0.5) {
    echo "🔍 Cercando storie simili a: \"{$searchText}\"\n";
    echo "   (limit: {$limit}, threshold: {$threshold})\n\n";
    
    $results = Story::findSimilarToText($searchText, limit: $limit, threshold: $threshold);
    
    if ($results->isEmpty() && $threshold >= 0.3) {
        echo "   ⚠️  Nessuna storia trovata con threshold {$threshold}.\n";
        echo "   🔄 Provo con threshold più basso (0.3)...\n\n";
        $results = Story::findSimilarToText($searchText, limit: $limit, threshold: 0.3);
    }
    
    if ($results->isEmpty()) {
        echo "❌ Nessuna storia trovata.\n\n";
        echo "💡 Suggerimenti:\n";
        echo "   - Prova con un testo più descrittivo\n";
        echo "   - Usa parole chiave diverse\n";
        echo "   - Verifica che ci siano storie con embedding\n\n";
        return;
    }
    
    echo "✅ Trovate {$results->count()} storie simili:\n\n";
    
    foreach ($results as $index => $story) {
        $similarity = round(($story->similarity ?? 0) * 100, 1);
        $status = $story->status ?? 'N/A';
        $type = $story->type ?? 'N/A';
        
        echo str_repeat("-", 80) . "\n";
        echo "📋 Storia #{$story->id} (Similarità: {$similarity}%)\n";
        echo "   Nome: {$story->name}\n";
        echo "   Status: {$status} | Tipo: {$type}\n";
        
        if ($story->description) {
            $desc = substr(strip_tags($story->description), 0, 150);
            if (strlen($story->description) > 150) {
                $desc .= '...';
            }
            echo "   Descrizione: {$desc}\n";
        }
        
        echo "   Link Nova: http://localhost:8000/nova/resources/stories/{$story->id}\n";
    }
    
    echo str_repeat("-", 80) . "\n\n";
}

// Main loop
printHeader();
printStats();

// Se viene passato un argomento, usa quello e esci
if (isset($argv[1])) {
    searchStories($argv[1]);
    exit(0);
}

// Modalità interattiva
echo "💬 Inserisci una domanda o descrizione del problema.\n";
echo "   (Scrivi 'exit' o 'quit' per uscire, 'stats' per statistiche)\n\n";

while (true) {
    echo "> ";
    $input = trim(fgets(STDIN));
    
    if (empty($input)) {
        continue;
    }
    
    $input = strtolower($input);
    
    if ($input === 'exit' || $input === 'quit' || $input === 'q') {
        echo "\n👋 Arrivederci!\n";
        break;
    }
    
    if ($input === 'stats' || $input === 's') {
        printStats();
        continue;
    }
    
    if ($input === 'help' || $input === 'h') {
        echo "\n📖 Comandi disponibili:\n";
        echo "   - Scrivi una domanda per cercare storie simili\n";
        echo "   - 'stats' o 's' - Mostra statistiche\n";
        echo "   - 'exit' o 'quit' o 'q' - Esci\n";
        echo "   - 'help' o 'h' - Mostra questo aiuto\n\n";
        continue;
    }
    
    searchStories($input);
}
