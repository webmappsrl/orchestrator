<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function actingAsCustomerRole(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $this->getJson('/api/customers')->assertStatus(401);
    }

    /** @test */
    public function ruolo_customer_non_puo_accedere(): void
    {
        $this->actingAsCustomerRole();

        $this->getJson('/api/customers')->assertStatus(403);
    }

    /** @test */
    public function index_restituisce_i_campi_attesi(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $customer = Customer::factory()->create([
            'name'      => 'acme_srl',
            'full_name' => 'Acme S.r.l.',
            'vat'       => '01234567890',
            'address'   => 'Via Roma 1, Pisa',
            'email'     => 'a@acme.it,b@acme.it',
            'phone'     => '0501234567',
            'status'    => 'active',
            'user_id'   => $owner->id,
            'notes'     => 'nota interna',
        ]);

        $response = $this->getJson('/api/customers')->assertStatus(200);

        $item = collect($response->json())->firstWhere('id', $customer->id);
        $this->assertEquals('acme_srl', $item['name']);
        $this->assertEquals('Acme S.r.l.', $item['company_name']);
        $this->assertEquals('01234567890', $item['vat']);
        $this->assertEquals('Via Roma 1, Pisa', $item['address']);
        $this->assertEquals(['a@acme.it', 'b@acme.it'], $item['contact_emails']);
        $this->assertEquals('0501234567', $item['phone']);
        $this->assertEquals('active', $item['status']);
        $this->assertEquals(['id' => $owner->id, 'name' => $owner->name], $item['owner']);
        $this->assertEquals('nota interna', $item['notes']);
    }

    /** @test */
    public function show_restituisce_il_singolo_customer(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create(['name' => 'progetto_x']);

        $this->getJson("/api/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $customer->id)
            ->assertJsonPath('name', 'progetto_x');
    }

    /** @test */
    public function filtro_per_status_funziona(): void
    {
        $this->actingAsAdmin();
        Customer::factory()->create(['status' => 'active']);
        Customer::factory()->create(['status' => 'lost']);

        $response = $this->getJson('/api/customers?status=active')->assertStatus(200);

        $this->assertTrue(collect($response->json())->every(fn($c) => $c['status'] === 'active'));
    }

    /** @test */
    public function ricerca_per_nome_sanitizza_i_caratteri_like(): void
    {
        $this->actingAsAdmin();
        Customer::factory()->create(['name' => 'acme_srl']);
        Customer::factory()->create(['name' => 'altro_cliente']);

        $response = $this->getJson('/api/customers?search=acme%25')->assertStatus(200);

        $this->assertCount(0, $response->json());
    }
}
