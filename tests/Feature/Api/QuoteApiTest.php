<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringProduct;
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

        $page1 = $this->getJson('/api/quotes?per_page=2&page=1')->assertStatus(200)->json('data');
        $page2 = $this->getJson('/api/quotes?per_page=2&page=2')->assertStatus(200)->json('data');

        $combinedIds = collect($page1)->pluck('id')->merge(collect($page2)->pluck('id'));

        $this->assertCount(3, $combinedIds);
        $this->assertCount(3, $combinedIds->unique());
        $this->assertEquals($quotes->pluck('id')->sort()->values()->all(), $combinedIds->sort()->values()->all());
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
        $customer = Customer::factory()->create(['name' => 'cliente_test']);
        $quote = Quote::factory()->create(['additional_services' => [], 'customer_id' => $customer->id]);
        $product = Product::factory()->create(['price' => 50]);
        $quote->products()->attach($product->id, ['quantity' => 2]);

        $response = $this->getJson("/api/quotes/{$quote->id}?include=customer,products")->assertStatus(200);

        $response->assertJsonPath('customer.id', $customer->id);
        $response->assertJsonPath('customer.name', 'cliente_test');
        $response->assertJsonPath('products.0.id', $product->id);
        $response->assertJsonPath('products.0.quantity', 2);
        $this->assertArrayNotHasKey('recurringProducts', $response->json());
    }
}
