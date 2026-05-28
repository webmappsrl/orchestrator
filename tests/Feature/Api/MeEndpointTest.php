<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function utente_autenticato_ottiene_id_name_email(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertExactJson([
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ]);
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }
}
