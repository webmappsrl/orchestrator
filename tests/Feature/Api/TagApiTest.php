<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Tag;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsDeveloper(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        Sanctum::actingAs($user);
        return $user;
    }

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
    public function store_richiede_name(): void
    {
        $this->actingAsDeveloper();

        $this->postJson('/api/tags', [])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function store_accetta_solo_name_e_description(): void
    {
        $this->actingAsDeveloper();

        $this->postJson('/api/tags', ['name' => 'Test Tag', 'description' => 'Desc'])
            ->assertStatus(201);
    }

    /** @test */
    public function update_non_richiede_name(): void
    {
        $this->actingAsDeveloper();
        $tag = Tag::factory()->create();

        $this->patchJson("/api/tags/{$tag->id}", ['description' => 'updated'])
            ->assertStatus(200);
    }

    /** @test */
    public function index_restituisce_lista_tag(): void
    {
        $this->actingAsDeveloper();
        Tag::factory()->count(3)->create();

        $response = $this->getJson('/api/tags')->assertStatus(200);

        $this->assertGreaterThanOrEqual(3, count($response->json()));
        $response->assertJsonStructure([['id', 'name', 'description']]);
    }

    /** @test */
    public function index_filtra_per_nome(): void
    {
        $this->actingAsDeveloper();
        Tag::factory()->create(['name' => 'AlphaUnico tag']);
        Tag::factory()->create(['name' => 'BetaUnico tag']);

        $response = $this->getJson('/api/tags?search=AlphaUnico')->assertStatus(200);

        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertContains('AlphaUnico tag', $names);
        $this->assertNotContains('BetaUnico tag', $names);
    }

    /** @test */
    public function index_search_non_è_vulnerabile_a_like_injection(): void
    {
        $this->actingAsDeveloper();

        $countBefore = $this->getJson('/api/tags')->json();
        $response = $this->getJson('/api/tags?search=%25')->assertStatus(200);

        $this->assertCount(0, $response->json());
    }

    /** @test */
    public function show_restituisce_tag_con_stories(): void
    {
        $this->actingAsDeveloper();
        $tag   = Tag::factory()->create();
        $story = Story::factory()->create();
        $tag->tagged()->attach($story->id);

        $response = $this->getJson("/api/tags/{$tag->id}")->assertStatus(200);

        $response->assertJsonStructure(['id', 'name', 'description', 'stories']);
        $this->assertCount(1, $response->json('stories'));
        $response->assertJsonPath('stories.0.id', $story->id);
        $response->assertJsonPath('stories.0.name', $story->name);
        $this->assertArrayHasKey('status', $response->json('stories.0'));
        $this->assertArrayHasKey('customer_request', $response->json('stories.0'));
        $this->assertArrayHasKey('description', $response->json('stories.0'));
    }

    /** @test */
    public function show_restituisce_404_per_tag_inesistente(): void
    {
        $this->actingAsDeveloper();

        $this->getJson('/api/tags/99999')->assertStatus(404);
    }

    /** @test */
    public function customer_non_puo_accedere_alle_api_tag(): void
    {
        $this->actingAsCustomer();

        $this->getJson('/api/tags')->assertStatus(403);
    }

    /** @test */
    public function admin_puo_accedere_alle_api_tag(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/tags')->assertStatus(200);
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $this->getJson('/api/tags')->assertStatus(401);
    }

    /** @test */
    public function store_crea_tag_globale(): void
    {
        $this->actingAsDeveloper();

        $response = $this->postJson('/api/tags', [
            'name'        => 'Nuovo tag',
            'description' => 'Una descrizione',
        ])->assertStatus(201);

        $response->assertJsonStructure(['id', 'name', 'description']);
        $this->assertEquals('Nuovo tag', $response->json('name'));
        $this->assertDatabaseHas('tags', ['name' => 'Nuovo tag', 'taggable_type' => null, 'taggable_id' => null]);
    }

    /** @test */
    public function store_non_accetta_taggable_type_o_id(): void
    {
        $this->actingAsDeveloper();

        $response = $this->postJson('/api/tags', [
            'name'          => 'Tag con parent',
            'taggable_type' => 'App\Models\Project',
            'taggable_id'   => 1,
        ])->assertStatus(201);

        $this->assertDatabaseMissing('tags', ['name' => 'Tag con parent', 'taggable_type' => 'App\Models\Project']);
    }

    /** @test */
    public function update_aggiorna_name_e_description(): void
    {
        $this->actingAsDeveloper();
        $tag = Tag::factory()->create(['name' => 'Vecchio nome']);

        $response = $this->patchJson("/api/tags/{$tag->id}", [
            'name'        => 'Nuovo nome',
            'description' => 'Nuova desc',
        ])->assertStatus(200);

        $this->assertEquals('Nuovo nome', $response->json('name'));
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Nuovo nome']);
    }

    /** @test */
    public function attach_collega_story_a_tag(): void
    {
        $user  = $this->actingAsDeveloper();
        $tag   = Tag::factory()->create();
        $story = Story::factory()->create();

        $this->postJson("/api/tags/{$tag->id}/stories/{$story->id}")->assertStatus(200);

        $this->assertDatabaseHas('taggables', [
            'tag_id'        => $tag->id,
            'taggable_id'   => $story->id,
            'taggable_type' => 'App\Models\Story',
        ]);
        $this->assertDatabaseHas('story_logs', [
            'story_id' => $story->id,
            'user_id'  => $user->id,
        ]);
    }

    /** @test */
    public function attach_è_idempotente(): void
    {
        $this->actingAsDeveloper();
        $tag   = Tag::factory()->create();
        $story = Story::factory()->create();
        $tag->tagged()->attach($story->id);

        $this->postJson("/api/tags/{$tag->id}/stories/{$story->id}")->assertStatus(200);

        $this->assertEquals(1, $tag->tagged()->where('taggable_id', $story->id)->count());
    }

    /** @test */
    public function detach_scollega_story_da_tag(): void
    {
        $user  = $this->actingAsDeveloper();
        $tag   = Tag::factory()->create();
        $story = Story::factory()->create();
        $tag->tagged()->attach($story->id);

        $this->deleteJson("/api/tags/{$tag->id}/stories/{$story->id}")->assertStatus(200);

        $this->assertDatabaseMissing('taggables', [
            'tag_id'      => $tag->id,
            'taggable_id' => $story->id,
        ]);
        $this->assertDatabaseHas('story_logs', [
            'story_id' => $story->id,
            'user_id'  => $user->id,
        ]);
    }

    /** @test */
    public function detach_è_idempotente(): void
    {
        $this->actingAsDeveloper();
        $tag   = Tag::factory()->create();
        $story = Story::factory()->create();

        $this->deleteJson("/api/tags/{$tag->id}/stories/{$story->id}")->assertStatus(200);
    }
}
