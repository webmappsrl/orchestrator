<?php

namespace Tests\Unit;

use App\Models\Tag;
use App\Models\Story;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagServiceTest extends TestCase
{
    use RefreshDatabase;

    private TagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagService();
    }

    public function test_ensure_tag_creates_if_not_exists(): void
    {
        $tag = $this->service->ensureTag('osm2cai');

        $this->assertDatabaseHas('tags', ['name' => 'osm2cai']);
        $this->assertInstanceOf(Tag::class, $tag);
    }

    public function test_ensure_tag_returns_existing_tag(): void
    {
        $existing = Tag::factory()->create(['name' => 'osm2cai']);

        $tag = $this->service->ensureTag('osm2cai');

        $this->assertEquals($existing->id, $tag->id);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_attach_tag_to_story_is_idempotent(): void
    {
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
        $story = Story::factory()->create();
        \Illuminate\Database\Eloquent\Model::setEventDispatcher(app('events'));
        $tag = Tag::factory()->create(['name' => 'osm2cai']);

        $this->service->attachTagToStory($story, $tag);
        $this->service->attachTagToStory($story, $tag);

        $this->assertCount(1, $story->fresh()->tags);
    }

    public function test_quarter_tag_name_returns_correct_format(): void
    {
        $name = TagService::currentQuarterName();

        $this->assertMatchesRegularExpression('/^\d{2}Q[1-4]$/', $name);
    }

    public function test_extract_repo_names_from_text_finds_github_urls(): void
    {
        $text = 'Fix in https://github.com/webmapp/osm2cai/pull/123 and https://github.com/webmapp/wm-package/issues/5';

        $repos = TagService::extractRepoNamesFromText($text);

        $this->assertEquals(['osm2cai', 'wm-package'], $repos->toArray());
    }

    public function test_extract_repo_names_deduplicates(): void
    {
        $text = 'https://github.com/webmapp/osm2cai/pull/1 and https://github.com/webmapp/osm2cai/pull/2';

        $repos = TagService::extractRepoNamesFromText($text);

        $this->assertCount(1, $repos);
    }

    public function test_extract_repo_names_returns_empty_when_no_links(): void
    {
        $repos = TagService::extractRepoNamesFromText('Nessun link qui.');

        $this->assertCount(0, $repos);
    }
}
