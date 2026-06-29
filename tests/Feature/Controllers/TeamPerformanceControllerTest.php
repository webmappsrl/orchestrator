<?php

namespace Tests\Feature\Controllers;

use App\Models\Story;
use App\Models\User;
use App\Enums\StoryType;
use App\Enums\StoryStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TeamPerformanceControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $this->developer = User::factory()->create(['roles' => [UserRole::Developer]]);
    }

    public function test_returns_developers_list(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/nova-vendor/team-performance/data?developer_id=' . $this->developer->id . '&year=2026&quarter=2');

        $response->assertOk();
        $response->assertJsonStructure([
            'developers' => [['id', 'name']],
            'tickets',
            'aggregate' => ['developer', 'team_average'],
        ]);

        $developerIds = collect($response->json('developers'))->pluck('id');
        $this->assertContains($this->developer->id, $developerIds->toArray());
    }

    public function test_only_bug_and_feature_types_included(): void
    {
        // Crea uno story Scrum — non deve apparire
        Story::factory()->create([
            'user_id' => $this->developer->id,
            'type' => StoryType::Scrum->value,
            'status' => StoryStatus::Done->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/nova-vendor/team-performance/data?developer_id=' . $this->developer->id . '&year=2026&quarter=2');

        $response->assertOk();
        $types = collect($response->json('tickets'))->pluck('type')->unique()->toArray();
        foreach ($types as $type) {
            $this->assertContains($type, ['Bug', 'Feature']);
        }
    }

    public function test_developer_can_only_see_own_data(): void
    {
        $otherDev = User::factory()->create(['roles' => [UserRole::Developer]]);

        $response = $this->actingAs($this->developer)
            ->getJson('/nova-vendor/team-performance/data?developer_id=' . $otherDev->id . '&year=2026&quarter=2');

        // developer viene reindirizzato ai propri dati
        $response->assertOk();
        // developer_id nel response deve essere il proprio, non otherDev
        $this->assertEquals($this->developer->id, $response->json('selected_developer_id'));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/nova-vendor/team-performance/data?developer_id=1&year=2026&quarter=1');
        $response->assertUnauthorized();
    }
}
