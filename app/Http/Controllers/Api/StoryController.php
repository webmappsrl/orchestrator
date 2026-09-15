<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoryApiRequest;
use App\Models\Story;
use App\Models\StoryLog;
use App\Services\TagService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    /**
     * `changed_by`/`changed_from`/`changed_to` and the nested `with=logs` only ever match
     * story_logs rows that represent an actual field change. View-tracking rows written by
     * LogStory middleware (`changes: {"watch": ...}`) and relational tag attach/detach rows
     * written by TagController (`changes: {"tag_attached"/"tag_detached": ...}`) are excluded —
     * they are not "modifiche" in the sense this endpoint documents.
     */
    private const LOG_IS_CHANGE_SQL = "NOT (jsonb_exists(changes::jsonb, 'watch') OR jsonb_exists(changes::jsonb, 'tag_attached') OR jsonb_exists(changes::jsonb, 'tag_detached'))";

    /**
     * List stories, paginated and filtered — on story properties (`status`, `type`, `tag_id`,
     * `user_id`, `creator_id`, created/updated date ranges) and, distinctly, on their recorded
     * changes (`changed_by`, `changed_from`/`changed_to`). No filter is applied by default: an
     * omitted `status` returns stories in any status, including `done`/`rejected`/`released`.
     *
     * @response array{data: array<array{id: int, name: string, type: string, status: string, created_at: string|null, updated_at: string|null, user_id: int|null, creator_id: int|null, hours: float|null, tags: array<array{id: int, name: string}>, description?: string|null, customer_request?: string|null, logs?: array<array{at: string|null, user_id: int, changes: array|null}>}>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    #[QueryParameter('status', description: 'Filter by status. Accepts a single value (?status=todo) or multiple via array syntax (?status[]=todo&status[]=progress). Omitted: no status filter, every status is included.', type: 'string|array<string>')]
    #[QueryParameter('type', description: 'Filter by story type (e.g. Feature, Bug, Task). Omitted: no type filter.', type: 'string')]
    #[QueryParameter('tag_id', description: 'Filter stories attached to this tag id. Omitted: no tag filter. 422 if not an integer.', type: 'int')]
    #[QueryParameter('user_id', description: 'Filter by current assignee (Story.user_id) — NOT the author of a change. Omitted: no assignee filter. 422 if not an integer.', type: 'int')]
    #[QueryParameter('creator_id', description: 'Filter by the user who opened the story. Omitted: no creator filter. 422 if not an integer.', type: 'int')]
    #[QueryParameter('created_from', description: 'Filter stories created on or after this date (Y-m-d). Omitted: no lower bound. 422 if not a valid Y-m-d date.', type: 'string')]
    #[QueryParameter('created_to', description: 'Filter stories created on or before this date (Y-m-d). Omitted: no upper bound. 422 if not a valid Y-m-d date.', type: 'string')]
    #[QueryParameter('updated_from', description: 'Filter stories last updated on or after this date (Y-m-d). Omitted: no lower bound. 422 if not a valid Y-m-d date.', type: 'string')]
    #[QueryParameter('updated_to', description: 'Filter stories last updated on or before this date (Y-m-d). Omitted: no upper bound. 422 if not a valid Y-m-d date.', type: 'string')]
    #[QueryParameter('changed_by', description: 'Filter stories that have at least one recorded change (story_logs row, excluding view-tracking and tag attach/detach) authored by this user id — the AUTHOR of a change, not the current assignee (`user_id`). Omitted: no author filter. 422 if not an integer.', type: 'int')]
    #[QueryParameter('changed_from', description: 'Filter stories with at least one recorded change on or after this date (Y-m-d), matched against story_logs.created_at. Combined with `with=logs`, also bounds which nested log rows are returned. Omitted: no lower bound. 422 if not a valid Y-m-d date.', type: 'string')]
    #[QueryParameter('changed_to', description: 'Filter stories with at least one recorded change on or before this date (Y-m-d). Combined with `with=logs`, also bounds which nested log rows are returned. Omitted: no upper bound. 422 if not a valid Y-m-d date.', type: 'string')]
    #[QueryParameter('sort', description: 'Sort by "created_at"/"-created_at", "updated_at"/"-updated_at", or "status"/"-status" (canonical workflow order — not yet available, falls back silently to the default until StoryStatus::sortOrder() ships). Omitted or unrecognized: falls back to "-created_at" with id as tie-breaker.', type: 'string')]
    #[QueryParameter('per_page', description: 'Items per page, clamped to [1, ' . self::MAX_PER_PAGE . '].', type: 'int', default: self::DEFAULT_PER_PAGE)]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('with', description: 'Comma-separated opt-in extras: "description" adds description/customer_request to each item; "logs" nests that story\'s recorded changes (same exclusions as changed_* filters, and bounded by changed_from/changed_to when present).', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Story::class);

        $query = Story::query()->with('tags');

        if ($request->filled('status')) {
            $status = $request->input('status');
            is_array($status) ? $query->whereIn('status', $status) : $query->where('status', $status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if (($userId = $this->validatedIntFilter($request, 'user_id')) !== null) {
            $query->where('user_id', $userId);
        }

        if (($creatorId = $this->validatedIntFilter($request, 'creator_id')) !== null) {
            $query->where('creator_id', $creatorId);
        }

        if ($createdFrom = $this->validatedDateFilter($request, 'created_from')) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }
        if ($createdTo = $this->validatedDateFilter($request, 'created_to')) {
            $query->whereDate('created_at', '<=', $createdTo);
        }
        if ($updatedFrom = $this->validatedDateFilter($request, 'updated_from')) {
            $query->whereDate('updated_at', '>=', $updatedFrom);
        }
        if ($updatedTo = $this->validatedDateFilter($request, 'updated_to')) {
            $query->whereDate('updated_at', '<=', $updatedTo);
        }

        if (($tagId = $this->validatedIntFilter($request, 'tag_id')) !== null) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        if ($request->filled('changed_by') || $request->filled('changed_from') || $request->filled('changed_to')) {
            $query->whereHas('storyLogs', fn ($q) => $this->applyLogChangeFilters($q, $request));
        }

        // sort=status/-status falls through to the default branch until Task 3
        // (StoryStatus::sortOrder(), blocked on CTO confirmation) ships.
        match ($request->input('sort')) {
            'created_at'  => $query->orderBy('created_at')->orderBy('id'),
            '-created_at' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'updated_at'  => $query->orderBy('updated_at')->orderBy('id'),
            '-updated_at' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            default       => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $paginator = $query->paginate($this->resolvePerPage($request));

        $with = $this->parseWith($request);

        if (in_array('logs', $with, true)) {
            // Eager-load once for the whole page (a single extra query via whereIn) instead of
            // querying storyLogs per story inside formatStoryListItem() — avoids N+1.
            $paginator->getCollection()->load([
                'storyLogs' => function ($q) use ($request) {
                    $this->applyLogChangeFilters($q, $request);
                    $q->orderBy('created_at');
                },
            ]);
        }

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Story $story) => $this->formatStoryListItem($story, $with))
                ->values(),
            'meta' => $this->formatPaginationMeta($paginator),
        ]);
    }

    /**
     * Retrieve the change history of a single story — chronological, paginated, excluding
     * view-tracking and tag attach/detach rows (same exclusion as `GET /api/stories`' `with=logs`).
     * Unlike the list endpoint, this has no `changed_*` filters: it answers "what happened to this
     * story", not "who changed what in a given window".
     *
     * @response array{data: array<array{at: string|null, user_id: int, changes: array|null}>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: self::DEFAULT_PER_PAGE)]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    public function logs(Request $request, Story $story): JsonResponse
    {
        $this->authorize('view', $story);

        $query = $story->storyLogs()->whereRaw(self::LOG_IS_CHANGE_SQL)->orderBy('created_at');

        $paginator = $query->paginate($this->resolvePerPage($request));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (StoryLog $log) => $this->formatLog($log))->values(),
            'meta' => $this->formatPaginationMeta($paginator),
        ]);
    }

    /**
     * Retrieve a story by ID.
     *
     * @response array{id: int, name: string, status: string, type: string, description: string|null, customer_request: string|null, user_id: int|null, tester_id: int|null, creator_id: int|null, parent_id: int|null, estimated_hours: float|null, hours: float|null, tags: array<array{id: int, name: string}>, created_at: string|null, updated_at: string|null}
     */
    public function show(Story $story): JsonResponse
    {
        $story->load('tags');

        return response()->json($this->formatStory($story));
    }

    /**
     * Create a new story.
     *
     * @response 201 array{id: int, name: string, status: string, type: string, description: string|null, customer_request: string|null, user_id: int|null, tester_id: int|null, creator_id: int|null, parent_id: int|null, estimated_hours: float|null, hours: float|null, tags: array<array{id: int, name: string}>, created_at: string|null, updated_at: string|null}
     */
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

    /**
     * Update an existing story.
     *
     * @response array{id: int, name: string, status: string, type: string, description: string|null, customer_request: string|null, user_id: int|null, tester_id: int|null, creator_id: int|null, parent_id: int|null, estimated_hours: float|null, hours: float|null, tags: array<array{id: int, name: string}>, created_at: string|null, updated_at: string|null}
     */
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
            $story->description = $validated['description'];
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

    /**
     * @param array<int, string> $with
     */
    private function formatStoryListItem(Story $story, array $with): array
    {
        $data = [
            'id'         => $story->id,
            'name'       => $story->name,
            'type'       => $story->type,
            'status'     => $story->status,
            'created_at' => $story->created_at?->toIso8601String(),
            'updated_at' => $story->updated_at?->toIso8601String(),
            'user_id'    => $story->user_id,
            'creator_id' => $story->creator_id,
            'hours'      => $story->hours,
            'tags'       => $story->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
        ];

        if (in_array('description', $with, true)) {
            $data['description'] = $story->description;
            $data['customer_request'] = $story->customer_request;
        }

        if (in_array('logs', $with, true)) {
            // storyLogs is eager-loaded (with the same filters + order applied) by index()
            // before this method runs, so this is a read of an already-fetched relation, no query.
            $data['logs'] = $story->storyLogs
                ->map(fn (StoryLog $log) => $this->formatLog($log))
                ->values()
                ->all();
        }

        return $data;
    }

    private function formatLog(StoryLog $log): array
    {
        return [
            'at'      => $log->created_at?->format('Y-m-d H:i'),
            'user_id' => $log->user_id,
            'changes' => $log->changes,
        ];
    }

    /**
     * Applies the "is a real change" exclusion (always) plus the changed_by/changed_from/changed_to
     * bounds (only if present on the request) to a story_logs query builder — shared by the
     * `changed_*` list filter and the nested `with=logs` formatting, so both see the same rows.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany $query
     */
    private function applyLogChangeFilters($query, Request $request): void
    {
        $query->whereRaw(self::LOG_IS_CHANGE_SQL);

        if (($changedBy = $this->validatedIntFilter($request, 'changed_by')) !== null) {
            $query->where('user_id', $changedBy);
        }
        if ($changedFrom = $this->validatedDateFilter($request, 'changed_from')) {
            $query->whereDate('created_at', '>=', $changedFrom);
        }
        if ($changedTo = $this->validatedDateFilter($request, 'changed_to')) {
            $query->whereDate('created_at', '<=', $changedTo);
        }
    }

    /**
     * @return array<int, string>
     */
    private function parseWith(Request $request): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $request->input('with', '')))));
    }

    /**
     * Clamps `per_page` to [1, MAX_PER_PAGE] — Eloquent's paginate() silently drops the LIMIT
     * clause on a negative value (returning every matching row) and falls back to its own default
     * of 15, not ours, on 0. Neither is caught by `Request::integer()`, which is a plain (int) cast.
     */
    private function resolvePerPage(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Reads a date-only (Y-m-d) query parameter, aborting with 422 if present but not in that
     * exact format — `whereDate()` would otherwise forward a malformed value straight to
     * Postgres, surfacing as an uncaught 500 (and, with APP_DEBUG on, leaking the query/stack).
     */
    private function validatedDateFilter(Request $request, string $key): ?string
    {
        if (!$request->filled($key)) {
            return null;
        }

        $value = $request->input($key);

        abort_unless(
            is_string($value) && \Illuminate\Support\Carbon::hasFormat($value, 'Y-m-d'),
            422,
            "Il parametro '{$key}' deve essere una data nel formato Y-m-d."
        );

        return $value;
    }

    /**
     * Reads an integer-only query parameter, aborting with 422 if present but not numeric —
     * `Request::integer()` does a plain (int) cast, so a non-numeric value like `tag_id=abc`
     * would otherwise silently become 0 and match nothing, with no signal to the caller.
     */
    private function validatedIntFilter(Request $request, string $key): ?int
    {
        if (!$request->filled($key)) {
            return null;
        }

        $value = $request->input($key);

        abort_unless(
            is_numeric($value) && (int) $value == $value,
            422,
            "Il parametro '{$key}' deve essere un numero intero."
        );

        return (int) $value;
    }

    private function formatPaginationMeta(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'last_page'    => $paginator->lastPage(),
        ];
    }
}
