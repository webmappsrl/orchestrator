<?php

namespace Tests\Feature;

use App\Enums\EpicStatus;
use App\Enums\StoryStatus;
use App\Models\Epic;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;


class EpicStatusTest extends TestCase
{

    use RefreshDatabase;

    /**
     * @test
     * Se la epica non ha storie -> status=new
     */
    public function new_epic_with_no_stories_has_status_new()
    {
        $e = Epic::factory()->create();
        $this->assertEquals(EpicStatus::New, $e->getStatusFromStories());
    }

    /**
     * @test
     * quando la epica ha tutte le storie in status new ha status new
     */
    public function all_stories_new_then_epic_status_new()
    {
        $e = Epic::factory()->create();
        Story::factory(10)->create(['epic_id' => $e->id, 'status' => StoryStatus::New]);
        $this->assertEquals(EpicStatus::New, $e->getStatusFromStories());
    }

    /**
     * @test
     * quando la epica ha tutte le storie in status test è in status test, 
     */
    public function all_stories_test_then_epic_status_test()
    {
        $e = Epic::factory()->create();
        Story::factory(10)->create(['epic_id' => $e->id, 'status' => StoryStatus::Test]);
        $this->assertEquals(EpicStatus::Test, $e->getStatusFromStories());
    }

    /**
     * @test
     * quando la epica ha tutte le storie in status done ha status done.
     */

    public function all_stories_done_then_epic_status_done()
    {
        $e = Epic::factory()->create();
        Story::factory(10)->create(['epic_id' => $e->id, 'status' => StoryStatus::Done]);
        $this->assertEquals(EpicStatus::Done, $e->getStatusFromStories());
    }


    /**
     * @test
     * quando la epica ha almeno una storia con status diverso da new ha stato in progress, 
     */
    public function at_least_one_story_different_from_new_then_epic_status_progress()
    {
        $e = Epic::factory()->create();
        Story::factory(9)->create(['epic_id' => $e->id, 'status' => StoryStatus::New]);
        Story::factory(1)->create(['epic_id' => $e->id, 'status' => collect(StoryStatus::cases())
            ->reject(fn ($status) => $status === StoryStatus::New)->random()]);
        $this->assertEquals(EpicStatus::Progress, $e->getStatusFromStories());
    }
}
