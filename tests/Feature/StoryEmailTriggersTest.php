<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Jobs\SendStatusUpdateMailJob;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class StoryEmailTriggersTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCustomer(): User
    {
        return User::factory()->create(['roles' => collect([\App\Enums\UserRole::Customer])]);
    }

    private function makeDeveloper(): User
    {
        return User::factory()->create(['roles' => collect([\App\Enums\UserRole::Developer])]);
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

    private function jobsDispatchedTo(int $userId): \Illuminate\Support\Collection
    {
        return Bus::dispatched(SendStatusUpdateMailJob::class)
            ->filter(fn (SendStatusUpdateMailJob $job) => $job->user->id === $userId);
    }

    // =========================================================================
    // REGOLA 1: Status -> todo → mail a user_id
    // =========================================================================

    /** @test */
    public function rule1_status_to_todo_sends_email_to_assignee(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $assignee = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::New->value,
            'user_id' => $assignee->id,
        ]);

        Auth::login($actor);
        $story->status = StoryStatus::Todo->value;
        $story->save();

        $this->assertCount(1, $this->jobsDispatchedTo($assignee->id));
    }

    /** @test */
    public function rule1_status_to_assigned_does_not_send_email_to_assignee(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $assignee = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Todo->value,
            'user_id' => $assignee->id,
        ]);

        Auth::login($actor);
        $story->status = StoryStatus::Assigned->value;
        $story->save();

        $this->assertCount(0, $this->jobsDispatchedTo($assignee->id));
    }

    /** @test */
    public function rule1_no_email_if_actor_is_the_assignee(): void
    {
        Bus::fake();
        $assignee = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::New->value,
            'user_id' => $assignee->id,
        ]);

        Auth::login($assignee);
        $story->status = StoryStatus::Todo->value;
        $story->save();

        $this->assertCount(0, $this->jobsDispatchedTo($assignee->id));
    }

    // =========================================================================
    // REGOLA 2: user_id cambia + status già todo → mail a user_id
    // =========================================================================

    /** @test */
    public function rule2_user_id_changes_while_status_is_todo_sends_email(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $assignee = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Todo->value,
            'user_id' => null,
            'tester_id' => null,
        ]);

        Auth::login($actor);
        $story->user_id = $assignee->id;
        $story->save();

        $this->assertCount(1, $this->jobsDispatchedTo($assignee->id));
    }

    /** @test */
    public function rule2_user_id_changes_while_status_is_assigned_does_not_send_email(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $assignee = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Assigned->value,
            'user_id' => null,
            'tester_id' => null,
        ]);

        Auth::login($actor);
        $story->user_id = $assignee->id;
        $story->save();

        $this->assertCount(0, $this->jobsDispatchedTo($assignee->id));
    }

    /** @test */
    public function rule2_user_id_changes_while_status_is_not_todo_does_not_send_email(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $assignee = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Tested->value,
            'user_id' => null,
            'tester_id' => $tester->id,
        ]);

        Auth::login($actor);
        $story->user_id = $assignee->id;
        $story->save();

        $this->assertCount(0, $this->jobsDispatchedTo($assignee->id));
    }

    // =========================================================================
    // REGOLA 3: Status -> testing → mail a tester_id
    // =========================================================================

    /** @test */
    public function rule3_status_to_testing_sends_email_to_tester(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Todo->value,
            'user_id' => $actor->id,
            'tester_id' => $tester->id,
        ]);

        Auth::login($actor);
        $story->status = StoryStatus::Test->value;
        $story->save();

        $this->assertCount(1, $this->jobsDispatchedTo($tester->id));
    }

    /** @test */
    public function rule3_no_duplicate_when_developer_sets_status_to_testing(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Todo->value,
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
        ]);

        Auth::login($developer);
        $story->status = StoryStatus::Test->value;
        $story->save();

        $toTester = $this->jobsDispatchedTo($tester->id);
        $this->assertCount(1, $toTester, 'Tester should receive exactly 1 email, not 2 (saved + updated)');
    }

    // =========================================================================
    // REGOLA 4: tester_id cambia + status già testing → mail a tester_id
    // =========================================================================

    /** @test */
    public function rule4_tester_id_changes_while_status_is_testing_sends_email(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Test->value,
            'tester_id' => null,
        ]);

        Auth::login($actor);
        $story->tester_id = $tester->id;
        $story->save();

        $this->assertCount(1, $this->jobsDispatchedTo($tester->id));
    }

    /** @test */
    public function rule4_tester_id_changes_while_status_is_not_testing_does_not_send_email(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Todo->value,
            'tester_id' => null,
        ]);

        Auth::login($actor);
        $story->tester_id = $tester->id;
        $story->save();

        Bus::assertNotDispatched(SendStatusUpdateMailJob::class);
    }

    // =========================================================================
    // REGOLA 5: Status -> tested → mail a user_id
    // =========================================================================

    /** @test */
    public function rule5_status_to_tested_sends_email_to_assignee(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Test->value,
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
        ]);

        Auth::login($tester);
        $story->status = StoryStatus::Tested->value;
        $story->save();

        $this->assertCount(1, $this->jobsDispatchedTo($developer->id));
    }

    /** @test */
    public function rule5_no_duplicate_when_tester_sets_status_to_tested(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Test->value,
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
        ]);

        Auth::login($tester);
        $story->status = StoryStatus::Tested->value;
        $story->save();

        $toDev = $this->jobsDispatchedTo($developer->id);
        $this->assertCount(1, $toDev, 'Developer should receive exactly 1 email, not 2 (saved + updated)');
    }

    // =========================================================================
    // COMBINED SAVES: status + assignment nella stessa save → 1 sola mail
    // =========================================================================

    /** @test */
    public function combined_status_to_todo_and_user_id_set_sends_exactly_one_email(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $assignee = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::New->value,
            'user_id' => null,
            'tester_id' => null,
        ]);

        Auth::login($actor);
        $story->user_id = $assignee->id;
        $story->status = StoryStatus::Todo->value;
        $story->save();

        $toAssignee = $this->jobsDispatchedTo($assignee->id);
        $this->assertCount(1, $toAssignee, 'Assignee should receive exactly 1 email on combined status+user_id change');
    }

    /** @test */
    public function combined_status_to_testing_and_tester_id_set_sends_exactly_one_email(): void
    {
        Bus::fake();
        $actor = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'status' => StoryStatus::Todo->value,
            'user_id' => $actor->id,
            'tester_id' => null,
        ]);

        Auth::login($actor);
        $story->tester_id = $tester->id;
        $story->status = StoryStatus::Test->value;
        $story->save();

        $toTester = $this->jobsDispatchedTo($tester->id);
        $this->assertCount(1, $toTester, 'Tester should receive exactly 1 email on combined status+tester_id change');
    }

    // =========================================================================
    // CASI SPECIALI: customer released + customer_request → todo
    // =========================================================================

    /** @test */
    public function special_customer_released_email_with_highlight_when_customer_request_changed(): void
    {
        Bus::fake();
        $customer = $this->makeCustomer();
        $actor = $this->makeDeveloper();
        $story = $this->makeStory([
            'creator_id' => $customer->id,
            'status' => StoryStatus::Tested->value,
            'customer_request' => '<p>inizio</p>',
        ]);

        Auth::login($actor);
        $story->customer_request = '<p>aggiornato</p>';
        $story->status = StoryStatus::Released->value;
        $story->save();

        Bus::assertDispatched(SendStatusUpdateMailJob::class, function (SendStatusUpdateMailJob $job) use ($customer) {
            return $job->user->id === $customer->id
                && ($job->context['highlight_latest_response'] ?? false) === true;
        });
    }

    /** @test */
    public function special_customer_reply_forces_todo_and_notifies_assignee_with_highlight(): void
    {
        Bus::fake();
        $customer = $this->makeCustomer();
        $assignee = $this->makeDeveloper();
        $story = $this->makeStory([
            'creator_id' => $customer->id,
            'status' => StoryStatus::Released->value,
            'user_id' => $assignee->id,
            'customer_request' => '<p>inizio</p>',
        ]);

        Auth::login($customer);
        $story->customer_request = '<p>nuova risposta</p>';
        $story->status = StoryStatus::Todo->value;
        $story->save();

        Bus::assertDispatched(SendStatusUpdateMailJob::class, function (SendStatusUpdateMailJob $job) use ($assignee) {
            return $job->user->id === $assignee->id
                && ($job->context['highlight_latest_response'] ?? false) === true;
        });
    }

}
