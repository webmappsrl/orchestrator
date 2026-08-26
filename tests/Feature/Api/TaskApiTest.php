<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use DatabaseTransactions;

    private function loginAs(array $roles = [UserRole::Admin]): User
    {
        $user = User::factory()->create(['roles' => $roles]);
        Sanctum::actingAs($user);
        return $user;
    }

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
    public function utente_non_autenticato_ottiene_401(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }

    /** @test */
    public function customer_non_puo_accedere_alle_api_task(): void
    {
        $this->loginAs([UserRole::Customer]);

        $this->getJson('/api/tasks')->assertStatus(403);
    }

    /** @test */
    public function index_mostra_solo_task_di_cui_lutente_e_owner_o_creatore(): void
    {
        $owner = $this->loginAs([UserRole::Admin]);
        $quoteMia = $this->makeQuote();
        $quoteMia->user_id = $owner->id;
        $quoteMia->save();
        $taskMio = $this->makeTask($quoteMia);

        $quoteAltrui = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $taskAltrui = $this->makeTask($quoteAltrui, ['creator_id' => $altroCreator->id]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($taskMio->id));
        $this->assertFalse($ids->contains($taskAltrui->id));
    }

    /** @test */
    public function index_mostra_task_creati_dallutente_anche_su_quote_non_proprie(): void
    {
        $user = $this->loginAs([UserRole::Developer]);
        $quoteAltrui = $this->makeQuote();
        $taskCreatoDaMe = $this->makeTask($quoteAltrui, ['creator_id' => $user->id]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($taskCreatoDaMe->id));
    }

    /** @test */
    public function index_ordina_per_due_date_asc_di_default(): void
    {
        $user = $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $quote->user_id = $user->id;
        $quote->save();

        $tardi = $this->makeTask($quote, ['due_date' => now()->addDays(10)]);
        $presto = $this->makeTask($quote, ['due_date' => now()->addDay()]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->values();
        $this->assertEquals([$presto->id, $tardi->id], $ids->toArray());
    }

    /** @test */
    public function index_sort_created_at_desc_mostra_i_piu_recenti_prima(): void
    {
        $user = $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $quote->user_id = $user->id;
        $quote->save();

        $vecchio = $this->makeTask($quote);
        $nuovo = $this->makeTask($quote);

        $response = $this->getJson('/api/tasks?sort=-created_at')->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->values();
        $this->assertEquals([$nuovo->id, $vecchio->id], $ids->toArray());
    }

    /** @test */
    public function index_include_sempre_assignee_e_quote_title(): void
    {
        $owner = User::factory()->create(['name' => 'Owner Quote', 'roles' => [UserRole::Admin]]);
        Sanctum::actingAs($owner);
        $quote = $this->makeQuote(['title' => 'Titolo Quote di Test']);
        $quote->user_id = $owner->id;
        $quote->save();
        $this->makeTask($quote, ['creator_id' => $owner->id]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $item = $response->json()[0];
        $this->assertEquals('Titolo Quote di Test', $item['quote_title']);
        $this->assertEquals('Owner Quote', $item['assignee']['name']);
    }

    /** @test */
    public function index_assignee_null_se_quote_senza_owner(): void
    {
        $creator = $this->loginAs([UserRole::Admin]);
        $quoteSenzaOwner = $this->makeQuote();
        $this->makeTask($quoteSenzaOwner, ['creator_id' => $creator->id]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $item = collect($response->json())->firstWhere('quote_id', $quoteSenzaOwner->id);
        $this->assertArrayHasKey('assignee', $item);
        $this->assertNull($item['assignee']);
    }

    /** @test */
    public function show_ritorna_il_dettaglio_del_task(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote);

        $response = $this->getJson("/api/tasks/{$task->id}")->assertStatus(200);

        $response->assertJson(['id' => $task->id, 'title' => $task->title]);
    }

    /** @test */
    public function show_e_ruolo_only_non_richiede_ownership(): void
    {
        $this->loginAs([UserRole::Manager]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $taskAltrui = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $this->getJson("/api/tasks/{$taskAltrui->id}")->assertStatus(200);
    }

    /** @test */
    public function store_crea_un_task_su_quote_aperta(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote(['status' => QuoteStatus::New->value]);

        $response = $this->postJson('/api/tasks', [
            'quote_id' => $quote->id,
            'title'    => 'Richiamare il cliente',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertStatus(201);

        $response->assertJson(['quote_id' => $quote->id, 'title' => 'Richiamare il cliente', 'status' => Task::STATUS_TODO]);
        $this->assertDatabaseHas('tasks', ['quote_id' => $quote->id, 'title' => 'Richiamare il cliente']);
    }

    /** @test */
    public function store_su_quote_chiusa_ritorna_403(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote(['status' => QuoteStatus::Closed_Won->value]);

        $this->postJson('/api/tasks', [
            'quote_id' => $quote->id,
            'title'    => 'Follow-up non permesso',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertStatus(403);
    }

    /** @test */
    public function store_senza_quote_id_ritorna_422(): void
    {
        $this->loginAs([UserRole::Admin]);

        $this->postJson('/api/tasks', [
            'title'    => 'Task senza quote',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertStatus(422);
    }

    /** @test */
    public function store_ignora_creator_id_nel_payload(): void
    {
        $user = $this->loginAs([UserRole::Admin]);
        $altro = User::factory()->create();
        $quote = $this->makeQuote();

        $response = $this->postJson('/api/tasks', [
            'quote_id'   => $quote->id,
            'title'      => 'Task con creator_id spoofato',
            'due_date'   => now()->addDay()->toDateString(),
            'creator_id' => $altro->id,
        ])->assertStatus(201);

        $this->assertEquals($user->id, $response->json('creator_id'));
        $this->assertDatabaseHas('tasks', ['title' => 'Task con creator_id spoofato', 'creator_id' => $user->id]);
    }

    /** @test */
    public function store_ammette_due_date_nel_passato(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();

        $this->postJson('/api/tasks', [
            'quote_id' => $quote->id,
            'title'    => 'Task retroattivo',
            'due_date' => now()->subWeek()->toDateString(),
        ])->assertStatus(201);
    }

    /** @test */
    public function creator_puo_segnare_il_proprio_task_completato_e_completed_at_si_valorizza(): void
    {
        $creator = $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => Task::STATUS_COMPLETED])
            ->assertStatus(200);

        $response->assertJson(['status' => Task::STATUS_COMPLETED]);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    /** @test */
    public function riaprire_un_task_azzera_completed_at(): void
    {
        $creator = $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id, 'status' => Task::STATUS_COMPLETED, 'completed_at' => now()]);

        $this->patchJson("/api/tasks/{$task->id}", ['status' => Task::STATUS_TODO])->assertStatus(200);

        $this->assertNull($task->fresh()->completed_at);
    }

    /** @test */
    public function non_creator_non_puo_cambiare_status(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $this->patchJson("/api/tasks/{$task->id}", ['status' => Task::STATUS_COMPLETED])->assertStatus(403);
        $this->assertEquals(Task::STATUS_TODO, $task->fresh()->status);
    }

    /** @test */
    public function non_creator_puo_aggiungere_solo_notes(): void
    {
        $this->loginAs([UserRole::Manager]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $response = $this->patchJson("/api/tasks/{$task->id}", ['notes' => 'Email inviata al cliente'])
            ->assertStatus(200);

        $this->assertStringContainsString('Email inviata al cliente', $response->json('notes'));
        $this->assertStringContainsString('Email inviata al cliente', $task->fresh()->notes);
    }

    /** @test */
    public function payload_misto_da_non_creator_fallisce_tutto_o_niente(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id, 'notes' => 'Nota preesistente']);

        $this->patchJson("/api/tasks/{$task->id}", [
            'status' => Task::STATUS_COMPLETED,
            'notes'  => 'Questa nota non deve essere salvata',
        ])->assertStatus(403);

        $fresh = $task->fresh();
        $this->assertEquals(Task::STATUS_TODO, $fresh->status);
        $this->assertEquals('Nota preesistente', $fresh->notes);
    }

    /** @test */
    public function status_non_valido_ritorna_422(): void
    {
        $creator = $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->patchJson("/api/tasks/{$task->id}", ['status' => 'archived'])->assertStatus(422);
    }

    /** @test */
    public function update_ignora_creator_id_nel_payload(): void
    {
        $creator = $this->loginAs([UserRole::Developer]);
        $altro = User::factory()->create();
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->patchJson("/api/tasks/{$task->id}", [
            'notes'      => 'Nota qualsiasi',
            'creator_id' => $altro->id,
        ])->assertStatus(200);

        $this->assertEquals($creator->id, $task->fresh()->creator_id);
    }
}
