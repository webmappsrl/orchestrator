<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;

class AIEmbeddingService
{
    /**
     * Genera un embedding per un testo usando Laravel AI
     *
     * @param string $text Il testo da convertire in embedding
     * @return array|null L'array di embedding o null in caso di errore
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            if (empty(trim($text))) {
                return null;
            }

            // Usa il provider OpenAI configurato per gli embeddings
            $result = Embeddings::for([$text])
                ->generate('openai');

            // Il risultato contiene un array di embeddings, prendiamo il primo
            if (empty($result->embeddings)) {
                return null;
            }

            return $result->first();
        } catch (\Exception $e) {
            Log::error('Errore nella generazione dell\'embedding', [
                'error' => $e->getMessage(),
                'text' => substr($text, 0, 100) . '...',
            ]);

            return null;
        }
    }

    /**
     * Genera un embedding combinando più campi di testo
     *
     * @param array $texts Array di testi da combinare
     * @return array|null L'array di embedding o null in caso di errore
     */
    public function generateEmbeddingFromTexts(array $texts): ?array
    {
        // Filtra i testi vuoti e li combina
        $combinedText = implode("\n\n", array_filter($texts, fn($text) => !empty(trim($text))));

        return $this->generateEmbedding($combinedText);
    }
}
