<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringProduct;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function actingAsCustomer(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $this->getJson('/api/quotes')->assertStatus(401);
    }

    /** @test */
    public function customer_non_puo_accedere_alle_api_quote(): void
    {
        $this->actingAsCustomer();

        $this->getJson('/api/quotes')->assertStatus(403);
    }

    /** @test */
    public function index_restituisce_lista_con_totali(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => [], 'discount' => 0]);
        $product = Product::factory()->create(['price' => 100]);
        $quote->products()->attach($product->id, ['quantity' => 2]);

        $response = $this->getJson('/api/quotes')->assertStatus(200);

        $response->assertJsonStructure([['id', 'title', 'status', 'total', 'net_total']]);
        $item = collect($response->json())->firstWhere('id', $quote->id);
        $this->assertEquals(200.0, $item['total']);
    }

    /** @test */
    public function index_filtra_per_customer_id(): void
    {
        $this->actingAsAdmin();
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();
        Quote::factory()->create(['customer_id' => $customerA->id]);
        Quote::factory()->create(['customer_id' => $customerB->id]);

        $response = $this->getJson("/api/quotes?customer_id={$customerA->id}")->assertStatus(200);

        $customerIds = collect($response->json())->pluck('customer_id')->unique();
        $this->assertEquals([$customerA->id], $customerIds->values()->toArray());
    }

    /** @test */
    public function index_filtra_per_status(): void
    {
        $this->actingAsAdmin();
        Quote::factory()->create(['status' => QuoteStatus::New->value]);
        Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);

        $response = $this->getJson('/api/quotes?status=' . QuoteStatus::Closed_Won->value)->assertStatus(200);

        $statuses = collect($response->json())->pluck('status')->unique();
        $this->assertEquals([QuoteStatus::Closed_Won->value], $statuses->values()->toArray());
    }

    /** @test */
    public function show_restituisce_404_per_quote_inesistente(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/quotes/999999')->assertStatus(404);
    }

    /** @test */
    public function store_richiede_name_e_customer_id(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/quotes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'customer_id']);
    }

    /** @test */
    public function store_valida_customer_id_esistente(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/quotes', ['title' => 'Test', 'customer_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    /** @test */
    public function store_crea_quote(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/quotes', [
            'title'       => 'Nuovo preventivo',
            'customer_id' => $customer->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('quotes', ['customer_id' => $customer->id]);
        $this->assertEquals('Nuovo preventivo', Quote::find($response->json('id'))->title);
        $response->assertJsonStructure(['id', 'title', 'total', 'net_total']);
    }

    /** @test */
    public function update_blocca_quote_chiuso_vinto(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);

        $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Tentativo update'])
            ->assertStatus(403);
    }

    /** @test */
    public function update_aggiorna_quote_aperto(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Titolo aggiornato'])
            ->assertStatus(200);

        $this->assertEquals('Titolo aggiornato', $quote->fresh()->title);
    }

    /** @test */
    public function destroy_blocca_quote_chiuso_perso(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Lost->value]);

        $this->deleteJson("/api/quotes/{$quote->id}")->assertStatus(403);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
    }

    /** @test */
    public function destroy_elimina_quote_aperto(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->deleteJson("/api/quotes/{$quote->id}")->assertStatus(200);
        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
    }

    /** @test */
    public function update_solo_status_non_richiede_title_ne_customer_id(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => []]);

        $this->patchJson("/api/quotes/{$quote->id}", ['status' => QuoteStatus::Presented->value])
            ->assertStatus(200)
            ->assertJsonPath('status', QuoteStatus::Presented->value);

        $this->assertEquals(QuoteStatus::Presented->value, $quote->fresh()->status);
    }

    /** @test */
    public function attach_product_richiede_quantity(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/products/{$product->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    /** @test */
    public function attach_product_collega_con_quantity(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/products/{$product->id}", ['quantity' => 3])
            ->assertStatus(200);

        $this->assertDatabaseHas('product_quote', [
            'quote_id'   => $quote->id,
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);
    }

    /** @test */
    public function attach_product_e_upsert_su_seconda_chiamata(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();
        $quote->products()->attach($product->id, ['quantity' => 2]);

        $this->postJson("/api/quotes/{$quote->id}/products/{$product->id}", ['quantity' => 5])
            ->assertStatus(200);

        $this->assertEquals(1, $quote->products()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('product_quote', [
            'quote_id'   => $quote->id,
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);
    }

    /** @test */
    public function detach_product_scollega(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();
        $quote->products()->attach($product->id, ['quantity' => 1]);

        $this->deleteJson("/api/quotes/{$quote->id}/products/{$product->id}")->assertStatus(200);

        $this->assertDatabaseMissing('product_quote', ['quote_id' => $quote->id, 'product_id' => $product->id]);
    }

    /** @test */
    public function detach_product_inesistente_restituisce_404(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();

        $this->deleteJson("/api/quotes/{$quote->id}/products/{$product->id}")->assertStatus(404);
    }

    /** @test */
    public function attach_product_bloccato_su_quote_chiuso(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);
        $product = Product::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/products/{$product->id}", ['quantity' => 1])
            ->assertStatus(403);
    }

    /** @test */
    public function detach_product_bloccato_su_quote_chiuso(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create(['status' => QuoteStatus::Closed_Lost->value]);
        $product = Product::factory()->create();

        $this->deleteJson("/api/quotes/{$quote->id}/products/{$product->id}")->assertStatus(403);
    }

    /** @test */
    public function attach_recurring_product_richiede_quantity(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();
        $recurringProduct = RecurringProduct::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/recurring-products/{$recurringProduct->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    /** @test */
    public function attach_recurring_product_collega_con_quantity(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();
        $recurringProduct = RecurringProduct::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/recurring-products/{$recurringProduct->id}", ['quantity' => 2])
            ->assertStatus(200);

        $this->assertDatabaseHas('quote_recurring_product', [
            'quote_id'             => $quote->id,
            'recurring_product_id' => $recurringProduct->id,
            'quantity'             => 2,
        ]);
    }

    /** @test */
    public function detach_recurring_product_inesistente_restituisce_404(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();
        $recurringProduct = RecurringProduct::factory()->create();

        $this->deleteJson("/api/quotes/{$quote->id}/recurring-products/{$recurringProduct->id}")->assertStatus(404);
    }

    /** @test */
    public function index_espone_created_at_e_updated_at(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes')->assertStatus(200);

        $item = collect($response->json())->firstWhere('id', $quote->id);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertNotNull($item['created_at']);
    }

    /** @test */
    public function index_ordina_per_created_at_decrescente(): void
    {
        $this->actingAsAdmin();
        // 'created_at' non è in $fillable su Quote: Eloquent lo scarterebbe
        // silenziosamente via create(), quindi si forza con forceFill()+save()
        // dopo la creazione per garantire due timestamp distinti e deterministici.
        $older = Quote::factory()->create(['additional_services' => []]);
        $older->forceFill(['created_at' => now()->subDays(2)])->save();
        $newer = Quote::factory()->create(['additional_services' => []]);
        $newer->forceFill(['created_at' => now()])->save();

        $response = $this->getJson('/api/quotes?sort=-created_at')->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->filter(fn($id) => in_array($id, [$older->id, $newer->id]))->values();
        $this->assertEquals([$newer->id, $older->id], $ids->all());
    }

    /** @test */
    public function index_senza_parametri_di_paginazione_resta_un_array_semplice(): void
    {
        $this->actingAsAdmin();
        Quote::factory()->count(3)->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes')->assertStatus(200);

        $this->assertIsArray($response->json());
        $this->assertArrayNotHasKey('data', $response->json());
    }

    /** @test */
    public function index_con_per_page_restituisce_un_oggetto_paginato(): void
    {
        $this->actingAsAdmin();
        Quote::factory()->count(3)->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes?per_page=2&page=1')->assertStatus(200);

        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function index_senza_sort_esplicito_ordina_per_id_decrescente(): void
    {
        $this->actingAsAdmin();
        $first = Quote::factory()->create(['additional_services' => []]);
        $second = Quote::factory()->create(['additional_services' => []]);
        $third = Quote::factory()->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes')->assertStatus(200);

        $ids = collect($response->json())
            ->pluck('id')
            ->filter(fn($id) => in_array($id, [$first->id, $second->id, $third->id]))
            ->values();
        $this->assertEquals([$third->id, $second->id, $first->id], $ids->all());
    }

    /** @test */
    public function index_paginato_senza_sort_esplicito_non_ha_righe_duplicate_o_saltate(): void
    {
        $this->actingAsAdmin();
        $quotes = Quote::factory()->count(3)->create(['additional_services' => []]);

        // The test DB may already contain other quotes from unrelated fixtures —
        // walk every page (not just 2) so the assertion holds regardless of
        // total row count, instead of assuming these 3 are the only rows.
        $totalQuotes = Quote::count();
        $combinedIds = collect();
        $page = 1;
        do {
            $response = $this->getJson("/api/quotes?per_page=2&page={$page}")->assertStatus(200)->json();
            $combinedIds = $combinedIds->merge(collect($response['data'])->pluck('id'));
            $page++;
        } while ($page <= $response['meta']['last_page']);

        $this->assertCount($totalQuotes, $combinedIds, 'Paginating through every page should yield exactly one row per quote, no duplicates or gaps.');
        $this->assertCount($totalQuotes, $combinedIds->unique());
        $this->assertEquals($quotes->pluck('id')->sort()->values()->all(), $combinedIds->intersect($quotes->pluck('id'))->sort()->values()->all());
    }

    /** @test */
    public function index_filtra_per_piu_status(): void
    {
        $this->actingAsAdmin();
        $new = Quote::factory()->create(['additional_services' => [], 'status' => \App\Enums\QuoteStatus::New->value]);
        $presented = Quote::factory()->create(['additional_services' => [], 'status' => \App\Enums\QuoteStatus::Presented->value]);
        $cold = Quote::factory()->create(['additional_services' => [], 'status' => \App\Enums\QuoteStatus::Cold->value]);

        $response = $this->getJson('/api/quotes?' . http_build_query(['status' => [
            \App\Enums\QuoteStatus::New->value,
            \App\Enums\QuoteStatus::Presented->value,
        ]]))->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($new->id, $ids);
        $this->assertContains($presented->id, $ids);
        $this->assertNotContains($cold->id, $ids);
    }

    /** @test */
    public function index_filtra_per_status_singolo(): void
    {
        $this->actingAsAdmin();
        $onHold = Quote::factory()->create(['additional_services' => [], 'status' => QuoteStatus::On_Hold->value]);
        $presented = Quote::factory()->create(['additional_services' => [], 'status' => QuoteStatus::Presented->value]);

        $response = $this->getJson('/api/quotes?' . http_build_query(['status' => QuoteStatus::On_Hold->value]))
            ->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($onHold->id, $ids);
        $this->assertNotContains($presented->id, $ids);
    }

    /** @test */
    public function index_con_status_sconosciuto_risponde_422(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/quotes?' . http_build_query(['status' => 'xyz']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function index_con_status_in_elenco_sconosciuto_risponde_422(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/quotes?' . http_build_query(['status' => [QuoteStatus::New->value, 'xyz']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status.1']);
    }

    /** @test */
    public function index_con_status_vuoto_ignora_il_filtro(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes?status=')->assertStatus(200);

        $this->assertContains($quote->id, collect($response->json())->pluck('id')->all());
    }

    /** @test */
    public function index_con_elemento_vuoto_in_elenco_status_ignora_il_filtro(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes?status[]=')->assertStatus(200);

        $this->assertContains($quote->id, collect($response->json())->pluck('id')->all());
    }

    /** @test */
    public function index_con_elemento_vuoto_insieme_a_valori_validi_filtra_sui_valori(): void
    {
        $this->actingAsAdmin();
        $onHold = Quote::factory()->create(['additional_services' => [], 'status' => QuoteStatus::On_Hold->value]);
        $presented = Quote::factory()->create(['additional_services' => [], 'status' => QuoteStatus::Presented->value]);

        $response = $this->getJson('/api/quotes?status[]=' . urlencode(QuoteStatus::On_Hold->value) . '&status[]=')
            ->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($onHold->id, $ids);
        $this->assertNotContains($presented->id, $ids);
    }

    /** @test */
    public function show_espone_iva_e_final_price(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => [], 'discount' => 0]);
        $product = Product::factory()->create(['price' => 100]);
        $quote->products()->attach($product->id, ['quantity' => 1]);

        $response = $this->getJson("/api/quotes/{$quote->id}")->assertStatus(200);

        $response->assertJson([
            'net_total'   => 100.0,
            'iva'         => 22.0,
            'final_price' => 122.0,
        ]);
    }

    /** @test */
    public function show_senza_include_non_espone_le_relazioni(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => []]);

        $response = $this->getJson("/api/quotes/{$quote->id}")->assertStatus(200);

        $response->assertJsonMissing(['customer' => []]);
        $this->assertArrayNotHasKey('customer', $response->json());
        $this->assertArrayNotHasKey('products', $response->json());
    }

    /** @test */
    public function show_con_include_espone_customer_e_products(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create(['name' => 'cliente_test', 'full_name' => 'Cliente Test S.r.l.']);
        $quote = Quote::factory()->create(['additional_services' => [], 'customer_id' => $customer->id]);
        $product = Product::factory()->create(['price' => 50]);
        $quote->products()->attach($product->id, ['quantity' => 2]);

        $response = $this->getJson("/api/quotes/{$quote->id}?include=customer,products")->assertStatus(200);

        $response->assertJsonPath('customer.id', $customer->id);
        $response->assertJsonPath('customer.name', 'cliente_test');
        $response->assertJsonPath('customer.company_name', 'Cliente Test S.r.l.');
        $response->assertJsonPath('products.0.id', $product->id);
        $response->assertJsonPath('products.0.quantity', 2);
        $this->assertArrayNotHasKey('recurringProducts', $response->json());
    }

    /** @test */
    public function update_closed_lost_senza_motivo_risponde_422(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Presented->value]);

        $this->patchJson("/api/quotes/{$quote->id}", ['status' => QuoteStatus::Closed_Lost->value])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lost_reason');

        $this->assertEquals(QuoteStatus::Presented->value, $quote->fresh()->status);
    }

    /** @test */
    public function update_closed_lost_con_motivo_lo_salva_e_lo_restituisce(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Presented->value]);

        $this->patchJson("/api/quotes/{$quote->id}", [
            'status'      => QuoteStatus::Closed_Lost->value,
            'lost_reason' => 'Il cliente ha scelto un fornitore più economico',
        ])
            ->assertStatus(200)
            ->assertJsonPath('lost_reason', 'Il cliente ha scelto un fornitore più economico');

        $this->assertEquals('Il cliente ha scelto un fornitore più economico', $quote->fresh()->lost_reason);
    }

    /** @test */
    public function store_closed_lost_senza_motivo_risponde_422(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();

        $this->postJson('/api/quotes', [
            'title'       => 'Persa subito',
            'customer_id' => $customer->id,
            'status'      => QuoteStatus::Closed_Lost->value,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lost_reason');
    }

    /** @test */
    public function update_motivo_con_altro_stato_viene_scartato(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->patchJson("/api/quotes/{$quote->id}", [
            'status'      => QuoteStatus::Presented->value,
            'lost_reason' => 'Non dovrebbe essere salvato',
        ])
            ->assertStatus(200)
            ->assertJsonPath('lost_reason', null);

        $this->assertNull($quote->fresh()->lost_reason);
    }

    /** @test */
    public function motivo_oltre_2000_caratteri_risponde_422(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Presented->value]);

        $this->patchJson("/api/quotes/{$quote->id}", [
            'status'      => QuoteStatus::Closed_Lost->value,
            'lost_reason' => str_repeat('a', 2001),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lost_reason');
    }

    /**
     * Tre trattative dello stesso cliente (oc:8729): una aperta senza task, una aperta con un
     * task todo di un collega, una chiusa senza task.
     */
    private function makeQuotesForActiveTasksFilter(): array
    {
        $customer = Customer::factory()->create();
        $colleague = User::factory()->create();

        $uncovered = Quote::factory()->create(['customer_id' => $customer->id, 'status' => QuoteStatus::New->value]);
        $followed = Quote::factory()->create(['customer_id' => $customer->id, 'status' => QuoteStatus::Presented->value]);
        Task::create([
            'quote_id' => $followed->id,
            'title' => 'Richiamare il cliente',
            'due_date' => now()->addDay(),
            'creator_id' => $colleague->id,
            'assignee_id' => $colleague->id,
        ]);
        $closed = Quote::factory()->create(['customer_id' => $customer->id, 'status' => QuoteStatus::Closed_Won->value]);

        return [$customer, $uncovered, $followed, $closed];
    }

    /** @test */
    public function without_active_tasks_restituisce_solo_le_trattative_aperte_senza_task_todo(): void
    {
        $this->actingAsAdmin();
        [, $uncovered, $followed, $closed] = $this->makeQuotesForActiveTasksFilter();

        $ids = collect($this->getJson('/api/quotes?without_active_tasks=1')->assertStatus(200)->json())->pluck('id');

        $this->assertContains($uncovered->id, $ids);
        $this->assertNotContains($followed->id, $ids);
        $this->assertNotContains($closed->id, $ids);
    }

    /** @test */
    public function without_active_tasks_a_zero_o_assente_lascia_la_risposta_invariata(): void
    {
        $this->actingAsAdmin();
        [$customer] = $this->makeQuotesForActiveTasksFilter();

        $without = $this->getJson("/api/quotes?customer_id={$customer->id}")->assertStatus(200)->json();
        $zero = $this->getJson("/api/quotes?customer_id={$customer->id}&without_active_tasks=0")->assertStatus(200)->json();

        $this->assertCount(3, $without);
        $this->assertSame($without, $zero);
    }

    /** @test */
    public function without_active_tasks_accetta_true(): void
    {
        $this->actingAsAdmin();
        [$customer, $uncovered] = $this->makeQuotesForActiveTasksFilter();

        $ids = collect($this->getJson("/api/quotes?customer_id={$customer->id}&without_active_tasks=true")
            ->assertStatus(200)->json())->pluck('id')->all();

        $this->assertSame([$uncovered->id], $ids);
    }

    /** @test */
    public function without_active_tasks_con_valore_non_booleano_risponde_422(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/quotes?without_active_tasks=si')
            ->assertStatus(422)
            ->assertJsonValidationErrors('without_active_tasks');
    }

    /** @test */
    public function without_active_tasks_si_combina_con_customer_id(): void
    {
        $this->actingAsAdmin();
        [$customer, $uncovered] = $this->makeQuotesForActiveTasksFilter();
        // customer_id esplicito: QuoteFactory sceglie un cliente a caso fra quelli esistenti.
        $otherCustomerQuote = Quote::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'status' => QuoteStatus::New->value,
        ]);

        $ids = collect($this->getJson("/api/quotes?customer_id={$customer->id}&without_active_tasks=1")
            ->assertStatus(200)->json())->pluck('id')->all();

        $this->assertSame([$uncovered->id], $ids);
        $this->assertNotContains($otherCustomerQuote->id, $ids);
    }

    /** @test */
    public function without_active_tasks_con_solo_stati_chiusi_restituisce_elenco_vuoto(): void
    {
        $this->actingAsAdmin();
        [$customer] = $this->makeQuotesForActiveTasksFilter();

        $this->getJson("/api/quotes?customer_id={$customer->id}&status=closed%20won&without_active_tasks=1")
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    /** @test */
    public function without_active_tasks_si_combina_con_la_paginazione(): void
    {
        $this->actingAsAdmin();
        [$customer, $uncovered] = $this->makeQuotesForActiveTasksFilter();

        $response = $this->getJson("/api/quotes?customer_id={$customer->id}&without_active_tasks=1&per_page=10")
            ->assertStatus(200)
            ->json();

        $this->assertSame(1, $response['meta']['total']);
        $this->assertSame([$uncovered->id], collect($response['data'])->pluck('id')->all());
    }

    /** @test */
    public function without_active_tasks_vuoto_equivale_ad_assente(): void
    {
        $this->actingAsAdmin();
        [$customer] = $this->makeQuotesForActiveTasksFilter();

        $without = $this->getJson("/api/quotes?customer_id={$customer->id}")->assertStatus(200)->json();
        $empty = $this->getJson("/api/quotes?customer_id={$customer->id}&without_active_tasks=")->assertStatus(200)->json();

        $this->assertSame($without, $empty);
    }

    /** @test */
    public function store_salva_i_contatti_del_cliente_come_scritti(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();
        $contacts = "\"Rossi, Mario\" <m.rossi@comune.pisa.it>,\nAnna Bianchi <a.bianchi@comune.pisa.it>";

        $response = $this->postJson('/api/quotes', [
            'title'             => 'Portale Parco XY',
            'customer_id'       => $customer->id,
            'customer_contacts' => $contacts,
        ])->assertStatus(201)->assertJsonPath('customer_contacts', $contacts);

        $this->assertSame($contacts, Quote::find($response->json('id'))->customer_contacts);
    }

    /** @test */
    public function update_modifica_e_azzera_i_contatti_del_cliente(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['customer_contacts' => 'Mario Rossi <m.rossi@comune.pisa.it>']);

        $this->patchJson("/api/quotes/{$quote->id}", ['customer_contacts' => 'Anna Bianchi <a.bianchi@comune.pisa.it>'])
            ->assertStatus(200)
            ->assertJsonPath('customer_contacts', 'Anna Bianchi <a.bianchi@comune.pisa.it>');

        $this->patchJson("/api/quotes/{$quote->id}", ['customer_contacts' => null])
            ->assertStatus(200)
            ->assertJsonPath('customer_contacts', null);

        $this->assertNull($quote->fresh()->customer_contacts);
    }

    /** @test */
    public function update_senza_contatti_lascia_quelli_salvati(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['customer_contacts' => 'Mario Rossi <m.rossi@comune.pisa.it>']);

        $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Titolo nuovo'])->assertStatus(200);

        $this->assertSame('Mario Rossi <m.rossi@comune.pisa.it>', $quote->fresh()->customer_contacts);
    }

    /** @test */
    public function contatti_malformati_rispondono_422(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();

        // I casi del formato sono nel test della Rule: qui basta vedere che è collegata.
        $this->postJson('/api/quotes', [
            'title'             => 'Portale Parco XY',
            'customer_id'       => $customer->id,
            'customer_contacts' => 'Mario Rossi <m.rossi@>',
        ])->assertStatus(422)->assertJsonValidationErrors('customer_contacts');
    }

    /** @test */
    public function contatti_come_array_danno_un_solo_errore(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();

        $response = $this->patchJson("/api/quotes/{$quote->id}", ['customer_contacts' => ['Mario Rossi <m.rossi@comune.pisa.it>']])
            ->assertStatus(422);

        $this->assertCount(1, $response->json('errors.customer_contacts'));
    }

    /** @test */
    public function contatti_oltre_2000_caratteri_rispondono_422(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();

        $this->patchJson("/api/quotes/{$quote->id}", ['customer_contacts' => str_repeat('a', 1990) . ' <m@x.it>, b <b@x.it>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_contacts');
    }

    /** @test */
    public function show_restituisce_i_contatti_anche_se_vuoti(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();

        $this->getJson("/api/quotes/{$quote->id}")
            ->assertStatus(200)
            ->assertJsonPath('customer_contacts', null)
            ->assertJsonStructure(['customer_contacts']);
    }
}
