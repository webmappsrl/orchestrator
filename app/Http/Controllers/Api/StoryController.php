<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoryApiRequest;
use App\Models\Story;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;

class StoryController extends Controller
{
    public function show(Story $story): JsonResponse
    {
        $story->load('tags');

        return response()->json($this->formatStory($story));
    }

    public function store(StoryApiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tags = $validated['tags'] ?? null;
        unset($validated['tags']);

        $story = new Story();
        $story->fill(array_filter($validated, fn($v) => $v !== null, ARRAY_FILTER_USE_BOTH));

        if (isset($validated['customer_request'])) {
            $story->customer_request = $validated['customer_request'];
        }
        if (isset($validated['estimated_hours'])) {
            $story->estimated_hours = $validated['estimated_hours'];
        }

        $story->save();

        if ($tags !== null) {
            $story->tags()->syncWithoutDetaching($tags);
        }

        $this->attachAutoTags($story);

        $story->load('tags');

        return response()->json($this->formatStory($story), 201);
    }

    public function update(StoryApiRequest $request, Story $story): JsonResponse
    {
        $validated = $request->validated();
        $tags = $validated['tags'] ?? null;
        unset($validated['tags']);

        $fillable = array_intersect_key(
            $validated,
            array_flip(['name', 'status', 'type', 'user_id', 'tester_id', 'creator_id', 'parent_id'])
        );

        if (!empty($fillable)) {
            $story->fill($fillable);
        }

        if (array_key_exists('customer_request', $validated)) {
            $story->addResponse($validated['customer_request'], false);
        }
        if (array_key_exists('description', $validated)) {
            $story->addDevNote($validated['description'], false);
        }
        if (array_key_exists('estimated_hours', $validated)) {
            $story->estimated_hours = $validated['estimated_hours'];
        }

        $story->save();

        if ($tags !== null) {
            $story->tags()->syncWithoutDetaching($tags);
        }

        $this->attachAutoTags($story);

        $story->load('tags');

        return response()->json($this->formatStory($story));
    }

    private function attachAutoTags(Story $story): void
    {
        try {
            $tagService = app(TagService::class);
            $tagService->attachQuarterTagToStory($story);
            $tagService->attachCustomerTagToStory($story);
            $tagService->attachTagsFromTextToStory($story);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                "Auto-tagging failed for story #{$story->id}: " . $e->getMessage()
            );
        }
    }

    private function formatStory(Story $story): array
    {
        return [
            'id'               => $story->id,
            'name'             => $story->name,
            'status'           => $story->status,
            'type'             => $story->type,
            'description'      => $story->description,
            'customer_request' => $story->customer_request,
            'user_id'          => $story->user_id,
            'tester_id'        => $story->tester_id,
            'creator_id'       => $story->creator_id,
            'parent_id'        => $story->parent_id,
            'estimated_hours'  => $story->estimated_hours,
            'hours'            => $story->hours,
            'tags'             => $story->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values(),
            'created_at'       => $story->created_at?->toIso8601String(),
            'updated_at'       => $story->updated_at?->toIso8601String(),
        ];
    }
}
