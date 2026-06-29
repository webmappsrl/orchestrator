<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TagApiRequest;
use App\Models\StoryLog;
use App\Models\Story;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $query = Tag::query();

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->search);
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        $tags = $query->get();

        return response()->json($tags->map(fn($t) => $this->formatTag($t)));
    }

    public function show(Request $request, Tag $tag): JsonResponse
    {
        $this->authorizeRole($request);

        $tag->load('tagged');

        return response()->json($this->formatTag($tag, true));
    }

    public function store(TagApiRequest $request): JsonResponse
    {
        $this->authorizeRole($request);

        $validated = $request->validated();

        $tag = new Tag();
        $tag->name = $validated['name'];
        if (array_key_exists('description', $validated)) {
            $tag->description = $validated['description'];
        }
        $tag->save();

        return response()->json($this->formatTag($tag), 201);
    }

    public function update(TagApiRequest $request, Tag $tag): JsonResponse
    {
        $this->authorizeRole($request);

        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $tag->name = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $tag->description = $validated['description'];
        }
        $tag->save();

        return response()->json($this->formatTag($tag));
    }

    public function attachStory(Request $request, Tag $tag, Story $story): JsonResponse
    {
        $this->authorizeRole($request);

        $tag->tagged()->syncWithoutDetaching([$story->id]);

        StoryLog::create([
            'story_id'  => $story->id,
            'user_id'   => $request->user()->id,
            'viewed_at' => now()->format('Y-m-d H:i'),
            'changes'   => ['tag_attached' => $tag->id],
        ]);

        return response()->json(['message' => 'Story attached to tag.']);
    }

    public function detachStory(Request $request, Tag $tag, Story $story): JsonResponse
    {
        $this->authorizeRole($request);

        $tag->tagged()->detach($story->id);

        StoryLog::create([
            'story_id'  => $story->id,
            'user_id'   => $request->user()->id,
            'viewed_at' => now()->format('Y-m-d H:i'),
            'changes'   => ['tag_detached' => $tag->id],
        ]);

        return response()->json(['message' => 'Story detached from tag.']);
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole(UserRole::Developer) || $user->hasRole(UserRole::Admin),
            403
        );
    }

    private function formatTag(Tag $tag, bool $withStories = false): array
    {
        $data = [
            'id'          => $tag->id,
            'name'        => $tag->name,
            'description' => $tag->description,
        ];

        if ($withStories) {
            $data['stories'] = $tag->tagged->map(fn($s) => [
                'id'               => $s->id,
                'name'             => $s->name,
                'status'           => $s->status,
                'customer_request' => $s->customer_request,
                'description'      => $s->description,
            ])->values();
        }

        return $data;
    }
}
