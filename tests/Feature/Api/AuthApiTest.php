<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_con_credenziali_valide_restituisce_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'dev@webmapp.it',
            'password' => bcrypt('password'),
            'roles'    => [UserRole::Developer],
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'dev@webmapp.it',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    /** @test */
    public function login_con_credenziali_errate_restituisce_401(): void
    {
        User::factory()->create([
            'email' => 'dev@webmapp.it',
            'roles' => [UserRole::Developer],
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'dev@webmapp.it',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /** @test */
    public function login_con_campi_mancanti_restituisce_422(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
