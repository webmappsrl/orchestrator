<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private function makeQuote(array $overrides = []): Quote
    {
        $customer = Customer::factory()->create();

        return Quote::create(array_merge([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
        ], $overrides));
    }

    private function makeTask(Quote $quote, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'quote_id' => $quote->id,
            'title' => 'Task di test',
            'due_date' => now()->addDay(),
        ], $overrides));
    }

    /** @test */
    public function customer_non_puo_vedere_nessun_task(): void
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote);

        $this->assertFalse($user->can('viewAny', Task::class));
        $this->assertFalse($user->can('view', $task));
    }

    /** @test */
    public function admin_non_puo_creare_task_su_quote_chiusa_vinta(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote(['status' => QuoteStatus::Closed_Won->value]);

        $this->assertFalse($admin->can('create', [Task::class, $quote]));
    }

    /** @test */
    public function admin_non_puo_creare_task_su_quote_chiusa_persa(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote(['status' => QuoteStatus::Closed_Lost->value]);

        $this->assertFalse($admin->can('create', [Task::class, $quote]));
    }

    /** @test */
    public function admin_puo_creare_task_su_quote_aperta(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote(['status' => QuoteStatus::New->value]);

        $this->assertTrue($admin->can('create', [Task::class, $quote]));
    }

    /** @test */
    public function nova_puo_verificare_create_senza_una_quote_specifica(): void
    {
        // Mirror della chiamata generica che Nova esegue per decidere se
        // mostrare l'azione "crea" sulla risorsa Task (nessuna istanza
        // Quote disponibile in quel momento). Deve restituire true senza
        // sollevare ArgumentCountError.
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);

        $this->assertTrue($admin->can('create', Task::class));
    }

    /** @test */
    public function creator_puo_aggiornare_status_del_proprio_task(): void
    {
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->assertTrue($creator->can('updateStatus', $task));
    }

    /** @test */
    public function non_creator_non_puo_aggiornare_status(): void
    {
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $altro = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->assertFalse($altro->can('updateStatus', $task));
    }

    /** @test */
    public function nessuno_puo_aggiornare_status_di_un_task_senza_creator(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => null]);

        $this->assertFalse($admin->can('updateStatus', $task));
    }

    /** @test */
    public function qualsiasi_ruolo_abilitato_puo_aggiornare_notes(): void
    {
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $altro = User::factory()->create(['roles' => [UserRole::Manager]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->assertTrue($altro->can('update', $task));
    }
}
