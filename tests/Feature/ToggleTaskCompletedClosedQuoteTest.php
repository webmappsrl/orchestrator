<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Review oc:8730 (19/10/2026): su una trattativa chiusa il task successivo
 * non deve nascere — TaskPolicy::create() lo vieta e l'API risponde 403,
 * l'action deve fare lo stesso. La chiusura senza titolo resta valida
 * («io quella comunque la chiudo sempre»).
 */
class ToggleTaskCompletedClosedQuoteTest extends TestCase
{
    use DatabaseTransactions;

    private const ENDPOINT = '/nova-api/tasks/action?action=toggle-task-completed';

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create(['roles' => [UserRole::Admin]]);
    }

    private function makeOpenTaskOnClosedQuote(): Task
    {
        $quote = Quote::create([
            'title' => 'Trattativa chiusa',
            'customer_id' => Customer::factory()->create()->id,
        ]);
        $quote->status = QuoteStatus::Closed_Won->value;
        $quote->save();

        $this->actingAs($this->actor);

        return Task::create([
            'quote_id' => $quote->id,
            'title' => 'Ultimo task rimasto aperto',
            'due_date' => now()->addDay(),
        ]);
    }

    public function test_su_trattativa_chiusa_il_titolo_non_crea_il_task_successivo()
    {
        $task = $this->makeOpenTaskOnClosedQuote();
        $before = Task::count();

        $this->actingAs($this->actor)->postJson(self::ENDPOINT, [
            'resources' => [$task->id],
            'title' => 'Follow-up su trattativa chiusa',
            'due_date' => '2026-10-21 09:00:00',
            'assignee' => $this->actor->id,
        ]);

        $this->assertSame($before, Task::count());
    }

    public function test_su_trattativa_chiusa_senza_titolo_il_task_si_chiude()
    {
        $task = $this->makeOpenTaskOnClosedQuote();
        $before = Task::count();

        $this->actingAs($this->actor)->postJson(self::ENDPOINT, [
            'resources' => [$task->id],
            'title' => '',
        ])->assertOk();

        $this->assertSame(Task::STATUS_COMPLETED, $task->fresh()->status);
        $this->assertSame($before, Task::count());
    }
}
