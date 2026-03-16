<?php

namespace App\Http\Controllers;

use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AIStoryController extends Controller
{
    /**
     * Trova storie simili a una storia specifica
     *
     * @param Request $request
     * @param Story $story
     * @return JsonResponse
     */
    public function findSimilar(Request $request, Story $story): JsonResponse
    {
        try {
            $validated = $request->validate([
                'limit' => 'sometimes|integer|min:1|max:20',
                'threshold' => 'sometimes|numeric|min:0|max:1',
            ]);

            $limit = $validated['limit'] ?? 5;
            $threshold = $validated['threshold'] ?? 0.7;

            if ($story->embedding === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'La storia non ha un embedding. Genera prima l\'embedding per questa storia.',
                ], 400);
            }

            $similarStories = $story->findSimilar($limit, $threshold);

            return response()->json([
                'success' => true,
                'data' => [
                    'story_id' => $story->id,
                    'story_name' => $story->name,
                    'similar_stories' => $similarStories->map(function ($similar) {
                        return [
                            'id' => $similar->id,
                            'name' => $similar->name,
                            'description' => $similar->description,
                            'status' => $similar->status,
                            'type' => $similar->type,
                            'similarity' => round($similar->similarity ?? 0, 4),
                            'created_at' => $similar->created_at?->toISOString(),
                        ];
                    }),
                    'count' => $similarStories->count(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dati di input non validi',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Errore nella ricerca di storie simili', [
                'story_id' => $story->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore durante la ricerca di storie simili',
            ], 500);
        }
    }

    /**
     * Trova storie simili a un testo specifico
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchByText(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'text' => 'required|string|min:10|max:5000',
                'limit' => 'sometimes|integer|min:1|max:20',
                'threshold' => 'sometimes|numeric|min:0|max:1',
            ]);

            $text = $validated['text'];
            $limit = $validated['limit'] ?? 5;
            $threshold = $validated['threshold'] ?? 0.7;

            $similarStories = Story::findSimilarToText($text, $limit, $threshold);

            return response()->json([
                'success' => true,
                'data' => [
                    'search_text' => $text,
                    'similar_stories' => $similarStories->map(function ($similar) {
                        return [
                            'id' => $similar->id,
                            'name' => $similar->name,
                            'description' => $similar->description,
                            'status' => $similar->status,
                            'type' => $similar->type,
                            'similarity' => round($similar->similarity ?? 0, 4),
                            'created_at' => $similar->created_at?->toISOString(),
                        ];
                    }),
                    'count' => $similarStories->count(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dati di input non validi',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Errore nella ricerca di storie per testo', [
                'text' => substr($validated['text'] ?? '', 0, 100),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore durante la ricerca di storie simili',
            ], 500);
        }
    }

    /**
     * Genera l'embedding per una storia specifica
     *
     * @param Request $request
     * @param Story $story
     * @return JsonResponse
     */
    public function generateEmbedding(Request $request, Story $story): JsonResponse
    {
        try {
            $success = $story->generateEmbedding();

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Embedding generato con successo',
                    'data' => [
                        'story_id' => $story->id,
                        'story_name' => $story->name,
                        'has_embedding' => $story->embedding !== null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Impossibile generare l\'embedding. Verifica che la storia abbia contenuto testuale.',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Errore nella generazione dell\'embedding', [
                'story_id' => $story->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore durante la generazione dell\'embedding',
            ], 500);
        }
    }
}
