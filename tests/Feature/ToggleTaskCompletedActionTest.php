<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Nova\Actions\ToggleTaskCompleted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

/**
 * Chiusura di un task con task successivo da Nova (oc:8730).
 *
 * I test passano dall'endpoint di esecuzione di Nova, non da handle():
 * all'esecuzione Nova ricostruisce l'action senza task e la ritrova per
 * uriKey, e solo così si vedono un uriKey che cambia col nome o i campi
 * che spariscono quando il task non c'è.
 */
class ToggleTaskCompletedActionTest extends TestCase
{
    use DatabaseTransactions;

    private const ENDPOINT = '/nova-api/tasks/action?action=toggle-task-completed';

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create(['roles' => [UserRole::Admin]]);
    }

    private function makeQuote(?User $owner): Quote
    {
        $quote = Quote::create([
            'title' => 'Sito Rossi',
            'customer_id' => Customer::factory()->create()->id,
        ]);
        $quote->user_id = $owner?->id;
        $quote->save();

        return $quote->fresh();
    }

    private function makeOpenTask(?Quote $quote = null): Task
    {
        $quote ??= $this->makeQuote(User::factory()->create(['roles' => [UserRole::Manager]]));

        $this->actingAs($this->actor);

        return Task::create([
            'quote_id' => $quote->id,
            'title' => 'Richiamare il cliente per la demo',
            'due_date' => now()->addDay(),
        ]);
    }

    private function runAction(array $resources, array $fields = [])
    {
        return $this->actingAs($this->actor)->postJson(self::ENDPOINT, array_merge([
            'resources' => $resources,
        ], $fields));
    }

    private function assigneeDefaultFor(Task $task): mixed
    {
        $action = new ToggleTaskCompleted();
        $action->resource = $task;

        $request = NovaRequest::create('/')->setUserResolver(fn () => $this->actor);
        $this->app->instance(NovaRequest::class, $request);

        $field = collect($action->fields($request))->first(fn ($field) => $field->attribute === 'assignee');
        $field->resolveForAction($request);

        return $field->value;
    }

    public function test_chiusura_con_titolo_crea_il_task_successivo()
    {
        $task = $this->makeOpenTask();
        $assignee = User::factory()->create(['roles' => [UserRole::Developer]]);

        $this->runAction([$task->id], [
            'title' => 'Inviare offerta aggiornata',
            'due_date' => '2026-10-20 09:00:00',
            'assignee' => $assignee->id,
            'notes' => '<p>Il cliente vuole l\'upsell</p>',
        ])->assertOk();

        $this->assertSame(Task::STATUS_COMPLETED, $task->fresh()->status);

        $next = Task::where('title', 'Inviare offerta aggiornata')->sole();
        $this->assertSame($task->quote_id, $next->quote_id);
        $this->assertSame($this->actor->id, $next->creator_id);
        $this->assertSame($assignee->id, $next->assignee_id);
        $this->assertSame('2026-10-20', $next->due_date->toDateString());
        $this->assertStringContainsString('upsell', $next->notes);
        $this->assertSame(Task::STATUS_TODO, $next->status);
    }

    public function test_assegnatario_precompilato_con_owner_della_trattativa()
    {
        $owner = User::factory()->create(['roles' => [UserRole::Manager]]);
        $task = $this->makeOpenTask($this->makeQuote($owner));

        $this->assertSame($owner->id, $this->assigneeDefaultFor($task));
    }

    public function test_assegnatario_precompilato_con_chi_chiude_se_la_trattativa_non_ha_owner()
    {
        $task = $this->makeOpenTask($this->makeQuote(null));

        $this->assertSame($this->actor->id, $this->assigneeDefaultFor($task));
    }

    public function test_chiusura_senza_titolo_non_crea_nulla()
    {
        $task = $this->makeOpenTask();
        $before = Task::count();

        $this->runAction([$task->id], [
            'title' => '',
            'due_date' => '2026-10-20 09:00:00',
            'assignee' => $this->actor->id,
            'notes' => '<p>Nota</p>',
        ])->assertOk();

        $this->assertSame(Task::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertSame($before, Task::count());
    }

    public function test_titolo_senza_scadenza_non_chiude_il_task()
    {
        $task = $this->makeOpenTask();
        $before = Task::count();

        $this->runAction([$task->id], [
            'title' => 'Inviare offerta aggiornata',
            'assignee' => $this->actor->id,
        ])->assertStatus(422)->assertJsonValidationErrors('due_date');

        $this->assertSame(Task::STATUS_TODO, $task->fresh()->status);
        $this->assertSame($before, Task::count());
    }

    public function test_titolo_senza_assegnatario_non_chiude_il_task()
    {
        $task = $this->makeOpenTask();
        $before = Task::count();

        $this->runAction([$task->id], [
            'title' => 'Inviare offerta aggiornata',
            'due_date' => '2026-10-20 09:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors('assignee');

        $this->assertSame(Task::STATUS_TODO, $task->fresh()->status);
        $this->assertSame($before, Task::count());
    }

    public function test_riapertura_senza_campi_e_senza_task_nuovo()
    {
        $task = $this->makeOpenTask();
        $task->status = Task::STATUS_COMPLETED;
        $task->save();
        $before = Task::count();

        $action = new ToggleTaskCompleted();
        $action->resource = $task->fresh();
        $this->assertSame([], $action->fields(NovaRequest::create('/')));
        $this->assertSame(__('Reopen'), $action->name());

        $this->runAction([$task->id], [
            'title' => 'Non deve nascere',
            'due_date' => '2026-10-20 09:00:00',
            'assignee' => $this->actor->id,
        ])->assertOk();

        $task->refresh();
        $this->assertSame(Task::STATUS_TODO, $task->status);
        $this->assertNull($task->completed_at);
        $this->assertSame($before, Task::count());
    }

    public function test_nome_su_task_aperto()
    {
        $action = new ToggleTaskCompleted();
        $action->resource = $this->makeOpenTask();

        $this->assertSame(__('Mark as completed'), $action->name());
        $this->assertSame('toggle-task-completed', $action->uriKey());
    }

    public function test_piu_task_insieme_non_modificano_nulla()
    {
        $first = $this->makeOpenTask();
        $second = $this->makeOpenTask();
        $before = Task::count();

        $this->runAction([$first->id, $second->id], [
            'title' => 'Inviare offerta aggiornata',
            'due_date' => '2026-10-20 09:00:00',
            'assignee' => $this->actor->id,
        ])->assertJsonPath('danger', __('Only one task at a time can be closed or reopened.'));

        $this->assertSame(Task::STATUS_TODO, $first->fresh()->status);
        $this->assertSame(Task::STATUS_TODO, $second->fresh()->status);
        $this->assertSame($before, Task::count());
    }
}
