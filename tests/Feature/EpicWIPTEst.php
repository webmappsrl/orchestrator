<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Models\Epic;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Work In Progress ("n / t") tests
 * public function when_epic_has_no_stories_then_sal_is_ND
 * public function when_no_stories_are_done_or_test_then_sal_is_zero_over_five
 * public function when_all_stories_are_new_then_sal_is_zero_over_five 
 * public function when_all_stories_are_progress_then_sal_is_zero_over_five
 * public function when_all_stories_are_rejected_then_sal_is_zero_over_five
 * public function when_all_stories_are_test_then_sal_is_five_over_five
 * public function when_all_stories_are_done_then_sal_is_five_over_five
 * public function when_all_stories_are_done_or_test_then_sal_is_five_over_five
 * public function when_three_stories_are_done_then_sal_is_three_over_five
 * public function when_three_stories_are_test_then_sal_is_three_over_five
 * public function when_three_stories_are_test_or_done_then_sal_is_three_over_five
 */
class EpicWIPTEst extends TestCase
{
    use RefreshDatabase;

    /**
     * @test 
     */
    public function when_epic_has_no_stories_then_sal_is_ND() {
        $e = Epic::factory()->create();
        $this->assertEquals('ND',$e->wip());

    }

    /**
     * @test
     */
    public function when_no_stories_are_done_or_test_then_sal_is_zero_over_five () {
        $e = $this->getEpic([
            StoryStatus::New,
            StoryStatus::Progress,
            StoryStatus::Rejected,
            StoryStatus::New,
            StoryStatus::New, 
        ]);
        $this->assertEquals('0 / 5',$e->wip());
    }

    /**
     * @test
     */
    public function when_all_stories_are_new_then_sal_is_zero_over_five () {
        $e = $this->getEpic([
            StoryStatus::New,
            StoryStatus::New,
            StoryStatus::New,
            StoryStatus::New,
            StoryStatus::New,
        ]);
        $this->assertEquals('0 / 5',$e->wip());
    }

    /**
     * @test
     */
    public function when_all_stories_are_progress_then_sal_is_zero_over_five () {
        $e = $this->getEpic([
            StoryStatus::Progress,
            StoryStatus::Progress,
            StoryStatus::Progress,
            StoryStatus::Progress,
            StoryStatus::Progress,
        ]);
        $this->assertEquals('0 / 5',$e->wip());
    }

    /**
     * @test
     */
    public function when_all_stories_are_rejected_then_sal_is_zero_over_five () {
        $e = $this->getEpic([
            StoryStatus::Rejected,
            StoryStatus::Rejected,
            StoryStatus::Rejected,
            StoryStatus::Rejected,
            StoryStatus::Rejected,
        ]);
        $this->assertEquals('0 / 5',$e->wip());
    }
    /**
     * @test
     */
    public function when_all_stories_are_test_then_sal_is_five_over_five () {
        $e = $this->getEpic([
            StoryStatus::Test,
            StoryStatus::Test,
            StoryStatus::Test,
            StoryStatus::Test,
            StoryStatus::Test,
        ]);
        $this->assertEquals('5 / 5',$e->wip());
    }
    /**
     * @test
     */
    public function when_all_stories_are_done_then_sal_is_five_over_five () {
        $e = $this->getEpic([
            StoryStatus::Done,
            StoryStatus::Done,
            StoryStatus::Done,
            StoryStatus::Done,
            StoryStatus::Done,
        ]);
        $this->assertEquals('5 / 5',$e->wip());
    }
    /**
     * @test
     */
    public function when_all_stories_are_done_or_test_then_sal_is_five_over_five () {
        $e = $this->getEpic([
            StoryStatus::Done,
            StoryStatus::Test,
            StoryStatus::Done,
            StoryStatus::Test,
            StoryStatus::Done,
        ]);
        $this->assertEquals('5 / 5',$e->wip());
    }

    /**
     * @test
     */
    public function when_three_stories_are_done_then_sal_is_three_over_five () {
        $e = $this->getEpic([
            StoryStatus::Done,
            StoryStatus::Done,
            StoryStatus::Done,
            StoryStatus::New,
            StoryStatus::New,
        ]);
        $this->assertEquals('3 / 5',$e->wip());
    }
    /**
     * @test
     */
    public function when_three_stories_are_test_then_sal_is_three_over_five () {
        $e = $this->getEpic([
            StoryStatus::Test,
            StoryStatus::Test,
            StoryStatus::Test,
            StoryStatus::New,
            StoryStatus::New,
        ]);
        $this->assertEquals('3 / 5',$e->wip());
    }
    /**
     * @test
     */
    public function when_three_stories_are_test_or_done_then_sal_is_three_over_five () {
        $e = $this->getEpic([
            StoryStatus::Test,
            StoryStatus::Done,
            StoryStatus::Test,
            StoryStatus::New,
            StoryStatus::New,
        ]);
        $this->assertEquals('3 / 5',$e->wip());
    }
 
    private function getEpic(array $statuses) : Epic {
        $e = Epic::factory()->create();
        foreach($statuses as $s) {
            Story::factory()->create(['epic_id'=>$e->id,'status'=>$s]);
        }
        return $e;
    }    
}
