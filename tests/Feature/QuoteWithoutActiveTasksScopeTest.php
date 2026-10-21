<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Definizione unica di «trattativa aperta senza task attivi» (oc:8729), condivisa
 * da GET /api/quotes?without_active_tasks=1 e dalla Lens Nova QuotesWithoutActiveTasks.
 */
class QuoteWithoutActiveTasksScopeTest extends TestCase
{
    use DatabaseTransactions;

    private function makeQuote(QuoteStatus $status = QuoteStatus::New): Quote
    {
        $customer = Customer::factory()->create();

        return Quote::create([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
            'status' => $status->value,
        ]);
    }

    private function makeTask(Quote $quote, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'quote_id' => $quote->id,
            'title' => 'Task di test',
            'due_date' => now()->addDay(),
        ], $overrides));
    }

    private function resultIds(): array
    {
        return Quote::query()->openWithoutActiveTasks()->pluck('id')->all();
    }

    /** @test */
    public function trattativa_aperta_senza_task_e_inclusa(): void
    {
        $quote = $this->makeQuote();

        $this->assertContains($quote->id, $this->resultIds());
    }

    /** @test */
    public function trattativa_con_soli_task_completati_e_inclusa(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Presented);
        $this->makeTask($quote, ['status' => Task::STATUS_COMPLETED, 'completed_at' => now()]);

        $this->assertContains($quote->id, $this->resultIds());
    }

    /** @test */
    public function task_todo_di_un_altro_utente_esclude_la_trattativa(): void
    {
        $quote = $this->makeQuote(QuoteStatus::On_Hold);
        $colleague = User::factory()->create();
        $this->makeTask($quote, [
            'status' => Task::STATUS_TODO,
            'creator_id' => $colleague->id,
            'assignee_id' => $colleague->id,
        ]);

        $this->assertNotContains($quote->id, $this->resultIds());
    }

    /** @test */
    public function task_todo_scaduto_conta_come_attivo(): void
    {
        $quote = $this->makeQuote();
        $this->makeTask($quote, ['status' => Task::STATUS_TODO, 'due_date' => now()->subWeek()]);

        $this->assertNotContains($quote->id, $this->resultIds());
    }

    /** @test */
    public function trattative_chiuse_sono_escluse(): void
    {
        $won = $this->makeQuote(QuoteStatus::Closed_Won);
        $lost = $this->makeQuote(QuoteStatus::Closed_Lost);

        $ids = $this->resultIds();

        $this->assertNotContains($won->id, $ids);
        $this->assertNotContains($lost->id, $ids);
    }
}
