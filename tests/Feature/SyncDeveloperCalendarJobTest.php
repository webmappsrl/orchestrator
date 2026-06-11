<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Jobs\SyncDeveloperCalendarJob;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SyncDeveloperCalendarJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // ShouldBeUniqueUntilProcessing acquires its unique lock even when the
        // Bus is faked (the lock lives in PendingDispatch, before the fake
        // dispatcher). The job uses Cache::driver('redis') for the lock: point
        // the redis store to the array driver so tests never touch Redis.
        config(['cache.stores.redis.driver' => 'array']);
    }

    private function makeCustomer(): User
    {
        return User::factory()->create(['roles' => collect([UserRole::Customer])]);
    }

    private function makeDeveloper(): User
    {
        return User::factory()->create(['roles' => collect([UserRole::Developer])]);
    }

    private function makeStory(array $attrs = []): Story
    {
        $customer = $attrs['creator'] ?? $this->makeCustomer();

        return Story::query()->create(array_merge([
            'name' => 'Test story',
            'type' => StoryType::Helpdesk->value,
            'status' => StoryStatus::New->value,
            'creator_id' => $customer->id,
            'customer_request' => '<p>hello</p>',
        ], $attrs));
    }

    private function syncJobsFor(string $email): \Illuminate\Support\Collection
    {
        return Bus::dispatched(SyncDeveloperCalendarJob::class)
            ->filter(fn (SyncDeveloperCalendarJob $job) => $job->developerEmail === $email);
    }

    /** @test */
    public function status_change_dispatches_sync_for_assigned_developer(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $story = $this->makeStory(['user_id' => $developer->id]);

        Auth::login($developer);
        $story->status = StoryStatus::Todo->value;
        $story->save();

        $this->assertCount(1, $this->syncJobsFor($developer->email));
    }

    /** @test */
    public function status_to_testing_dispatches_sync_for_developer_and_tester(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
            'status' => StoryStatus::Progress->value,
        ]);

        Auth::login($developer);
        $story->status = StoryStatus::Test->value;
        $story->save();

        $this->assertCount(1, $this->syncJobsFor($developer->email));
        $this->assertCount(1, $this->syncJobsFor($tester->email));
    }

    /** @test */
    public function status_leaving_testing_dispatches_sync_for_tester(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
            'status' => StoryStatus::Test->value,
        ]);

        Auth::login($tester);
        $story->status = StoryStatus::Done->value;
        $story->save();

        $this->assertCount(1, $this->syncJobsFor($developer->email));
        $this->assertCount(1, $this->syncJobsFor($tester->email));
    }

    /** @test */
    public function assignee_change_dispatches_sync_for_both_developers(): void
    {
        Bus::fake();
        $oldDeveloper = $this->makeDeveloper();
        $newDeveloper = $this->makeDeveloper();
        $story = $this->makeStory([
            'user_id' => $oldDeveloper->id,
            'status' => StoryStatus::Todo->value,
        ]);

        Auth::login($oldDeveloper);
        $story->user_id = $newDeveloper->id;
        $story->save();

        $this->assertCount(1, $this->syncJobsFor($oldDeveloper->email));
        $this->assertCount(1, $this->syncJobsFor($newDeveloper->email));
    }

    /** @test */
    public function save_without_status_or_assignee_change_dispatches_nothing(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $story = $this->makeStory([
            'user_id' => $developer->id,
            'status' => StoryStatus::Todo->value,
        ]);

        Auth::login($developer);
        $story->name = 'Renamed story';
        $story->save();

        Bus::assertNotDispatched(SyncDeveloperCalendarJob::class);
    }

    /** @test */
    public function dispatched_job_is_delayed_by_the_debounce_window(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $story = $this->makeStory(['user_id' => $developer->id]);

        Auth::login($developer);
        $story->status = StoryStatus::Todo->value;
        $story->save();

        Bus::assertDispatched(
            SyncDeveloperCalendarJob::class,
            fn (SyncDeveloperCalendarJob $job) => $job->delay === SyncDeveloperCalendarJob::DEBOUNCE_SECONDS
        );
    }
}
