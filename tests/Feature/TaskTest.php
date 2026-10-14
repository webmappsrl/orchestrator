<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskTest extends TestCase
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

    public function test_task_on_quote_without_owner_created_without_authenticated_user_has_no_assignee()
    {
        $customer = Customer::factory()->create();
        $quote = Quote::create([
            'title' => 'Quote senza owner',
            'customer_id' => $customer->id,
        ]);

        // Nessun utente autenticato e nessun owner: il default non ha
        // nessuno a cui ricadere.
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task su quote senza owner',
            'due_date' => now()->addDay(),
        ]);

        $this->assertNotNull($task->id);
        $this->assertNull($task->assignee);
    }

    public function test_default_assignee_is_quote_owner()
    {
        $quote = $this->makeQuoteWithOwner();
        $actor = User::factory()->create(['roles' => [UserRole::Developer]]);

        $this->assertSame($quote->user_id, Task::defaultAssigneeId($quote, $actor));
    }

    public function test_default_assignee_is_actor_when_quote_has_no_owner()
    {
        $quote = Quote::create(['title' => 'Senza owner', 'customer_id' => Customer::factory()->create()->id]);
        $actor = User::factory()->create(['roles' => [UserRole::Developer]]);

        $this->assertSame($actor->id, Task::defaultAssigneeId($quote, $actor));
    }

    public function test_default_assignee_skips_owner_without_task_access()
    {
        $quote = $this->makeQuoteWithOwner();
        $quote->user->update(['roles' => [UserRole::Customer]]);
        $actor = User::factory()->create(['roles' => [UserRole::Developer]]);

        $this->assertSame($actor->id, Task::defaultAssigneeId($quote->fresh(), $actor));
    }

    public function test_default_assignee_is_null_without_owner_and_actor()
    {
        $quote = Quote::create(['title' => 'Senza owner', 'customer_id' => Customer::factory()->create()->id]);

        $this->assertNull(Task::defaultAssigneeId($quote, null));
        $this->assertNull(Task::defaultAssigneeId(null, null));
    }

    public function test_creating_task_assigns_quote_owner_by_default()
    {
        $quote = $this->makeQuoteWithOwner();
        $this->actingAs(User::factory()->create(['roles' => [UserRole::Developer]]));

        $task = Task::create(['quote_id' => $quote->id, 'title' => 'T', 'due_date' => now()->addDay()]);

        $this->assertSame($quote->user_id, $task->assignee_id);
    }

    public function test_creating_task_on_quote_without_owner_assigns_creator()
    {
        $quote = Quote::create(['title' => 'Senza owner', 'customer_id' => Customer::factory()->create()->id]);
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $this->actingAs($creator);

        $task = Task::create(['quote_id' => $quote->id, 'title' => 'T', 'due_date' => now()->addDay()]);

        $this->assertSame($creator->id, $task->assignee_id);
    }

    public function test_creating_task_keeps_explicit_assignee()
    {
        $quote = $this->makeQuoteWithOwner();
        $other = User::factory()->create(['roles' => [UserRole::Developer]]);
        $this->actingAs(User::factory()->create(['roles' => [UserRole::Admin]]));

        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'T',
            'due_date' => now()->addDay(),
            'assignee_id' => $other->id,
        ]);

        $this->assertSame($other->id, $task->assignee_id);
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

    public function test_assignee_relation_resolves_via_assignee_id_not_quote_owner()
    {
        $quote = $this->makeQuoteWithOwner();
        $other = User::factory()->create(['roles' => [UserRole::Developer]]);
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task con assegnatario',
            'due_date' => now()->addDay(),
            'assignee_id' => $other->id,
        ]);

        $this->assertTrue($other->is($task->fresh()->assignee));
        $this->assertTrue($other->assignedTasks->contains($task));
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

    public function test_for_user_scope_filters_by_assignee_not_quote_owner()
    {
        $quote = $this->makeQuoteWithOwner();
        $owner = $quote->user;
        $other = User::factory()->create(['roles' => [UserRole::Developer]]);

        $toOwner = Task::create(['quote_id' => $quote->id, 'title' => 'Al owner', 'due_date' => now()->addDay()]);
        $toOther = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Assegnato e creato da altri',
            'due_date' => now()->addDay(),
            'creator_id' => $other->id,
            'assignee_id' => $other->id,
        ]);

        $ownerResults = Task::forUser($owner)->get();
        $this->assertTrue($ownerResults->contains($toOwner));
        $this->assertFalse($ownerResults->contains($toOther));

        $otherResults = Task::forUser($other)->get();
        $this->assertCount(1, $otherResults);
        $this->assertTrue($otherResults->first()->is($toOther));
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

    public function test_toggle_action_authorized_for_every_role_with_task_access()
    {
        $quote = $this->makeQuoteWithOwner();
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);

        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task con creator',
            'due_date' => now()->addDay(),
            'creator_id' => $creator->id,
        ]);

        $action = new \App\Nova\Actions\ToggleTaskCompleted();
        $requestAs = fn (User $user) => \Illuminate\Http\Request::create('/')->setUserResolver(fn () => $user);

        foreach ([UserRole::Admin, UserRole::Manager, UserRole::Developer] as $role) {
            $user = User::factory()->create(['roles' => [$role]]);
            $this->assertTrue($action->authorizedToRun($requestAs($user), $task), $role->value);
        }

        foreach ([UserRole::Editor, UserRole::Customer] as $role) {
            $user = User::factory()->create(['roles' => [$role]]);
            $this->assertFalse($action->authorizedToRun($requestAs($user), $task), $role->value);
        }
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
    public function test_backfill_migration_applies_default_rule_to_existing_tasks()
    {
        $withOwner = $this->makeQuoteWithOwner();
        $withoutOwner = Quote::create(['title' => 'Senza owner', 'customer_id' => Customer::factory()->create()->id]);
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);

        // Task "vecchi": inseriti senza passare dall'hook di creazione.
        $insert = fn (Quote $quote, ?int $creatorId) => DB::table('tasks')->insertGetId([
            'quote_id' => $quote->id,
            'title' => 'Task esistente',
            'due_date' => now()->addDay(),
            'status' => Task::STATUS_TODO,
            'creator_id' => $creatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $toOwner = $insert($withOwner, $creator->id);
        $toCreator = $insert($withoutOwner, $creator->id);
        $toNobody = $insert($withoutOwner, null);

        (require database_path('migrations/2026_10_14_120100_backfill_tasks_assignee_id.php'))->up();

        $this->assertSame($withOwner->user_id, Task::find($toOwner)->assignee_id);
        $this->assertSame($creator->id, Task::find($toCreator)->assignee_id);
        $this->assertNull(Task::find($toNobody)->assignee_id);
    }
    public function test_deleting_assignee_reassigns_tasks_to_quote_owner()
    {
        $quote = $this->makeQuoteWithOwner();
        $assignee = User::factory()->create(['roles' => [UserRole::Developer]]);
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'T',
            'due_date' => now()->addDay(),
            'assignee_id' => $assignee->id,
        ]);

        $assignee->delete();

        $this->assertSame($quote->user_id, $task->fresh()->assignee_id);
    }

    public function test_deleting_assignee_reassigns_to_creator_when_quote_has_no_owner()
    {
        $quote = Quote::create(['title' => 'Senza owner', 'customer_id' => Customer::factory()->create()->id]);
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $assignee = User::factory()->create(['roles' => [UserRole::Developer]]);
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'T',
            'due_date' => now()->addDay(),
            'creator_id' => $creator->id,
            'assignee_id' => $assignee->id,
        ]);

        $assignee->delete();

        $this->assertSame($creator->id, $task->fresh()->assignee_id);
    }

    public function test_deleting_assignee_who_is_also_creator_leaves_task_unassigned_without_owner()
    {
        $quote = Quote::create(['title' => 'Senza owner', 'customer_id' => Customer::factory()->create()->id]);
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'T',
            'due_date' => now()->addDay(),
            'creator_id' => $user->id,
            'assignee_id' => $user->id,
        ]);

        $user->delete();

        $this->assertNull($task->fresh()->assignee_id);
    }
    public function test_default_assignee_skips_owner_with_null_roles()
    {
        $quote = $this->makeQuoteWithOwner();
        DB::table('users')->where('id', $quote->user_id)->update(['roles' => null]);
        $actor = User::factory()->create(['roles' => [UserRole::Developer]]);

        $this->assertSame($actor->id, Task::defaultAssigneeId($quote->fresh(), $actor));
    }

    public function test_failed_user_delete_does_not_reassign_tasks()
    {
        // X è owner di una trattativa: la FK di quotes.user_id blocca la
        // cancellazione, e la riassegnazione dei task deve annullarsi con lei.
        $quoteOfX = $this->makeQuoteWithOwner();
        $x = $quoteOfX->user;
        $otherQuote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $otherQuote->id,
            'title' => 'T',
            'due_date' => now()->addDay(),
            'assignee_id' => $x->id,
        ]);

        try {
            $x->delete();
            $this->fail('La cancellazione di un owner di trattativa doveva fallire.');
        } catch (\Illuminate\Database\QueryException $e) {
            // atteso: violazione della FK su quotes.user_id
        }

        $this->assertTrue(User::whereKey($x->id)->exists());
        $this->assertSame($x->id, $task->fresh()->assignee_id);
    }
}
