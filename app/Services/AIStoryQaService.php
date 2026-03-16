<?php

namespace App\Services;

use App\Models\Documentation;
use App\Models\Story;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function Laravel\Ai\agent;

class AIStoryQaService
{
    public function ask(string $question, ?Story $pinnedStory = null): string
    {
        $question = trim($question);
        if ($question === '') {
            throw new \InvalidArgumentException('La domanda non può essere vuota.');
        }

        $similarStories = collect();

        try {
            $similarStories = Story::findSimilarToText($question, 8, 0.45);
        } catch (QueryException $e) {
            // Se la colonna embedding non esiste ancora, facciamo fallback a ricerca testuale.
            if (! (($e->getCode() === '42703') || str_contains($e->getMessage(), 'column "embedding" does not exist'))) {
                throw $e;
            }
        }

        // Fallback aggiuntivo: se abbiamo pochi risultati via pgvector (es. embeddings non ancora generati),
        // integriamo con una ricerca testuale.
        if ($similarStories->count() < 5) {
            $textMatches = Story::query()
                ->where(function ($q) use ($question) {
                    $q->where('name', 'ILIKE', '%'.$question.'%')
                        ->orWhere('description', 'ILIKE', '%'.$question.'%')
                        ->orWhere('customer_request', 'ILIKE', '%'.$question.'%');
                })
                ->latest('id')
                ->limit(8)
                ->get();

            $similarStories = $similarStories
                ->concat($textMatches)
                ->unique('id')
                ->values()
                ->take(8);
        }

        // Carica i tags delle storie simili, gestendo eventuali errori
        $tagNames = collect();
        try {
            $tagNames = $similarStories
                ->loadMissing('tags')
                ->pluck('tags')
                ->flatten()
                ->pluck('name')
                ->unique()
                ->values();
        } catch (\Exception $e) {
            // Se il caricamento dei tags fallisce, continuiamo senza tags
            $tagNames = collect();
        }

        $context = [
            'pinned_story' => $pinnedStory ? $this->storyContext($pinnedStory) : null,
            'similar_stories' => $similarStories->map(fn (Story $s) => $this->storyContext($s))->values()->all(),
            'documentation_tags' => $tagNames->all(),
            'related_documentations' => $this->relatedDocumentationsContext($tagNames->all(), $question),
        ];

        $prompt = implode("\n\n", [
            'Sei un assistente per il ticketing. Rispondi in italiano, in modo chiaro e concreto.',
            'Se mancano informazioni, esplicita cosa manca e proponi le prossime verifiche.',
            'Quando citi un elemento, aggiungi riferimenti espliciti tipo "Story #123" o "Documentation #45".',
            '---',
            'CONTESTO (JSON):',
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            '---',
            'DOMANDA:',
            $question,
        ]);

        $response = agent(
            instructions: 'Agisci come assistente di supporto tecnico/prodotto per ticket e documentazione.',
        )->prompt($prompt, [], null, null, 90);

        return trim((string) $response);
    }

    /**
     * @return array<string, mixed>
     */
    private function storyContext(Story $story): array
    {
        try {
            $story->loadMissing(['tags', 'participants']);
        } catch (\Exception $e) {
            // Se alcune relazioni non esistono, continuiamo comunque
        }

        // Prova a caricare project (belongsTo) invece di projects (belongsToMany)
        $projectName = null;
        try {
            if ($story->relationLoaded('project') || $story->project_id) {
                $story->loadMissing('project');
                $projectName = $story->project?->name;
            }
        } catch (\Exception $e) {
            // Ignora se la relazione project non funziona
        }

        // Prova a caricare projects (belongsToMany) se esiste
        $projectNames = [];
        try {
            if (method_exists($story, 'projects')) {
                $story->loadMissing('projects');
                $projectNames = $story->projects->pluck('name')->values()->all();
            }
        } catch (\Exception $e) {
            // Se la tabella story_project non esiste, usiamo solo project
            if ($projectName) {
                $projectNames = [$projectName];
            }
        }

        $attributes = Arr::except($story->getAttributes(), ['embedding']);

        // Normalizza campi testuali potenzialmente lunghi.
        foreach (['description', 'customer_request', 'answer_to_ticket'] as $key) {
            if (array_key_exists($key, $attributes) && is_string($attributes[$key])) {
                $attributes[$key] = $this->truncateText($attributes[$key], 8000);
            }
        }

        $views = collect();
        try {
            $views = $story->views()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($log) => Arr::except($log->getAttributes(), []));
        } catch (\Exception $e) {
            // Se la relazione views non funziona, continuiamo senza logs
        }

        return [
            'attributes' => $attributes,
            'tags' => $story->tags->pluck('name')->values()->all(),
            'projects' => $projectNames,
            'participants' => $story->participants->pluck('email')->values()->all(),
            'last_logs' => $views->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relatedDocumentationsContext(array $tagNames, string $question): array
    {
        $keywords = collect(preg_split('/\s+/', mb_strtolower($question)) ?: [])
            ->filter(fn ($w) => mb_strlen($w) >= 4)
            ->unique()
            ->values()
            ->take(8);

        /** @var Collection<int, Documentation> $docs */
        $docs = Documentation::query()
            ->with(['tags', 'creator', 'media'])
            ->when(! empty($tagNames) || $keywords->isNotEmpty(), function ($q) use ($tagNames, $keywords) {
                return $q->where(function ($qq) use ($tagNames, $keywords) {
                    if (! empty($tagNames)) {
                        $qq->orWhereHas('tags', fn ($t) => $t->whereIn('tags.name', $tagNames));
                    }

                    if ($keywords->isNotEmpty()) {
                        $qq->orWhere(function ($qname) use ($keywords) {
                            foreach ($keywords as $kw) {
                                $qname->orWhere('name', 'ILIKE', '%'.$kw.'%');
                            }
                        });
                    }
                });
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return $docs->map(function (Documentation $doc) {
            $attributes = $doc->getAttributes();

            // Gestisci getMedia() in modo sicuro
            $documents = [];
            $images = [];
            try {
                if (method_exists($doc, 'getMedia') && $doc instanceof \Spatie\MediaLibrary\HasMedia) {
                    $documents = $doc->getMedia('documents')->map(fn ($m) => [
                        'name' => $m->name,
                        'file_name' => $m->file_name,
                        'mime_type' => $m->mime_type,
                        'size' => $m->size,
                        'url' => $m->getUrl(),
                    ])->values()->all();
                    $images = $doc->getMedia('images')->map(fn ($m) => [
                        'name' => $m->name,
                        'file_name' => $m->file_name,
                        'mime_type' => $m->mime_type,
                        'size' => $m->size,
                        'url' => $m->getUrl(),
                    ])->values()->all();
                }
            } catch (\Exception $e) {
                // Se getMedia non funziona, continuiamo senza media
            }

            return [
                'attributes' => $attributes,
                'category' => (string) ($doc->category?->value ?? $doc->category ?? ''),
                'creator_email' => optional($doc->creator)->email,
                'tags' => $doc->tags->pluck('name')->values()->all(),
                'documents' => $documents,
                'images' => $images,
            ];
        })->values()->all();
    }

    private function truncateText(string $text, int $maxChars): string
    {
        $text = Str::of($text)->replace("\r\n", "\n")->toString();

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars).'… [troncato]';
    }
}

