<?php

namespace App\Http\Controllers\Api;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\QuoteActivity;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Attività delle trattative per giorno (oc:8724), lette dalla tabella
 * `quote_activities`.
 */
class QuoteActivityController extends Controller
{
    private const CLOSED_STATUSES = [QuoteStatus::Closed_Won->value, QuoteStatus::Closed_Lost->value];

    /**
     * Quote activities for a day or a range of days
     *
     * Returns the quotes that had at least one activity in the requested days, each with its
     * activities in chronological order: quote created/updated/deleted, products added/removed,
     * tasks created/updated/completed/reopened/deleted, notes added to a task. Quotes already
     * closed (closed won / closed lost) before the first requested day are left out; a quote
     * closed during the period is included. Days are calendar days in Europe/Rome. Activities
     * are recorded from the release of this endpoint on: earlier days return an empty list.
     *
     * @response array{from: string, days: int, data: array<array{quote: array{id: int|null, title: string|null, status: string|null, customer: array{id: int, name: string}|null, deleted: bool}, activities: array<array{at: string, event: string, user: array{id: int|null, name: string}, task: array{id: int|null, title: string|null}|null, changes: array|null}>}>}
     */
    #[QueryParameter('from', description: 'First day (YYYY-MM-DD, Europe/Rome). Defaults to today. 422 for another format.', type: 'string')]
    #[QueryParameter('days', description: 'Number of days starting from `from`, `from` included. Integer, at least 1, defaults to 1 (only `from`). 422 otherwise.', type: 'int', default: 1)]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'days' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $timezone = config('app.timezone');
        $start = isset($validated['from'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['from'], $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();
        $days = (int) ($validated['days'] ?? 1);
        $end = $start->addDays($days);

        $activities = QuoteActivity::query()
            ->with(['quote.customer'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        // Le righe di una trattativa cancellata hanno quote_id vuoto: si
        // tengono insieme con il titolo copiato.
        $groups = $activities->groupBy(fn (QuoteActivity $activity) => $activity->quote_id !== null
            ? 'id:' . $activity->quote_id
            : 'deleted:' . $activity->quote_title);

        $data = $groups
            ->reject(fn (Collection $group) => $group->first()->quote !== null
                && $this->wasClosedAt($group->first()->quote, $start))
            ->sortByDesc(fn (Collection $group) => $group->last()->created_at)
            ->map(fn (Collection $group) => [
                'quote' => $this->quotePayload($group->first()),
                'activities' => $group->map(fn (QuoteActivity $activity) => $this->activityPayload($activity))->values(),
            ])
            ->values();

        return response()->json([
            'from' => $start->toDateString(),
            'days' => $days,
            'data' => $data,
        ]);
    }

    /**
     * Stato della trattativa all'inizio del periodo, ricostruito dalle attività:
     * l'ultimo cambio di stato prima dell'inizio, altrimenti il valore di
     * partenza del primo cambio successivo, altrimenti lo stato attuale (una
     * trattativa chiusa senza cambi registrati era già chiusa prima del
     * rilascio di questa registrazione).
     */
    private function wasClosedAt(Quote $quote, CarbonImmutable $start): bool
    {
        $statusChanges = $quote->activities()
            ->where('event', QuoteActivity::EVENT_QUOTE_UPDATED)
            ->whereNotNull('changes->status');

        $before = (clone $statusChanges)
            ->where('created_at', '<', $start)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($before !== null) {
            $status = $before->changes['status']['new'] ?? null;
        } else {
            $after = (clone $statusChanges)
                ->where('created_at', '>=', $start)
                ->orderBy('created_at')
                ->orderBy('id')
                ->first();
            $status = $after !== null ? ($after->changes['status']['old'] ?? null) : $quote->status;
        }

        return in_array($status, self::CLOSED_STATUSES, true);
    }

    private function quotePayload(QuoteActivity $activity): array
    {
        $quote = $activity->quote;

        if ($quote === null) {
            return [
                'id' => null,
                'title' => $activity->quote_title,
                'status' => null,
                'customer' => null,
                'deleted' => true,
            ];
        }

        return [
            'id' => $quote->id,
            'title' => QuoteActivity::titleOf($quote),
            'status' => $quote->status,
            'customer' => $quote->customer ? ['id' => $quote->customer->id, 'name' => $quote->customer->name] : null,
            'deleted' => false,
        ];
    }

    private function activityPayload(QuoteActivity $activity): array
    {
        return [
            'at' => $activity->created_at->toIso8601String(),
            'event' => $activity->event,
            'user' => [
                'id' => $activity->user_id,
                'name' => $activity->user_name ?? __('System'),
            ],
            'task' => $activity->task_title !== null || $activity->task_id !== null
                ? ['id' => $activity->task_id, 'title' => $activity->task_title]
                : null,
            'changes' => $activity->changes,
        ];
    }
}
