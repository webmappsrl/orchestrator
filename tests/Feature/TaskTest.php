<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use DatabaseTransactions;

    private function makeQuoteWithOwner(): Quote
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();

        $quote = Quote::create([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
        ]);
        $quote->user_id = $user->id;
        $quote->save();

        return $quote->fresh();
    }

    public function test_task_belongs_to_quote()
    {
        $quote = $this->makeQuoteWithOwner();

        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Richiamare il cliente',
            'due_date' => now()->addDay(),
        ]);

        $this->assertInstanceOf(Quote::class, $task->quote);
        $this->assertTrue($quote->tasks->contains($task));
    }

    public function test_deleting_quote_cascades_to_tasks()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task da cancellare in cascata',
            'due_date' => now()->addDay(),
        ]);

        $quote->delete();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_can_create_task_on_quote_without_owner_with_null_assignee()
    {
        $customer = Customer::factory()->create();
        $quote = Quote::create([
            'title' => 'Quote senza owner',
            'customer_id' => $customer->id,
        ]);

        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task su quote senza owner',
            'due_date' => now()->addDay(),
        ]);

        $this->assertNotNull($task->id);
        $this->assertNull($task->assignee);
    }

    public function test_completing_task_sets_completed_at()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Da completare',
            'due_date' => now()->addDay(),
        ]);

        $task->status = Task::STATUS_COMPLETED;
        $task->save();

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_reopening_task_clears_completed_at()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Da riaprire',
            'due_date' => now()->addDay(),
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $task->status = Task::STATUS_TODO;
        $task->save();

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_assignee_accessor_resolves_via_quote_user()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task con assegnatario',
            'due_date' => now()->addDay(),
        ]);

        $this->assertTrue($quote->user->is($task->assignee));
    }

    public function test_overdue_scope_returns_only_past_due_todo_tasks()
    {
        $quote = $this->makeQuoteWithOwner();
        $overdue = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Scaduto',
            'due_date' => now()->subDay(),
        ]);
        Task::create([
            'quote_id' => $quote->id,
            'title' => 'Futuro',
            'due_date' => now()->addDay(),
        ]);

        $results = Task::overdue()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($overdue));
    }

    public function test_for_user_scope_filters_by_quote_owner()
    {
        $quoteA = $this->makeQuoteWithOwner();
        $quoteB = $this->makeQuoteWithOwner();

        $taskA = Task::create([
            'quote_id' => $quoteA->id,
            'title' => 'Task A',
            'due_date' => now()->addDay(),
        ]);
        Task::create([
            'quote_id' => $quoteB->id,
            'title' => 'Task B',
            'due_date' => now()->addDay(),
        ]);

        $results = Task::forUser($quoteA->user)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($taskA));
    }

    public function test_for_user_scope_also_includes_tasks_created_by_the_user()
    {
        $customer = Customer::factory()->create();
        $quoteWithoutOwner = Quote::create([
            'title' => 'Quote senza owner',
            'customer_id' => $customer->id,
        ]);
        $creator = User::factory()->create();

        $task = Task::create([
            'quote_id' => $quoteWithoutOwner->id,
            'title' => 'Task creato su quote altrui/senza owner',
            'due_date' => now()->addDay(),
            'creator_id' => $creator->id,
        ]);

        $results = Task::forUser($creator)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($task));
    }

    public function test_due_date_filter_applies_overdue_scope()
    {
        $quote = $this->makeQuoteWithOwner();
        $overdue = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Scaduto',
            'due_date' => now()->subDay(),
        ]);
        Task::create([
            'quote_id' => $quote->id,
            'title' => 'Futuro',
            'due_date' => now()->addDay(),
        ]);

        $filter = new \App\Nova\Filters\TaskDueDateFilter();
        $request = \Laravel\Nova\Http\Requests\NovaRequest::createFrom(request());

        $results = $filter->apply($request, Task::query(), 'overdue')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($overdue));
    }

    public function test_toggle_action_authorized_only_for_creator()
    {
        $quote = $this->makeQuoteWithOwner();
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();

        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task con creator',
            'due_date' => now()->addDay(),
            'creator_id' => $creator->id,
        ]);

        $action = new \App\Nova\Actions\ToggleTaskCompleted();

        $this->assertTrue($action->authorizedToRun(
            \Illuminate\Http\Request::create('/')->setUserResolver(fn () => $creator),
            $task
        ));

        $this->assertFalse($action->authorizedToRun(
            \Illuminate\Http\Request::create('/')->setUserResolver(fn () => $otherUser),
            $task
        ));
    }

    public function test_urgency_badge_treats_earlier_time_today_as_due_today_not_overdue()
    {
        $quote = $this->makeQuoteWithOwner();

        // Scadenza oggi ma con orario già passato rispetto a "adesso": deve restare
        // "due_today", non scivolare in "overdue" per un confronto sull'orario esatto.
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Scadenza oggi con orario passato',
            'due_date' => now()->startOfDay()->addHour(),
        ]);

        $resource = new \App\Nova\Task($task);

        $this->assertSame('due_today', $resource->urgencyBadgeKey());
    }
}
