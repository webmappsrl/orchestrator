<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StoryLogOverrideTest extends TestCase
{
    use DatabaseTransactions;

    private function makeStory(): Story
    {
        return Story::factory()->create([
            'status'  => StoryStatus::Assigned->value,
            'type'    => StoryType::Helpdesk->value,
            'user_id' => null,
        ]);
    }

    /** @test */
    public function save_creates_story_log_on_field_change(): void
    {
        $story = $this->makeStory();
        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $story->status = StoryStatus::Progress->value;
        $story->save();

        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertEquals(StoryStatus::Progress->value, $log->changes['status']);
    }

    /** @test */
    public function save_quietly_also_creates_story_log(): void
    {
        $story = $this->makeStory();
        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $story->status = StoryStatus::Done->value;
        $story->saveQuietly();

        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertEquals(StoryStatus::Done->value, $log->changes['status']);
    }

    /** @test */
    public function save_without_changes_does_not_create_story_log(): void
    {
        $story = $this->makeStory();
        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $story->save(); // nessun campo dirty

        $this->assertEquals($logsBefore, StoryLog::where('story_id', $story->id)->count());
    }

    /** @test */
    public function save_on_new_story_does_not_create_story_log(): void
    {
        $logsCount = StoryLog::count();

        Story::factory()->create([
            'status' => StoryStatus::New->value,
            'type'   => StoryType::Helpdesk->value,
        ]);

        $this->assertEquals($logsCount, StoryLog::count());
    }

    /** @test */
    public function save_uses_system_user_when_no_auth(): void
    {
        $systemUser = User::firstOrCreate(
            ['email' => 'orchestrator_artisan@webmapp.it'],
            ['name' => 'Orchestrator Artisan', 'password' => bcrypt('unused')]
        );
        $story = $this->makeStory();

        $story->status = StoryStatus::Done->value;
        $story->saveQuietly();

        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertEquals($systemUser->id, $log->user_id);
    }
}
