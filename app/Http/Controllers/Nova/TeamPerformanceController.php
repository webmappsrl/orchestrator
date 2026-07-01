<?php

namespace App\Http\Controllers\Nova;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryGithubCommit;
use App\Models\StoryGithubPr;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\Metrics\StoryMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TeamPerformanceController extends Controller
{
    public function __construct(private StoryMetricsCalculator $calc) {}

    public function data(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $isAdmin = $currentUser->hasRole(UserRole::Admin) || $currentUser->hasRole(UserRole::Manager);

        // Developer vede solo se stesso
        $developerId = $isAdmin
            ? (int) $request->input('developer_id', $currentUser->id)
            : $currentUser->id;

        $year    = (int) $request->input('year', now()->year);
        $quarter = (int) $request->input('quarter', (int) ceil(now()->month / 3));
        $quarter = max(1, min(4, $quarter));

        [$startMonth, $endMonth] = match ($quarter) {
            1 => [1, 3], 2 => [4, 6], 3 => [7, 9], 4 => [10, 12],
        };
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end   = Carbon::create($year, $endMonth, 1)->endOfMonth()->endOfDay();

        // Aggregato team (media aziendale) — calcolato prima per usarlo come benchmark in onTimeDelivery
        $teamAggregate = Cache::remember(
            "team_perf_avg_{$year}_q{$quarter}",
            3600,
            fn () => $this->buildTeamAggregate($year, $quarter, $start, $end)
        );

        $teamAvgCycleMinutes = $teamAggregate['avg_cycle_time_hours'] !== null
            ? $teamAggregate['avg_cycle_time_hours'] * 60
            : null;

        // Storie Bug/Feature chiuse nel quarter per questo developer
        $tickets = $this->getTickets($developerId, $start, $end, $teamAvgCycleMinutes);

        // Aggregato developer
        $devAggregate = $this->buildAggregate($tickets);

        // Lista developer per il selettore
        $developers = $isAdmin
            ? User::whereJsonContains('roles', 'developer')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->values()
                ->toArray()
            : [['id' => $currentUser->id, 'name' => $currentUser->name]];

        return response()->json([
            'developers'             => $developers,
            'selected_developer_id'  => $developerId,
            'year'                   => $year,
            'quarter'                => $quarter,
            'tickets'                => $tickets,
            'aggregate'              => [
                'developer'    => $devAggregate,
                'team_average' => $teamAggregate,
            ],
        ]);
    }

    private function getTickets(int $userId, Carbon $start, Carbon $end, ?float $teamAvgCycleMinutes = null): array
    {
        $closedStoryIds = StoryLog::whereRaw("changes::jsonb ->> 'status' IN ('done', 'released')")
            ->whereBetween('viewed_at', [$start, $end])
            ->pluck('story_id')
            ->unique()
            ->toArray();

        $stories = Story::where('user_id', $userId)
            ->whereIn('type', [StoryType::Bug->value, StoryType::Feature->value])
            ->whereIn('status', [StoryStatus::Done->value, StoryStatus::Released->value])
            ->whereIn('id', $closedStoryIds)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'name', 'type', 'estimated_hours', 'hours', 'updated_at']);

        return $stories->map(function ($story) use ($teamAvgCycleMinutes) {
            $cycleMinutes = $this->calc->cycleTimeMinutes($story->id);
            $commits      = StoryGithubCommit::where('story_id', $story->id)->count();
            $prs          = StoryGithubPr::where('story_id', $story->id)->get();

            return [
                'id'                    => $story->id,
                'name'                  => $story->name,
                'type'                  => $story->type,
                'nova_url'              => '/resources/customer-stories/' . $story->id,
                'cycle_time_hours'      => $cycleMinutes !== null ? round($cycleMinutes / 60, 1) : null,
                'reopen_count'          => $this->calc->reopenCount($story->id),
                'on_time'               => $this->calc->onTimeDelivery($story->id, $teamAvgCycleMinutes),
                'on_time_diff_hours'    => $this->onTimeDiff($story->id, $teamAvgCycleMinutes),
                'on_time_detail'        => $this->onTimeDetail($story, $teamAvgCycleMinutes),
                'commit_count'          => $commits ?: null,
                'pr_count'              => $prs->count() ?: null,
                'change_requests_count' => $prs->sum('change_requests_count') ?: null,
                'todo_stagnation_days'  => $this->calc->todoStagnationTotalDays($story->id),
            ];
        })->toArray();
    }

    private function onTimeDetail(\App\Models\Story $story, ?float $teamAvgCycleMinutes): ?string
    {
        $actualMinutes = $this->calc->cycleTimeMinutes($story->id);
        if ($actualMinutes === null) {
            return null;
        }

        $actualHours = round($actualMinutes / 60, 1);

        if (!is_null($story->estimated_hours) && $story->estimated_hours > 0) {
            return "{$actualHours}h effettive vs {$story->estimated_hours}h stimate";
        } elseif ($teamAvgCycleMinutes !== null) {
            $benchmarkHours = round($teamAvgCycleMinutes / 60, 1);
            return "{$actualHours}h effettive vs {$benchmarkHours}h media team";
        }

        return null;
    }

    private function onTimeDiff(int $storyId, ?float $teamAvgCycleMinutes): ?float
    {
        $actualMinutes = $this->calc->cycleTimeMinutes($storyId);
        if ($actualMinutes === null) {
            return null;
        }

        $story = \App\Models\Story::find($storyId, ['estimated_hours']);
        if (!is_null($story?->estimated_hours) && $story->estimated_hours > 0) {
            $benchmarkMinutes = $story->estimated_hours * 60;
        } elseif ($teamAvgCycleMinutes !== null) {
            $benchmarkMinutes = $teamAvgCycleMinutes;
        } else {
            return null;
        }

        $diffHours = round(($actualMinutes - $benchmarkMinutes) / 60, 1);
        return $diffHours === 0.0 ? null : $diffHours;
    }

    private function median(array $values): ?float
    {
        $values = array_values(array_filter($values, fn ($v) => $v !== null));
        if (empty($values)) {
            return null;
        }
        sort($values);
        $count = count($values);
        $mid   = (int) floor($count / 2);
        return $count % 2 === 0
            ? round(($values[$mid - 1] + $values[$mid]) / 2, 1)
            : round($values[$mid], 1);
    }

    private function buildAggregate(array $tickets): array
    {
        if (empty($tickets)) {
            return [
                'story_count'              => 0,
                'avg_cycle_time_hours'     => null,
                'avg_reopen_count'         => null,
                'on_time_rate'             => null,
                'avg_commit_count'         => null,
                'avg_pr_count'             => null,
                'avg_change_requests'      => null,
                'avg_todo_stagnation_days' => null,
            ];
        }

        $cycleTimes     = array_filter(array_column($tickets, 'cycle_time_hours'), fn ($v) => $v !== null);
        $reopens        = array_column($tickets, 'reopen_count');
        $onTimes        = array_filter(array_column($tickets, 'on_time'), fn ($v) => $v !== null);
        $commits        = array_filter(array_column($tickets, 'commit_count'), fn ($v) => $v !== null);
        $prs            = array_filter(array_column($tickets, 'pr_count'), fn ($v) => $v !== null);
        $changeReqs     = array_filter(array_column($tickets, 'change_requests_count'), fn ($v) => $v !== null);
        $todoStagnation = array_filter(array_column($tickets, 'todo_stagnation_days'), fn ($v) => $v !== null);
        $capped     = count(array_filter($cycleTimes, fn ($v) => $v >= 80));

        return [
            'story_count'          => count($tickets),
            'avg_cycle_time_hours' => count($cycleTimes) ? round(array_sum($cycleTimes) / count($cycleTimes), 1) : null,
            'capped_count'         => $capped,
            'avg_reopen_count'     => count($reopens) ? round(array_sum($reopens) / count($reopens), 2) : null,
            'on_time_rate'         => count($onTimes) ? round(count(array_filter($onTimes)) / count($onTimes) * 100, 1) : null,
            'avg_commit_count'     => count($commits) ? round(array_sum($commits) / count($commits), 1) : null,
            'avg_pr_count'         => count($prs) ? round(array_sum($prs) / count($prs), 1) : null,
            'avg_change_requests'      => count($changeReqs) ? round(array_sum($changeReqs) / count($changeReqs), 1) : null,
            'avg_todo_stagnation_days' => count($todoStagnation) ? round(array_sum($todoStagnation) / count($todoStagnation), 1) : null,
        ];
    }

    private function buildTeamAggregate(int $year, int $quarter, Carbon $start, Carbon $end): array
    {
        $developers = User::whereJsonContains('roles', 'developer')->get(['id']);

        // Primo passaggio: cycle time medio del team (senza on_time, non serve il benchmark ancora)
        $firstPass = $developers
            ->map(fn ($dev) => $this->buildAggregate($this->getTickets($dev->id, $start, $end)))
            ->filter(fn ($agg) => $agg['story_count'] > 0)
            ->values();

        $teamAvgCycleMinutes = null;
        if ($firstPass->isNotEmpty()) {
            $avgHours = $firstPass->pluck('avg_cycle_time_hours')->filter()->average();
            $teamAvgCycleMinutes = $avgHours ? $avgHours * 60 : null;
        }

        // Secondo passaggio: ricalcola con il benchmark corretto per on_time
        $perDeveloper = $developers
            ->map(fn ($dev) => $this->buildAggregate($this->getTickets($dev->id, $start, $end, $teamAvgCycleMinutes)))
            ->filter(fn ($agg) => $agg['story_count'] > 0)
            ->values();

        if ($perDeveloper->isEmpty()) {
            return ['story_count' => 0, 'avg_cycle_time_hours' => null, 'avg_reopen_count' => null, 'on_time_rate' => null, 'avg_commit_count' => null, 'avg_pr_count' => null, 'avg_change_requests' => null, 'avg_todo_stagnation_days' => null];
        }

        $avg = fn (string $key) => $perDeveloper->pluck($key)->filter(fn ($v) => $v !== null)->average();

        return [
            'story_count'          => $perDeveloper->sum('story_count'),
            'avg_cycle_time_hours' => $avg('avg_cycle_time_hours') !== null ? round($avg('avg_cycle_time_hours'), 1) : null,
            'capped_count'         => (int) round($avg('capped_count') ?? 0),
            'avg_reopen_count'     => $avg('avg_reopen_count') !== null ? round($avg('avg_reopen_count'), 2) : null,
            'on_time_rate'         => $avg('on_time_rate') !== null ? round($avg('on_time_rate'), 1) : null,
            'avg_commit_count'     => $avg('avg_commit_count') !== null ? round($avg('avg_commit_count'), 1) : null,
            'avg_pr_count'         => $avg('avg_pr_count') !== null ? round($avg('avg_pr_count'), 1) : null,
            'avg_change_requests'      => $avg('avg_change_requests') !== null ? round($avg('avg_change_requests'), 1) : null,
            'avg_todo_stagnation_days' => $avg('avg_todo_stagnation_days') !== null ? round($avg('avg_todo_stagnation_days'), 1) : null,
        ];
    }
}
