<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\Tag;
use App\Nova\Metrics\TagHoursTotal;
use App\Nova\Tag as TagNovaResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Mockery;
use Tests\TestCase;

class TagHoursMetricsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * NovaRequest come sulla detail metric (findModel → Tag). Mock evita routing Nova nei test.
     */
    private function novaDetailMetricRequest(Tag $tag): NovaRequest
    {
        $request = Mockery::mock(NovaRequest::class);
        $request->shouldReceive('findModel')->once()->withAnyArgs()->andReturn($tag);

        return $request;
    }

    public function test_estimated_hours_metric_uses_tag_estimate_like_sal_card(): void
    {
        $tag = Tag::factory()->create(['estimate' => 999.0]);
        $s1 = Story::factory()->create(['estimated_hours' => 2.5, 'hours' => 1.0]);
        $s2 = Story::factory()->create(['estimated_hours' => 3.5, 'hours' => 1.0]);
        $s1->tags()->attach($tag->id);
        $s2->tags()->attach($tag->id);

        $result = (new TagHoursTotal('estimated'))->calculate($this->novaDetailMetricRequest($tag));

        $this->assertEqualsWithDelta(999.0, (float) $result->value, 0.001);
    }

    public function test_estimated_hours_metric_uses_tag_estimate_even_when_stories_have_null_estimated_hours(): void
    {
        $tag = Tag::factory()->create(['estimate' => 8.0]);
        $story = Story::factory()->create(['estimated_hours' => null, 'hours' => 10.0]);
        $story->tags()->attach($tag->id);

        $result = (new TagHoursTotal('estimated'))->calculate($this->novaDetailMetricRequest($tag));

        $this->assertEqualsWithDelta(8.0, (float) $result->value, 0.001);
    }

    public function test_effective_hours_metric_is_zero_when_tag_has_no_stories(): void
    {
        $tag = Tag::factory()->create();
        $this->assertFalse($tag->tagged()->exists(), 'Tag should have no linked stories');

        $result = (new TagHoursTotal('effective'))->calculate($this->novaDetailMetricRequest($tag));

        $this->assertEqualsWithDelta(0.0, (float) $result->value, 0.001);
    }

    public function test_effective_hours_metric_sums_story_hours(): void
    {
        $tag = Tag::factory()->create();
        $s1 = Story::factory()->create(['hours' => 10.25, 'estimated_hours' => null]);
        $s2 = Story::factory()->create(['hours' => 5.5, 'estimated_hours' => null]);
        $s1->tags()->attach($tag->id);
        $s2->tags()->attach($tag->id);

        $result = (new TagHoursTotal('effective'))->calculate($this->novaDetailMetricRequest($tag));

        $this->assertEqualsWithDelta(15.75, (float) $result->value, 0.001);
    }

    public function test_effective_hours_metric_returns_zero_when_no_story_hours(): void
    {
        $tag = Tag::factory()->create();
        $story = Story::factory()->create(['hours' => null]);
        $story->tags()->attach($tag->id);

        $result = (new TagHoursTotal('effective'))->calculate($this->novaDetailMetricRequest($tag));

        $this->assertEqualsWithDelta(0.0, (float) $result->value, 0.001);
    }

    public function test_nova_tag_resource_includes_hours_metrics_on_detail(): void
    {
        $request = NovaRequest::create('/nova-api/tags/1', 'GET');
        $cards = (new TagNovaResource(Tag::factory()->make()))->cards($request);

        $hoursCards = array_values(array_filter($cards, fn ($card) => $card instanceof TagHoursTotal));
        $this->assertCount(2, $hoursCards);

        $uriKeys = array_map(fn (TagHoursTotal $card) => $card->uriKey(), $hoursCards);
        sort($uriKeys);

        $this->assertSame(
            ['tag-effective-hours-total', 'tag-estimated-hours-total'],
            $uriKeys
        );
    }
}
