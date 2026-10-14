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
    public function index_mostra_solo_task_di_cui_lutente_e_assegnatario_o_creatore(): void
    {
        $owner = $this->loginAs([UserRole::Admin]);
        $quoteMia = $this->makeQuote();
        $quoteMia->user_id = $owner->id;
        $quoteMia->save();
        $taskMio = $this->makeTask($quoteMia);

        $quoteAltrui = $this->makeQuote();
        $altroCreator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $taskAltrui = $this->makeTask($quoteAltrui, [
            'creator_id' => $altroCreator->id,
            'assignee_id' => $altroCreator->id,
        ]);

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
    public function index_assignee_e_il_creatore_se_quote_senza_owner(): void
    {
        $creator = $this->loginAs([UserRole::Admin]);
        $quoteSenzaOwner = $this->makeQuote();
        $this->makeTask($quoteSenzaOwner);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $item = collect($response->json())->firstWhere('quote_id', $quoteSenzaOwner->id);
        $this->assertEquals(
            ['id' => $creator->id, 'name' => $creator->name, 'email' => $creator->email],
            $item['assignee']
        );
    }

    /** @test */
    public function index_non_mostra_allowner_i_task_assegnati_e_creati_da_altri(): void
    {
        $owner = $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $quote->user_id = $owner->id;
        $quote->save();
        $collega = User::factory()->create(['roles' => [UserRole::Developer]]);
        $taskDelCollega = $this->makeTask($quote, ['creator_id' => $collega->id, 'assignee_id' => $collega->id]);
        $taskAssegnatoAMe = $this->makeTask($quote, ['creator_id' => $collega->id, 'assignee_id' => $owner->id]);

        $ids = collect($this->getJson('/api/tasks')->assertStatus(200)->json())->pluck('id');

        $this->assertFalse($ids->contains($taskDelCollega->id));
        $this->assertTrue($ids->contains($taskAssegnatoAMe->id));
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
    public function chi_non_ha_creato_il_task_puo_cambiare_status(): void
    {
        $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $this->patchJson("/api/tasks/{$task->id}", ['status' => Task::STATUS_COMPLETED])->assertStatus(200);
        $this->assertEquals(Task::STATUS_COMPLETED, $task->fresh()->status);
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
    public function payload_con_status_e_notes_da_chi_non_ha_creato_il_task_salva_entrambi(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id, 'notes' => 'Nota preesistente']);

        $this->patchJson("/api/tasks/{$task->id}", [
            'status' => Task::STATUS_COMPLETED,
            'notes'  => 'Chiuso da un collega',
        ])->assertStatus(200);

        $fresh = $task->fresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $fresh->status);
        $this->assertStringContainsString('Chiuso da un collega', $fresh->notes);
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

    /** @test */
    public function creator_puo_spostare_la_scadenza(): void
    {
        $creator = $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $response = $this->patchJson("/api/tasks/{$task->id}", ['due_date' => '2026-10-15 10:30:00'])
            ->assertStatus(200);

        $this->assertStringStartsWith('2026-10-15T10:30:00', $response->json('due_date'));
        $this->assertEquals('2026-10-15 10:30:00', $task->fresh()->due_date->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function due_date_non_valida_ritorna_422(): void
    {
        $creator = $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);
        $scadenzaOriginale = $task->fresh()->due_date;

        $this->patchJson("/api/tasks/{$task->id}", ['due_date' => 'domani'])->assertStatus(422);
        $this->patchJson("/api/tasks/{$task->id}", ['due_date' => null])->assertStatus(422);

        $this->assertEquals($scadenzaOriginale, $task->fresh()->due_date);
    }

    /** @test */
    public function non_creator_puo_spostare_la_scadenza(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $this->patchJson("/api/tasks/{$task->id}", ['due_date' => '2026-10-20'])->assertStatus(200);

        $this->assertEquals('2026-10-20', $task->fresh()->due_date->format('Y-m-d'));
    }

    /** @test */
    public function payload_con_status_e_due_date_da_chi_non_ha_creato_il_task_salva_entrambi(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $this->patchJson("/api/tasks/{$task->id}", [
            'status'   => Task::STATUS_COMPLETED,
            'due_date' => '2026-10-20',
        ])->assertStatus(200);

        $fresh = $task->fresh();
        $this->assertEquals(Task::STATUS_COMPLETED, $fresh->status);
        $this->assertEquals('2026-10-20', $fresh->due_date->toDateString());
    }

    /** @test */
    public function due_date_con_fuso_orario_viene_convertita_in_ora_di_roma(): void
    {
        $creator = $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        // 22:00 UTC del 30/09 è la mezzanotte del 1/10 a Roma (ora legale, +02:00)
        $response = $this->patchJson("/api/tasks/{$task->id}", ['due_date' => '2026-09-30T22:00:00.000Z'])
            ->assertStatus(200);

        $this->assertStringStartsWith('2026-10-01T00:00:00', $response->json('due_date'));
        $this->assertEquals('2026-10-01 00:00:00', $task->fresh()->due_date->format('Y-m-d H:i:s'));

        // 23:30 UTC del 1/12 è le 00:30 del 2/12 a Roma (ora solare, +01:00)
        $this->patchJson("/api/tasks/{$task->id}", ['due_date' => '2026-12-01T23:30:00Z'])
            ->assertStatus(200);

        $this->assertEquals('2026-12-02 00:30:00', $task->fresh()->due_date->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function store_converte_due_date_con_fuso_orario_in_ora_di_roma(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();

        $response = $this->postJson('/api/tasks', [
            'quote_id' => $quote->id,
            'title'    => 'Richiamare il cliente',
            'due_date' => '2026-10-15T08:30:00+00:00',
        ])->assertStatus(201);

        $this->assertEquals('2026-10-15 10:30:00', Task::find($response->json('id'))->due_date->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function payload_con_due_date_e_notes_salva_entrambi(): void
    {
        $this->loginAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['notes' => 'Nota preesistente']);

        $this->patchJson("/api/tasks/{$task->id}", [
            'due_date' => '2026-10-20',
            'notes'    => 'Scadenza spostata su richiesta del cliente',
        ])->assertStatus(200);

        $fresh = $task->fresh();
        $this->assertEquals('2026-10-20', $fresh->due_date->format('Y-m-d'));
        $this->assertStringContainsString('Scadenza spostata su richiesta del cliente', $fresh->notes);
        $this->assertStringContainsString('Nota preesistente', $fresh->notes);
    }
    /** @test */
    public function store_senza_assignee_id_assegna_allowner_della_trattativa(): void
    {
        $this->loginAs([UserRole::Developer]);
        $owner = User::factory()->create(['roles' => [UserRole::Manager]]);
        $quote = $this->makeQuote();
        $quote->user_id = $owner->id;
        $quote->save();

        foreach ([[], ['assignee_id' => null]] as $extra) {
            $response = $this->postJson('/api/tasks', array_merge([
                'quote_id' => $quote->id,
                'title'    => 'Task',
                'due_date' => now()->addDay()->toDateString(),
            ], $extra))->assertStatus(201);

            $this->assertEquals($owner->id, $response->json('assignee.id'));
        }
    }

    /** @test */
    public function store_su_quote_senza_owner_assegna_allutente_del_token(): void
    {
        $user = $this->loginAs([UserRole::Developer]);
        $quote = $this->makeQuote();

        $response = $this->postJson('/api/tasks', [
            'quote_id' => $quote->id,
            'title'    => 'Task',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertStatus(201);

        $this->assertEquals($user->id, $response->json('assignee.id'));
    }

    /** @test */
    public function store_con_assignee_id_esplicito_lo_rispetta(): void
    {
        $this->loginAs([UserRole::Admin]);
        $collega = User::factory()->create(['roles' => [UserRole::Developer]]);
        $quote = $this->makeQuote();

        $response = $this->postJson('/api/tasks', [
            'quote_id'    => $quote->id,
            'title'       => 'Task',
            'due_date'    => now()->addDay()->toDateString(),
            'assignee_id' => $collega->id,
        ])->assertStatus(201);

        $this->assertEquals($collega->id, $response->json('assignee.id'));
        $this->assertDatabaseHas('tasks', ['id' => $response->json('id'), 'assignee_id' => $collega->id]);
    }

    /** @test */
    public function assignee_id_con_ruolo_non_ammesso_o_inesistente_ritorna_422(): void
    {
        $this->loginAs([UserRole::Admin]);
        $customer = User::factory()->create(['roles' => [UserRole::Customer]]);
        $editor = User::factory()->create(['roles' => [UserRole::Editor]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote);

        foreach ([$customer->id, $editor->id, 999999999] as $assigneeId) {
            $this->postJson('/api/tasks', [
                'quote_id'    => $quote->id,
                'title'       => 'Task',
                'due_date'    => now()->addDay()->toDateString(),
                'assignee_id' => $assigneeId,
            ])->assertStatus(422)->assertJsonValidationErrors('assignee_id');

            $this->patchJson("/api/tasks/{$task->id}", ['assignee_id' => $assigneeId])
                ->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        }
    }

    /** @test */
    public function patch_cambia_assignee_ma_non_lo_svuota(): void
    {
        $this->loginAs([UserRole::Developer]);
        $collega = User::factory()->create(['roles' => [UserRole::Manager]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote);

        $this->patchJson("/api/tasks/{$task->id}", ['assignee_id' => $collega->id])
            ->assertStatus(200)
            ->assertJsonPath('assignee.id', $collega->id);

        $this->patchJson("/api/tasks/{$task->id}", ['assignee_id' => null])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');

        $this->assertEquals($collega->id, $task->fresh()->assignee_id);
    }
}
