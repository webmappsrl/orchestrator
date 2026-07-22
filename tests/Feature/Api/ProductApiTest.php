<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\RecurringProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
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
    public function utente_non_autenticato_ottiene_401_su_products(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
    }

    /** @test */
    public function customer_non_puo_accedere_a_products(): void
    {
        $this->actingAsCustomer();

        $this->getJson('/api/products')->assertStatus(403);
    }

    /** @test */
    public function index_restituisce_lista_products(): void
    {
        $this->actingAsAdmin();
        Product::factory()->count(2)->create();

        $response = $this->getJson('/api/products')->assertStatus(200);

        $response->assertJsonStructure([['id', 'name', 'price']]);
        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }

    /** @test */
    public function index_restituisce_lista_recurring_products(): void
    {
        $this->actingAsAdmin();
        RecurringProduct::factory()->count(2)->create();

        $response = $this->getJson('/api/recurring-products')->assertStatus(200);

        $response->assertJsonStructure([['id', 'name', 'price']]);
        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }
}
