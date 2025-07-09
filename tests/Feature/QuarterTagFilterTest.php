<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tag;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Filters\QuarterTagFilter;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuarterTagFilterTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function test_it_returns_the_correct_quarter_options()
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentQuarter = (int) ceil($now->month / 3);
        $expectedLabels = [];

        for ($i = 1; $i <= 4; $i++) {
            $suffixYear = substr((string) $currentYear, -2);
            $expectedLabels[] = "{$suffixYear}Q{$currentQuarter}";

            $currentQuarter--;
            if ($currentQuarter == 0) {
                $currentQuarter = 4;
                $currentYear--;
            }
        }

        $filter = new QuarterTagFilter();
        $options = $filter->options(new NovaRequest());

        $this->assertCount(4, $options);
        $this->assertEquals($expectedLabels, array_keys($options));
    }

    public function test_it_returns_tags_matching_the_selected_quarter()
    {
        Tag::factory()->create(['name' => '[24Q4] Fixes']);
        Tag::factory()->create(['name' => '[25Q3] Frontend']);
        Tag::factory()->create(['name' => '[25Q32] Backend']);
        Tag::factory()->create(['name' => '[25Q1] API']);
        Tag::factory()->create(['name' => 'Database']);

        $filter = new QuarterTagFilter();
        $filtered = $filter->apply(NovaRequest::create('/', 'GET'), Tag::query(), '25Q3')->get();

        $this->assertCount(2, $filtered);
        foreach ($filtered as $tag) {
            $this->assertStringContainsString('25Q3', $tag->name);
        }
    }

    public function test_it_excludes_tags_without_quarter_pattern()
    {
        Tag::factory()->create(['name' => 'Random Tag']);
        Tag::factory()->create(['name' => '[ABC] Tag']);
        Tag::factory()->create(['name' => '[25Q4] Tag']);

        $filter = new QuarterTagFilter();
        $filtered = $filter->apply(NovaRequest::create('/', 'GET'), Tag::query(), '25Q3')->get();

        $this->assertCount(0, $filtered);
    }
}
