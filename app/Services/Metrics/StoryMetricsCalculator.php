<?php

namespace App\Services\Metrics;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StoryMetricsCalculator
{
    // Status che indicano "rilavorazione" — un reopen avviene quando si torna
    // da uno stato avanzato a uno di questi
    private const REOPEN_TARGET_STATUSES = ['progress', 'todo', 'assigned'];
    private const FORWARD_STATUSES = ['testing', 'tested', 'released', 'done'];

    /** Cache in-memory per i log di status, per evitare N+1 query nella Lens */
    private static array $logCache = [];

    /**
     * Minuti effettivi in stato `progress`, sommati da tutti gli intervalli attivi.
     */
    public function cycleTimeMinutes(int $storyId): ?int
    {
        $logs = $this->getStatusLogs($storyId);

        if ($logs->isEmpty()) {
            return null;
        }

        $totalMinutes = 0;
        $progressStart = null;
        $hasProgress   = false;

        foreach ($logs as $log) {
            if ($log['status'] === 'progress') {
                $progressStart = Carbon::parse($log['viewed_at']);
                $hasProgress   = true;
            } elseif ($progressStart !== null) {
                $totalMinutes += $progressStart->diffInMinutes(Carbon::parse($log['viewed_at']));
                $progressStart = null;
            }
        }

        return $hasProgress ? max(0, $totalMinutes) : null;
    }

    /**
     * true se il cycle time attivo (minuti in progress) rientra nelle ore stimate.
     * Se estimated_hours è null, usa la media del cycle time del team come benchmark.
     * Restituisce null solo se non è possibile calcolare né il cycle time né il benchmark.
     */
    public function onTimeDelivery(int $storyId, ?float $teamAvgCycleTimeMinutes = null): ?bool
    {
        $story = Story::find($storyId, ['estimated_hours']);
        $actualMinutes = $this->cycleTimeMinutes($storyId);

        if ($actualMinutes === null) {
            return null;
        }

        // Benchmark: ore stimate → minuti; fallback → media cycle time del team
        if (!is_null($story?->estimated_hours) && $story->estimated_hours > 0) {
            $benchmarkMinutes = $story->estimated_hours * 60;
        } elseif ($teamAvgCycleTimeMinutes !== null && $teamAvgCycleTimeMinutes > 0) {
            $benchmarkMinutes = $teamAvgCycleTimeMinutes;
        } else {
            return null;
        }

        return $actualMinutes <= $benchmarkMinutes;
    }

    /**
     * Conteggio di volte che la story è tornata a progress/todo da stati avanzati.
     */
    public function reopenCount(int $storyId): int
    {
        $logs = $this->getStatusLogs($storyId);
        $reopens = 0;

        for ($i = 1; $i < $logs->count(); $i++) {
            $prev = $logs[$i - 1]['status'];
            $curr = $logs[$i]['status'];

            if (
                in_array($prev, self::FORWARD_STATUSES)
                && in_array($curr, self::REOPEN_TARGET_STATUSES)
            ) {
                $reopens++;
            }
        }

        return $reopens;
    }

    /**
     * estimated_hours / hours. Null se hours = 0 o estimated_hours = null.
     */
    public function estimationAccuracy(int $storyId): ?float
    {
        $story = Story::find($storyId, ['estimated_hours', 'hours']);

        if (!$story || is_null($story->estimated_hours) || ($story->hours ?? 0) <= 0) {
            return null;
        }

        return round($story->estimated_hours / $story->hours, 2);
    }

    /**
     * Giorni lavorativi massimi in cui la story era in `progress`.
     */
    public function scrumFollowThroughDays(int $storyId): ?int
    {
        return $this->maxDaysInStatus($storyId, 'progress');
    }

    /**
     * Giorni lavorativi in cui la story è rimasta in `todo` prima di passare a `progress`.
     */
    public function todoStagnationDays(int $storyId): ?int
    {
        return $this->maxDaysInStatus($storyId, 'todo');
    }

    /**
     * Totale CHANGES_REQUESTED ricevuti sulle PR collegate alla story.
     */
    public function prChangeRequestsCount(int $storyId): int
    {
        return \App\Models\StoryGithubPr::where('story_id', $storyId)
            ->sum('change_requests_count');
    }

    /**
     * Aggregato per developer e quarter: restituisce tutte le metriche.
     */
    public function developerMetrics(int $userId, int $year, int $quarter): array
    {
        $stories = $this->closedStoriesForDeveloper($userId, $year, $quarter);

        if ($stories->isEmpty()) {
            return $this->emptyMetrics();
        }

        $cycleTimes   = $stories->map(fn($s) => $this->cycleTimeMinutes($s->id))->filter()->values();
        $teamAvgCycleMinutes = $cycleTimes->isNotEmpty() ? (float) $cycleTimes->average() : null;
        $onTimes      = $stories->map(fn($s) => $this->onTimeDelivery($s->id, $teamAvgCycleMinutes))->filter(fn($v) => !is_null($v))->values();
        $reopens      = $stories->map(fn($s) => $this->reopenCount($s->id));
        $accuracies   = $stories->map(fn($s) => $this->estimationAccuracy($s->id))->filter()->values();
        $scrumDays    = $stories->map(fn($s) => $this->scrumFollowThroughDays($s->id))->filter()->values();
        $todoDays     = $stories->map(fn($s) => $this->todoStagnationDays($s->id))->filter()->values();
        $prChangeReqs = $stories->map(fn($s) => $this->prChangeRequestsCount($s->id));

        return [
            'story_count'              => $stories->count(),
            'avg_cycle_time_minutes'   => $cycleTimes->isNotEmpty() ? round($cycleTimes->average()) : null,
            'on_time_delivery_rate'    => $onTimes->isNotEmpty() ? round($onTimes->filter()->count() / $onTimes->count() * 100, 1) : null,
            'total_reopens'            => $reopens->sum(),
            'reopen_rate'              => $stories->count() > 0 ? round($reopens->filter(fn($v) => $v > 0)->count() / $stories->count() * 100, 1) : null,
            'avg_estimation_accuracy'  => $accuracies->isNotEmpty() ? round($accuracies->average(), 2) : null,
            'avg_scrum_follow_through' => $scrumDays->isNotEmpty() ? round($scrumDays->average(), 1) : null,
            'avg_todo_stagnation'      => $todoDays->isNotEmpty() ? round($todoDays->average(), 1) : null,
            'total_pr_change_requests' => $prChangeReqs->sum(),
            'avg_pr_change_requests'   => $stories->count() > 0 ? round($prChangeReqs->average(), 2) : null,
        ];
    }

    /**
     * Medie di team per ogni metrica nel quarter specificato.
     */
    public function teamAverages(int $year, int $quarter): array
    {
        $cacheKey = "team_averages_{$year}_q{$quarter}";

        return Cache::remember($cacheKey, now()->addHours(4), function () use ($year, $quarter) {
            return $this->computeTeamAverages($year, $quarter);
        });
    }

    private function computeTeamAverages(int $year, int $quarter): array
    {
        $developers = User::whereJsonContains('roles', 'developer')->get('id');
        $allMetrics = $developers->map(fn($u) => $this->developerMetrics($u->id, $year, $quarter))
            ->filter(fn($m) => $m['story_count'] > 0);

        if ($allMetrics->isEmpty()) {
            return $this->emptyMetrics();
        }

        $avg = fn(string $key) => $allMetrics->pluck($key)->filter()->average();

        return [
            'story_count'              => round($allMetrics->pluck('story_count')->average()),
            'avg_cycle_time_minutes'   => $avg('avg_cycle_time_minutes') ? round($avg('avg_cycle_time_minutes')) : null,
            'on_time_delivery_rate'    => $avg('on_time_delivery_rate') ? round($avg('on_time_delivery_rate'), 1) : null,
            'total_reopens'            => null,
            // Media pooled: totale reopen / totale storie — evita il paradosso in cui
            // tutti i developer risultano "migliori della media" quando la media è vicina a 0
            'reopen_rate'              => $allMetrics->sum('story_count') > 0
                ? round($allMetrics->sum('total_reopens') / $allMetrics->sum('story_count') * 100, 1)
                : null,
            'avg_estimation_accuracy'  => $avg('avg_estimation_accuracy') ? round($avg('avg_estimation_accuracy'), 2) : null,
            'avg_scrum_follow_through' => $avg('avg_scrum_follow_through') ? round($avg('avg_scrum_follow_through'), 1) : null,
            'avg_todo_stagnation'      => $avg('avg_todo_stagnation') ? round($avg('avg_todo_stagnation'), 1) : null,
            'total_pr_change_requests' => null,
            'avg_pr_change_requests'   => $avg('avg_pr_change_requests') ? round($avg('avg_pr_change_requests'), 2) : null,
        ];
    }

    // --- Private helpers ---

    private function getStatusLogs(int $storyId): Collection
    {
        if (!isset(self::$logCache[$storyId])) {
            self::$logCache[$storyId] = StoryLog::where('story_id', $storyId)
                ->whereRaw("changes::jsonb ?? 'status'")
                ->orderBy('viewed_at')
                ->get(['changes', 'viewed_at'])
                ->map(fn($log) => [
                    'status'    => $log->changes['status'] ?? null,
                    'viewed_at' => $log->viewed_at,
                ])
                ->filter(fn($l) => !is_null($l['status']))
                ->values();
        }

        return self::$logCache[$storyId];
    }

    private function maxDaysInStatus(int $storyId, string $targetStatus): ?int
    {
        $logs = $this->getStatusLogs($storyId);
        $maxDays = null;

        for ($i = 0; $i < $logs->count(); $i++) {
            if ($logs[$i]['status'] !== $targetStatus) {
                continue;
            }

            $start = Carbon::parse($logs[$i]['viewed_at']);
            $end   = isset($logs[$i + 1]) ? Carbon::parse($logs[$i + 1]['viewed_at']) : Carbon::now();

            $days = $this->workingDaysBetween($start, $end);
            $maxDays = is_null($maxDays) ? $days : max($maxDays, $days);
        }

        return $maxDays;
    }

    private function workingDaysBetween(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy()->startOfDay();

        while ($current->lt($end->startOfDay())) {
            if ($current->isWeekday()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    private function closedStoriesForDeveloper(int $userId, int $year, int $quarter): Collection
    {
        [$startMonth, $endMonth] = match ($quarter) {
            1 => [1, 3],
            2 => [4, 6],
            3 => [7, 9],
            4 => [10, 12],
            default => [1, 3],
        };

        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end   = Carbon::create($year, $endMonth, 1)->endOfMonth()->endOfDay();

        // Usa il timestamp dell'ultimo StoryLog con status done/released per determinare
        // il quarter di chiusura, evitando di affidarsi a updated_at che può cambiare
        // per qualsiasi motivo (es. riassegnazione, aggiornamento campo).
        $closedInQuarter = StoryLog::whereRaw("changes::jsonb ->> 'status' IN ('done', 'released')")
            ->whereBetween('viewed_at', [$start, $end])
            ->select('story_id')
            ->groupBy('story_id');

        return Story::where('user_id', $userId)
            ->whereIn('status', [StoryStatus::Done->value, StoryStatus::Released->value])
            ->whereIn('id', $closedInQuarter->pluck('story_id'))
            ->get(['id', 'estimated_hours', 'hours']);
    }

    private function emptyMetrics(): array
    {
        return [
            'story_count'              => 0,
            'avg_cycle_time_minutes'   => null,
            'on_time_delivery_rate'    => null,
            'total_reopens'            => 0,
            'reopen_rate'              => null,
            'avg_estimation_accuracy'  => null,
            'avg_scrum_follow_through' => null,
            'avg_todo_stagnation'      => null,
            'total_pr_change_requests' => 0,
            'avg_pr_change_requests'   => null,
        ];
    }
}
