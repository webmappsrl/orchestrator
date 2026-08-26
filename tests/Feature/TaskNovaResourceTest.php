<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Nova\Filters\TaskAssigneeFilter;
use App\Nova\Task as TaskResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class TaskNovaResourceTest extends TestCase
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

    private function requestFor(?User $user): NovaRequest
    {
        return NovaRequest::create('/')->setUserResolver(fn () => $user);
    }

    public function test_admin_sees_all_tasks_in_global_index()
    {
        $quoteA = $this->makeQuoteWithOwner();
        $quoteB = $this->makeQuoteWithOwner();

        Task::create(['quote_id' => $quoteA->id, 'title' => 'Task A', 'due_date' => now()->addDay()]);
        Task::create(['quote_id' => $quoteB->id, 'title' => 'Task B', 'due_date' => now()->addDay()]);

        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);

        $results = TaskResource::indexQuery($this->requestFor($admin), Task::query())->get();

        $this->assertCount(2, $results);
    }

    public function test_manager_sees_all_tasks_in_global_index()
    {
        $quoteA = $this->makeQuoteWithOwner();
        $quoteB = $this->makeQuoteWithOwner();

        Task::create(['quote_id' => $quoteA->id, 'title' => 'Task A', 'due_date' => now()->addDay()]);
        Task::create(['quote_id' => $quoteB->id, 'title' => 'Task B', 'due_date' => now()->addDay()]);

        $manager = User::factory()->create(['roles' => [UserRole::Manager]]);

        $results = TaskResource::indexQuery($this->requestFor($manager), Task::query())->get();

        $this->assertCount(2, $results);
    }

    public function test_developer_still_sees_only_own_tasks_in_global_index()
    {
        $quoteA = $this->makeQuoteWithOwner();
        $quoteB = $this->makeQuoteWithOwner();

        $taskA = Task::create(['quote_id' => $quoteA->id, 'title' => 'Task A', 'due_date' => now()->addDay()]);
        Task::create(['quote_id' => $quoteB->id, 'title' => 'Task B', 'due_date' => now()->addDay()]);

        $developer = $quoteA->user;
        $developer->update(['roles' => [UserRole::Developer]]);

        $results = TaskResource::indexQuery($this->requestFor($developer), Task::query())->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($taskA));
    }

    public function test_assignee_filter_options_only_include_users_with_assigned_tasks()
    {
        $quote = $this->makeQuoteWithOwner();
        Task::create(['quote_id' => $quote->id, 'title' => 'Task assegnato', 'due_date' => now()->addDay()]);

        $userWithoutTasks = User::factory()->create();

        $filter = new TaskAssigneeFilter();
        $options = $filter->options($this->requestFor(null));

        $this->assertContains($quote->user->id, $options);
        $this->assertNotContains($userWithoutTasks->id, $options);
    }

    public function test_assignee_filter_apply_filters_by_quote_owner()
    {
        $quoteA = $this->makeQuoteWithOwner();
        $quoteB = $this->makeQuoteWithOwner();

        $taskA = Task::create(['quote_id' => $quoteA->id, 'title' => 'Task A', 'due_date' => now()->addDay()]);
        Task::create(['quote_id' => $quoteB->id, 'title' => 'Task B', 'due_date' => now()->addDay()]);

        $filter = new TaskAssigneeFilter();
        $results = $filter->apply($this->requestFor(null), Task::query(), $quoteA->user->id)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($taskA));
    }

    public function test_assignee_accessor_returns_null_when_quote_has_no_owner()
    {
        $customer = Customer::factory()->create();
        $quoteWithoutOwner = Quote::create([
            'title' => 'Quote senza owner',
            'customer_id' => $customer->id,
        ]);

        $task = Task::create([
            'quote_id' => $quoteWithoutOwner->id,
            'title' => 'Task senza assegnatario',
            'due_date' => now()->addDay(),
        ]);

        $this->assertNull($task->assignee);
    }

    public function test_quote_subpanel_fields_keep_original_layout_without_assignee_column()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create(['quote_id' => $quote->id, 'title' => 'Task', 'due_date' => now()->addDay()]);

        $request = NovaRequest::create('/?viaResource=quotes');
        $fields = (new TaskResource($task))->fields($request);
        $names = collect($fields)->pluck('name');

        $this->assertNotContains(__('Assignee'), $names);
        $this->assertNotContains(__('Customer'), $names);

        $dueDateField = collect($fields)->firstWhere('name', __('Due date'));
        $this->assertSame('date-time-field', $dueDateField->component);
    }

    public function test_global_index_fields_include_reordered_columns_and_assignee()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create(['quote_id' => $quote->id, 'title' => 'Task', 'due_date' => now()->addDay()]);

        $request = NovaRequest::create('/');
        $fields = (new TaskResource($task))->fields($request);
        $names = collect($fields)->pluck('name');

        $this->assertContains(__('Assignee'), $names);
        $this->assertContains(__('Customer'), $names);

        $dueDateField = collect($fields)->firstWhere('name', __('Due date'));
        $this->assertSame('date-field', $dueDateField->component);
    }

    public function test_quote_subpanel_filters_exclude_assignee_filter()
    {
        $request = NovaRequest::create('/?viaResource=quotes');
        $filters = (new TaskResource(new Task()))->filters($request);

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(\App\Nova\Filters\TaskDueDateFilter::class, $filters[0]);
    }

    public function test_global_index_filters_include_assignee_filter()
    {
        $request = NovaRequest::create('/');
        $filters = (new TaskResource(new Task()))->filters($request);

        $this->assertCount(2, $filters);
        $this->assertTrue(collect($filters)->contains(fn ($filter) => $filter instanceof TaskAssigneeFilter));
    }
}
