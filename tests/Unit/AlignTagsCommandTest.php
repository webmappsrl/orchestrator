<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlignTagsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleans_project_prefix_from_tag_names(): void
    {
        Tag::factory()->create(['name' => 'Project: STELVIO']);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'STELVIO']);
        $this->assertDatabaseMissing('tags', ['name' => 'Project: STELVIO']);
    }

    public function test_cleans_customer_prefix_from_tag_names(): void
    {
        Tag::factory()->create(['name' => 'Customer: Webmapp']);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'Webmapp']);
    }

    public function test_cleans_app_prefix_from_tag_names(): void
    {
        Tag::factory()->create(['name' => 'App: OSM2CAI']);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'OSM2CAI']);
    }

    public function test_cleans_main_project_for_customer_substring(): void
    {
        Tag::factory()->create(['name' => 'Project: Main project for customer ITINERA ROMANICA PLUS']);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'ITINERA ROMANICA PLUS']);
    }

    public function test_attaches_quarter_tag_to_stories_without_it(): void
    {
        $story = Story::factory()->create();

        $this->artisan('tags:align')->assertSuccessful();

        $quarterName = \App\Services\TagService::currentQuarterName();
        $this->assertTrue(
            $story->fresh()->tags->pluck('name')->contains($quarterName)
        );
    }

    public function test_attaches_quarter_tag_is_idempotent(): void
    {
        $story = Story::factory()->create();

        $this->artisan('tags:align')->assertSuccessful();
        $this->artisan('tags:align')->assertSuccessful();

        $quarterName = \App\Services\TagService::currentQuarterName();
        $this->assertCount(
            1,
            $story->fresh()->tags->where('name', $quarterName)
        );
    }

    public function test_attaches_repo_tag_from_description(): void
    {
        $story = Story::factory()->create([
            'description' => 'Fix in https://github.com/webmapp/osm2cai/pull/5',
            'customer_request' => null,
        ]);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertTrue(
            $story->fresh()->tags->pluck('name')->contains('osm2cai')
        );
    }

    public function test_aligns_customer_associated_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'client@example.com', 'roles' => ['customer']]);
        $customer = Customer::factory()->create(['email' => 'client@example.com', 'associated_user_id' => null]);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertEquals($user->id, $customer->fresh()->associated_user_id);
    }
}
