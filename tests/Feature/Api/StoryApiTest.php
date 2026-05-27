<?php

namespace Tests\Feature\Api;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->developer = User::factory()->create(['roles' => [UserRole::Developer]]);
    }

    /** @test */
    public function get_story_autenticato_restituisce_campi_corretti(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create([
            'name'   => 'Test story',
            'status' => StoryStatus::New->value,
            'type'   => StoryType::Feature->value,
        ]);

        $response = $this->getJson("/api/stories/{$story->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'name', 'status', 'type', 'description',
                'customer_request', 'user_id', 'tester_id', 'creator_id',
                'parent_id', 'estimated_hours', 'hours',
                'tags', 'created_at', 'updated_at',
            ])
            ->assertJsonFragment(['name' => 'Test story']);
    }

    /** @test */
    public function get_story_senza_autenticazione_restituisce_401(): void
    {
        $story = Story::factory()->create();

        $response = $this->getJson("/api/stories/{$story->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function get_story_non_esistente_restituisce_404(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->getJson('/api/stories/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function crea_story_con_campi_validi_restituisce_201(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->postJson('/api/stories', [
            'name'        => 'Nuova feature via API',
            'type'        => StoryType::Feature->value,
            'description' => 'Note tecniche della feature',
            'status'      => StoryStatus::New->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Nuova feature via API']);

        $this->assertDatabaseHas('stories', ['name' => 'Nuova feature via API']);
    }

    /** @test */
    public function crea_story_senza_name_restituisce_422(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->postJson('/api/stories', [
            'type' => StoryType::Feature->value,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function crea_story_con_status_non_valido_restituisce_422(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->postJson('/api/stories', [
            'name'   => 'Test',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function aggiorna_story_con_campi_validi(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create(['name' => 'Vecchio nome']);

        $response = $this->patchJson("/api/stories/{$story->id}", [
            'name'        => 'Nuovo nome',
            'description' => 'Note aggiornate',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Nuovo nome']);

        $this->assertDatabaseHas('stories', [
            'id'   => $story->id,
            'name' => 'Nuovo nome',
        ]);
    }

    /** @test */
    public function aggiorna_story_non_tocca_campi_non_passati(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create([
            'name'   => 'Nome originale',
            'status' => StoryStatus::New->value,
        ]);

        $this->patchJson("/api/stories/{$story->id}", [
            'description' => 'Solo descrizione aggiornata',
        ]);

        $this->assertDatabaseHas('stories', [
            'id'   => $story->id,
            'name' => 'Nome originale',
        ]);
    }

    /** @test */
    public function aggiorna_story_con_type_non_valido_restituisce_422(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create();

        $response = $this->patchJson("/api/stories/{$story->id}", [
            'type' => 'InvalidType',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }
}
