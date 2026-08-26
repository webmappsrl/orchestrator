<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskAppendNoteTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTask(): Task
    {
        $customer = Customer::factory()->create();
        $quote = Quote::create([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
        ]);

        return Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task di test',
            'due_date' => now()->addDay(),
            'notes' => 'Nota originale',
        ]);
    }

    /** @test */
    public function appendNote_prepende_la_nuova_nota_e_preserva_quella_esistente(): void
    {
        $user = User::factory()->create(['name' => 'Mario Rossi']);
        Sanctum::actingAs($user);
        $task = $this->makeTask();

        $task->appendNote('Chiamato il cliente, richiamare domani');

        $this->assertStringContainsString('Mario Rossi', $task->notes);
        $this->assertStringContainsString('Chiamato il cliente, richiamare domani', $task->notes);
        $this->assertStringContainsString('Nota originale', $task->notes);
        $this->assertTrue(
            strpos($task->notes, 'Chiamato il cliente') < strpos($task->notes, 'Nota originale'),
            'La nuova nota deve precedere quella esistente (prepend, non append)'
        );
    }

    /** @test */
    public function appendNote_persiste_su_db_di_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $task = $this->makeTask();

        $task->appendNote('Nota persistita');

        $this->assertStringContainsString('Nota persistita', $task->fresh()->notes);
    }

    /** @test */
    public function appendNote_non_persiste_se_persist_false(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $task = $this->makeTask();

        $task->appendNote('Nota non persistita', false);

        $this->assertStringNotContainsString('Nota non persistita', $task->fresh()->notes);
    }

    /** @test */
    public function appendNote_funziona_anche_se_notes_e_inizialmente_vuoto(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $customer = Customer::factory()->create();
        $quote = Quote::create(['title' => 'Quote', 'customer_id' => $customer->id]);
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task senza note iniziali',
            'due_date' => now()->addDay(),
        ]);

        $task->appendNote('Prima nota');

        $this->assertStringContainsString('Prima nota', $task->notes);
    }
}
