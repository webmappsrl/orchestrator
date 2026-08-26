<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TaskApiRequest;
use App\Models\Quote;
use App\Models\Task;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * List the authenticated user's tasks — Task::scopeForUser() returns
     * tasks where the user owns the linked quote OR is the task creator.
     * Sorted by `due_date` ascending by default; opt-in `?sort=created_at`
     * (ascending) / `?sort=-created_at` (descending) mirrors the sort
     * syntax already used by QuoteController::index(). No pagination.
     *
     * @response array<array{id: int, quote_id: int, quote_title: string|null, title: string, notes: string|null, due_date: string, status: string, completed_at: string|null, creator_id: int|null, assignee: array{id: int, name: string, email: string}|null, created_at: string|null, updated_at: string|null}>
     */
    #[QueryParameter('sort', description: 'Sort by created_at: "created_at" for ascending, "-created_at" for descending. Any other value (including omitting this parameter) falls back to due_date ascending.', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::query()->forUser($request->user())->with('quote.user');

        if ($request->get('sort') === '-created_at') {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } elseif ($request->get('sort') === 'created_at') {
            $query->orderBy('created_at')->orderBy('id');
        } else {
            $query->orderBy('due_date')->orderBy('id');
        }

        $tasks = $query->get();

        return response()->json($tasks->map(fn (Task $task) => $this->formatTask($task)));
    }

    /**
     * Retrieve a single task's detail. Role-only authorization — no
     * ownership scoping (mirror QuotePolicy::view()): any Admin/Manager/
     * Developer can view any task's detail, even if not its quote owner
     * nor its creator.
     *
     * @response array{id: int, quote_id: int, quote_title: string|null, title: string, notes: string|null, due_date: string, status: string, completed_at: string|null, creator_id: int|null, assignee: array{id: int, name: string, email: string}|null, created_at: string|null, updated_at: string|null}
     */
    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load('quote.user');

        return response()->json($this->formatTask($task));
    }

    /**
     * Create a new task on an existing quote (also used for follow-ups,
     * as a plain task on the same quote — no explicit link to the
     * originating task is stored). `creator_id` is never accepted from
     * input: it is set automatically to the authenticated user by
     * Task::booted(). Denied (403) when the target quote is closed_won or
     * closed_lost (see TaskPolicy::create()).
     *
     * @response array{id: int, quote_id: int, quote_title: string|null, title: string, notes: string|null, due_date: string, status: string, completed_at: string|null, creator_id: int|null, assignee: array{id: int, name: string, email: string}|null, created_at: string|null, updated_at: string|null}
     */
    public function store(TaskApiRequest $request): JsonResponse
    {
        $quote = Quote::findOrFail($request->input('quote_id'));

        $this->authorize('create', [Task::class, $quote]);

        $task = new Task();
        $task->quote_id = $quote->id;
        $task->title = $request->input('title');
        $task->notes = $request->input('notes');
        $task->due_date = $request->input('due_date');
        $task->save();

        $task->load('quote.user');

        return response()->json($this->formatTask($task), 201);
    }

    /**
     * Update a task, limited to two fields with per-field authorization
     * (documented in TaskPolicy): `status` requires the authenticated user
     * to be the task's creator (mirror of
     * App\Nova\Actions\ToggleTaskCompleted::authorizedToRun()); `notes` is
     * open to any Admin/Manager/Developer. The `status` authorization
     * check runs BEFORE any mutation is applied, so a mixed payload
     * {status, notes} from a non-creator fails the entire request with
     * 403 — `notes` is never persisted in that case, even though it would
     * have been allowed on its own. `completed_at` is updated
     * automatically by the existing Task::booted() hook.
     *
     * @response array{id: int, quote_id: int, quote_title: string|null, title: string, notes: string|null, due_date: string, status: string, completed_at: string|null, creator_id: int|null, assignee: array{id: int, name: string, email: string}|null, created_at: string|null, updated_at: string|null}
     */
    public function update(TaskApiRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        if ($request->has('status')) {
            $this->authorize('updateStatus', $task);
        }

        if ($request->has('status')) {
            $task->status = $request->input('status');
            $task->save();
        }

        if ($request->has('notes')) {
            $task->appendNote($request->input('notes'));
        }

        $task->load('quote.user');

        return response()->json($this->formatTask($task));
    }

    private function formatTask(Task $task): array
    {
        $assignee = $task->assignee;

        return [
            'id'           => $task->id,
            'quote_id'     => $task->quote_id,
            'quote_title'  => $task->quote?->title,
            'title'        => $task->title,
            'notes'        => $task->notes,
            'due_date'     => optional($task->due_date)->toIso8601String(),
            'status'       => $task->status,
            'completed_at' => optional($task->completed_at)->toIso8601String(),
            'creator_id'   => $task->creator_id,
            'assignee'     => $assignee ? [
                'id'    => $assignee->id,
                'name'  => $assignee->name,
                'email' => $assignee->email,
            ] : null,
            'created_at'   => optional($task->created_at)->toIso8601String(),
            'updated_at'   => optional($task->updated_at)->toIso8601String(),
        ];
    }
}
