<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Nova\Filters\MyTaskScopeFilter;
use App\Nova\Filters\TaskAssigneeFilter;
use App\Nova\MyTask;
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
        $user = User::factory()->create(['roles' => [UserRole::Manager]]);

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
        $assignee = User::factory()->create(['roles' => [UserRole::Developer]]);
        Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task assegnato',
            'due_date' => now()->addDay(),
            'assignee_id' => $assignee->id,
        ]);

        $filter = new TaskAssigneeFilter();
        $options = $filter->options($this->requestFor(null));

        $this->assertContains($assignee->id, $options);
        // L'owner della trattativa non ha task assegnati: non è un'opzione.
        $this->assertNotContains($quote->user->id, $options);
    }

    public function test_assignee_filter_apply_filters_by_task_assignee_not_quote_owner()
    {
        $quote = $this->makeQuoteWithOwner();
        $assignee = User::factory()->create(['roles' => [UserRole::Developer]]);

        $toAssignee = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task A',
            'due_date' => now()->addDay(),
            'assignee_id' => $assignee->id,
        ]);
        $toOwner = Task::create(['quote_id' => $quote->id, 'title' => 'Task B', 'due_date' => now()->addDay()]);

        $filter = new TaskAssigneeFilter();

        $results = $filter->apply($this->requestFor(null), Task::query(), $assignee->id)->get();
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($toAssignee));

        $results = $filter->apply($this->requestFor(null), Task::query(), $quote->user->id)->get();
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($toOwner));
    }

    public function test_quote_subpanel_fields_keep_original_layout_without_assignee_column()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create(['quote_id' => $quote->id, 'title' => 'Task', 'due_date' => now()->addDay()]);

        $request = NovaRequest::create('/?viaResource=quotes');
        $fields = (new TaskResource($task))->fields($request);
        $names = collect($fields)->pluck('name');

        // La lista del sub-panel resta identica al layout pre-oc:8402: il
        // campo Assegnatario c'è solo nel form (oc:8722), non come colonna.
        $this->assertContains(__('Customer'), $names);
        $this->assertContains(__('Quote'), $names);
        $this->assertCount(9, $fields);

        $assignee = collect($fields)->firstWhere('attribute', 'assignee');
        $this->assertNotNull($assignee);
        $this->assertFalse($assignee->showOnIndex);
        $this->assertTrue($assignee->showOnCreation);
        $this->assertTrue($assignee->showOnUpdate);

        // Ordine esatto pre-oc:8402: Task, Quote, Customer (non l'ordine
        // Customer-first introdotto per la vista globale). Il primo campo
        // (ID) e' escluso: la sua label e' un oggetto PendingTranslation,
        // non una stringa comparabile direttamente.
        $this->assertSame(
            [__('Task'), __('Quote'), __('Customer'), __('Due date'), __('Status'), __('Completed'), __('Assignee'), __('Notes')],
            $names->slice(1)->values()->all()
        );

        $dueDateField = collect($fields)->firstWhere('name', __('Due date'));
        $this->assertSame('date-time-field', $dueDateField->component);
    }

    public function test_assignee_field_is_required_and_lists_only_users_with_task_access()
    {
        foreach (['/', '/?viaResource=quotes'] as $url) {
            $fields = (new TaskResource(new Task()))->fields(NovaRequest::create($url));
            $assignee = collect($fields)->firstWhere('attribute', 'assignee');

            $this->assertNotNull($assignee, $url);
            $this->assertContains('required', $assignee->rules, $url);

            $developer = User::factory()->create(['roles' => [UserRole::Developer]]);
            $customer = User::factory()->create(['roles' => [UserRole::Customer]]);
            $query = User::query();
            call_user_func($assignee->relatableQueryCallback, NovaRequest::create($url), $query);
            $ids = $query->pluck('id');

            $this->assertTrue($ids->contains($developer->id), $url);
            $this->assertFalse($ids->contains($customer->id), $url);
        }
    }

    public function test_assignee_field_defaults_to_quote_owner_in_quote_subpanel_form()
    {
        $quote = $this->makeQuoteWithOwner();
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $request = NovaRequest::create("/?viaResource=quotes&viaResourceId={$quote->id}&editing=true&editMode=create")
            ->setUserResolver(fn () => $creator);

        $assignee = collect((new TaskResource(new Task()))->fields($request))->firstWhere('attribute', 'assignee');

        $this->assertSame($quote->user_id, $assignee->resolveDefaultValue($request));
    }

    public function test_assignee_field_recomputes_default_when_quote_changes_only_on_create()
    {
        $quote = $this->makeQuoteWithOwner();
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $manual = User::factory()->create(['roles' => [UserRole::Developer]]);

        $sync = function (string $editMode) use ($quote, $creator, $manual) {
            $request = NovaRequest::create('/', 'PATCH', [
                'editing' => 'true',
                'editMode' => $editMode,
                'quote' => $quote->id,
                'assignee' => $manual->id,
            ])->setUserResolver(fn () => $creator);

            $assignee = collect((new TaskResource(new Task()))->fields($request))->firstWhere('attribute', 'assignee');
            $assignee->setValue($manual->id);
            $assignee->applyDependsOn($request);

            return $assignee->value;
        };

        $this->assertSame($quote->user_id, $sync('create'));
        $this->assertSame($manual->id, $sync('update'));
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
    public function test_my_tasks_view_shows_assigned_and_created_tasks_even_for_admin()
    {
        $quote = $this->makeQuoteWithOwner();
        $me = User::factory()->create(['roles' => [UserRole::Admin]]);
        $other = User::factory()->create(['roles' => [UserRole::Developer]]);

        $assignedToMe = Task::create(['quote_id' => $quote->id, 'title' => 'A me', 'due_date' => now()->addDays(2), 'assignee_id' => $me->id, 'creator_id' => $other->id]);
        $createdByMe = Task::create(['quote_id' => $quote->id, 'title' => 'Da me', 'due_date' => now()->addDay(), 'assignee_id' => $other->id, 'creator_id' => $me->id]);
        $notMine = Task::create(['quote_id' => $quote->id, 'title' => 'Altro', 'due_date' => now()->addDay(), 'assignee_id' => $other->id, 'creator_id' => $other->id]);

        $results = MyTask::indexQuery($this->requestFor($me), Task::query())->get();

        $this->assertEquals([$createdByMe->id, $assignedToMe->id], $results->pluck('id')->all());
        $this->assertFalse($results->contains($notMine));
    }

    public function test_my_tasks_scope_filter_splits_assigned_and_created_with_assigned_default()
    {
        $quote = $this->makeQuoteWithOwner();
        $me = User::factory()->create(['roles' => [UserRole::Developer]]);
        $other = User::factory()->create(['roles' => [UserRole::Developer]]);

        $assignedToMe = Task::create(['quote_id' => $quote->id, 'title' => 'A me', 'due_date' => now()->addDay(), 'assignee_id' => $me->id, 'creator_id' => $other->id]);
        $createdByMe = Task::create(['quote_id' => $quote->id, 'title' => 'Da me', 'due_date' => now()->addDay(), 'assignee_id' => $other->id, 'creator_id' => $me->id]);

        $filter = new MyTaskScopeFilter();
        $request = $this->requestFor($me);

        $this->assertSame('assigned', $filter->default());
        $this->assertEquals(['assigned', 'created'], array_values($filter->options($request)));

        $assigned = $filter->apply($request, Task::query(), 'assigned')->get();
        $this->assertCount(1, $assigned);
        $this->assertTrue($assigned->first()->is($assignedToMe));

        $created = $filter->apply($request, Task::query(), 'created')->get();
        $this->assertCount(1, $created);
        $this->assertTrue($created->first()->is($createdByMe));
    }

    public function test_my_tasks_view_filters_replace_assignee_filter()
    {
        $filters = collect((new MyTask(new Task()))->filters(NovaRequest::create('/')));

        $this->assertTrue($filters->contains(fn ($f) => $f instanceof MyTaskScopeFilter));
        $this->assertTrue($filters->contains(fn ($f) => $f instanceof \App\Nova\Filters\TaskDueDateFilter));
        $this->assertFalse($filters->contains(fn ($f) => $f instanceof TaskAssigneeFilter));
        $this->assertSame('my-tasks', MyTask::uriKey());
    }
}
