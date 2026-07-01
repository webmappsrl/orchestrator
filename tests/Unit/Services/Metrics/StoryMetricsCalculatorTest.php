<?php

namespace Tests\Unit\Services\Metrics;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\Metrics\StoryMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StoryMetricsCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private StoryMetricsCalculator $calc;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new StoryMetricsCalculator();
        $this->user = User::factory()->create();
    }

    private function createStoryWithLogs(array $statusSequence): Story
    {
        $story = Story::factory()->create();
        $time = Carbon::now()->subDays(count($statusSequence) + 1);

        foreach ($statusSequence as $status) {
            StoryLog::create([
                'story_id'  => $story->id,
                'user_id'   => $this->user->id,
                'viewed_at' => $time,
                'changes'   => ['status' => $status],
            ]);
            $time->addHours(8);
        }

        return $story;
    }

    // --- Cycle time ---

    public function test_cycle_time_sums_only_progress_intervals(): void
    {
        // progress 09:00→11:00 (120 min), poi todo, poi progress 09:00→11:00 (120 min) = 240 min
        $story = Story::factory()->create();
        $base = Carbon::parse('2026-01-10 09:00:00');

        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base, 'changes' => ['status' => 'progress']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base->copy()->addHours(2), 'changes' => ['status' => 'todo']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base->copy()->addDay(), 'changes' => ['status' => 'progress']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base->copy()->addDay()->addHours(2), 'changes' => ['status' => 'released']]);

        $result = $this->calc->cycleTimeMinutes($story->id);
        $this->assertEquals(240, $result);
    }

    public function test_cycle_time_single_progress_interval(): void
    {
        $story = Story::factory()->create();
        $start = Carbon::now()->subHours(10);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $start, 'changes' => ['status' => 'progress']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $start->copy()->addMinutes(600), 'changes' => ['status' => 'released']]);

        $result = $this->calc->cycleTimeMinutes($story->id);
        $this->assertEquals(600, $result);
    }

    public function test_cycle_time_null_if_no_progress_log(): void
    {
        $story = $this->createStoryWithLogs(['testing', 'released']);
        $this->assertNull($this->calc->cycleTimeMinutes($story->id));
    }

    // --- Reopen rate ---

    public function test_reopen_count_detects_regression_from_testing(): void
    {
        $story = $this->createStoryWithLogs(['progress', 'testing', 'progress', 'testing', 'released']);
        $this->assertEquals(1, $this->calc->reopenCount($story->id));
    }

    public function test_reopen_count_zero_for_clean_flow(): void
    {
        $story = $this->createStoryWithLogs(['progress', 'testing', 'tested', 'released']);
        $this->assertEquals(0, $this->calc->reopenCount($story->id));
    }

    public function test_released_to_done_not_counted_as_reopen(): void
    {
        // La transizione released→done avviene via saveQuietly, non produce log
        // Simuliamo comunque un log per sicurezza
        $story = $this->createStoryWithLogs(['progress', 'testing', 'released', 'done']);
        // 'done' non è uno status di "reopen" (non è progress/todo/assigned)
        $this->assertEquals(0, $this->calc->reopenCount($story->id));
    }

    // --- On-time delivery ---

    public function test_on_time_delivery_true_when_cycle_time_within_estimate(): void
    {
        $story = Story::factory()->create(['estimated_hours' => 8]);
        $base = Carbon::parse('2026-01-10 09:00:00');

        // Cycle time = 120 min (2h) ≤ 8h stimate → true
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base, 'changes' => ['status' => 'progress']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base->copy()->addMinutes(120), 'changes' => ['status' => 'released']]);

        $this->assertTrue($this->calc->onTimeDelivery($story->id));
    }

    public function test_on_time_delivery_false_when_cycle_time_exceeds_estimate(): void
    {
        $story = Story::factory()->create(['estimated_hours' => 1]);
        $base = Carbon::parse('2026-01-10 09:00:00');

        // Cycle time = 120 min (2h) > 1h stimata → false
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base, 'changes' => ['status' => 'progress']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base->copy()->addMinutes(120), 'changes' => ['status' => 'released']]);

        $this->assertFalse($this->calc->onTimeDelivery($story->id));
    }

    public function test_on_time_delivery_null_when_estimated_hours_null_and_no_fallback(): void
    {
        // Senza cycle time logs e senza fallback → null
        $story = Story::factory()->create(['estimated_hours' => null]);
        $this->assertNull($this->calc->onTimeDelivery($story->id));
    }

    public function test_on_time_delivery_uses_team_avg_cycle_time_as_fallback(): void
    {
        $story = Story::factory()->create(['estimated_hours' => null]);
        $base = Carbon::parse('2026-01-10 09:00:00');

        // Cycle time = 120 minuti in progress
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base, 'changes' => ['status' => 'progress']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $base->copy()->addMinutes(120), 'changes' => ['status' => 'released']]);

        // Team avg = 180 min → 120 <= 180 → true
        $this->assertTrue($this->calc->onTimeDelivery($story->id, 180.0));

        // Team avg = 90 min → 120 > 90 → false
        $this->assertFalse($this->calc->onTimeDelivery($story->id, 90.0));
    }

    // --- Estimation accuracy ---

    public function test_estimation_accuracy_ratio(): void
    {
        $story = Story::factory()->create(['estimated_hours' => 8, 'hours' => 4]);
        $this->assertEquals(2.0, $this->calc->estimationAccuracy($story->id));
    }

    public function test_estimation_accuracy_null_when_hours_zero(): void
    {
        $story = Story::factory()->create(['estimated_hours' => 8, 'hours' => 0]);
        $this->assertNull($this->calc->estimationAccuracy($story->id));
    }

    // --- Todo stagnation ---

    public function test_todo_stagnation_counts_working_days(): void
    {
        $story = Story::factory()->create();
        // Lunedì → Mercoledì (2 giorni lavorativi in todo)
        $monday = Carbon::parse('next monday');
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday, 'changes' => ['status' => 'todo']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(2), 'changes' => ['status' => 'progress']]);

        $this->assertEquals(2, $this->calc->todoStagnationDays($story->id));
    }

    // --- Todo stagnation total ---

    public function test_todo_stagnation_total_sums_multiple_intervals(): void
    {
        $story = Story::factory()->create();
        $monday = Carbon::parse('next monday');

        // Primo intervallo: lunedì → mercoledì = 2 giorni
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday, 'changes' => ['status' => 'todo']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(2), 'changes' => ['status' => 'progress']]);

        // Secondo intervallo: venerdì → lunedì successivo = 1 giorno lavorativo
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(4), 'changes' => ['status' => 'todo']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(7), 'changes' => ['status' => 'progress']]);

        $this->assertEquals(3, $this->calc->todoStagnationTotalDays($story->id));
    }

    public function test_todo_stagnation_total_returns_null_when_no_todo_logs(): void
    {
        $story = Story::factory()->create();
        $monday = Carbon::parse('next monday');

        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday, 'changes' => ['status' => 'progress']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(2), 'changes' => ['status' => 'done']]);

        $this->assertNull($this->calc->todoStagnationTotalDays($story->id));
    }

    public function test_todo_stagnation_total_single_interval(): void
    {
        $story = Story::factory()->create();
        $monday = Carbon::parse('next monday');

        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday, 'changes' => ['status' => 'todo']]);
        StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(2), 'changes' => ['status' => 'progress']]);

        $this->assertEquals(2, $this->calc->todoStagnationTotalDays($story->id));
    }
}
