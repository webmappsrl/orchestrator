<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Models\Tag;
use App\Models\Story;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class TagSalTest extends TestCase
{
    use DatabaseTransactions;

    protected function emptyLabel(): string
    {
        return __('Empty');
    }

    // Helper: create a Nova Tag resource and get the 'Sal' field for a tag
    private function getSalField(Tag $tag)
    {
        $request = NovaRequest::create('/nova-api/tags', 'GET');
        $resource = new \App\Nova\Tag($tag);
        $fields = collect($resource->fields($request));
        $salField = $fields->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Sal field not found');
        return $salField;
    }

    // Helper: resolve the Sal field and return the HTML string
    private function resolveSalField(Tag $tag): string
    {
        $salField = $this->getSalField($tag);
        $salField->resolve($tag);
        $this->assertIsString($salField->value, 'Sal field does not return a string');
        return $salField->value;
    }

    // Helper: create a tag with optional estimate and optional related stories
    private function createTagWithStories(?float $estimate = null, array $storyHours = []): Tag
    {
        $tag = Tag::factory()->create(['estimate' => $estimate]);

        foreach ($storyHours as $hours) {
            $story = Story::factory()->create(['hours' => $hours]);
            $story->tags()->attach($tag->id);
        }

        return $tag;
    }

    public function test_sal_shows_empty_when_no_total_and_no_estimate()
    {
        $tag = $this->createTagWithStories(null);

        $totalHours = $tag->getTotalHoursAttribute();
        $this->assertTrue($totalHours === null || $totalHours === 0.0);

        $salHtml = $this->resolveSalField($tag);
        $this->assertStringContainsString("<a style=\"font-weight:bold;\"> {$this->emptyLabel()} </a>", $salHtml);
    }

    public function test_sal_shows_total_hours_with_empty_estimate()
    {
        $tag = $this->createTagWithStories(null, [15.00, 20.87]);

        $salHtml = $this->resolveSalField($tag);
        $this->assertStringContainsString("<a style=\"font-weight:bold;\"> 35.87 / {$this->emptyLabel()} </a>", $salHtml);
    }

    public function test_sal_shows_estimate_with_empty_total()
    {
        $tag = $this->createTagWithStories(32);

        $salHtml = $this->resolveSalField($tag);
        $this->assertStringContainsString("<a style=\"font-weight:bold;\"> {$this->emptyLabel()} / 32 </a>", $salHtml);
    }

    public function test_sal_shows_total_and_estimate_with_percentage()
    {
        $tag = $this->createTagWithStories(48, [20.75, 20.00]);

        $salHtml = $this->resolveSalField($tag);
        $this->assertStringContainsString('😊', $salHtml);
        $this->assertStringContainsString('<a style="color:orange; font-weight:bold;"> 40.75 / 48 </a>', $salHtml);
        $this->assertStringContainsString('<a style="color:orange; font-weight:bold;"> [84.9%] </a>', $salHtml);
    }

    public function test_sal_shows_angry_trend_when_percentage_is_100_or_more()
    {
        $tag = $this->createTagWithStories(20, [50]);

        $salHtml = $this->resolveSalField($tag);
        $this->assertStringContainsString('😡', $salHtml);
    }

    public function test_sal_shows_smile_trend_when_percentage_is_below_100()
    {
        $tag = $this->createTagWithStories(20, [15]);

        $salHtml = $this->resolveSalField($tag);
        $this->assertStringContainsString('😊', $salHtml);
    }

    public function test_sal_shows_green_color_when_tag_is_closed()
    {
        $tag = Tag::factory()->create(['estimate' => 20]);
        $story = Story::factory()->create(['hours' => 15, 'status' => StoryStatus::Done]);
        $story->tags()->attach($tag->id);

        $this->assertTrue($tag->isClosed(), 'The tag must be closed');

        $salHtml = $this->resolveSalField($tag);
        $this->assertStringContainsString('color:green', $salHtml);
    }

    public function test_sal_shows_orange_color_when_tag_is_open()
    {
        $tag = Tag::factory()->create(['estimate' => 20]);
        $story = Story::factory()->create(['hours' => 15, 'status' => StoryStatus::Progress]);
        $story->tags()->attach($tag->id);

        $salHtml = $this->resolveSalField($tag);
        $this->assertStringContainsString('color:orange', $salHtml);
    }
}
