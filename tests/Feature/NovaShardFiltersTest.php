<?php

namespace Tests\Feature;

use App\Models\App;
use App\Nova\Filters\ShardFilter;
use App\Nova\Filters\ShardStatusFilter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class NovaShardFiltersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shard_filter_filters_by_slug(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '900001']);
        App::factory()->create(['shard' => 'maphub', 'app_id' => '900001']);

        $query = (new ShardFilter())->apply(app(NovaRequest::class), App::where('app_id', '900001'), 'maphub');

        $this->assertSame(1, $query->count());
        $this->assertSame('maphub', $query->first()->shard);
    }

    public function test_shard_filter_options_come_from_config(): void
    {
        config(['shards' => ['alpha' => ['url' => 'x', 'driver' => 'geohub'], 'beta' => ['url' => 'y', 'driver' => 'wmpackage']]]);

        $this->assertSame(['alpha' => 'alpha', 'beta' => 'beta'], (new ShardFilter())->options(app(NovaRequest::class)));
    }

    public function test_status_filter_defaults_to_active_and_filters(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '900010']);
        App::factory()->create(['shard' => 'geohub', 'app_id' => '900011', 'removed_from_shard_at' => now()]);

        $filter = new ShardStatusFilter();
        $base = fn () => App::whereIn('app_id', ['900010', '900011']);

        $this->assertSame('active', $filter->default());
        $this->assertSame(1, $filter->apply(app(NovaRequest::class), $base(), 'active')->count());
        $this->assertSame(1, $filter->apply(app(NovaRequest::class), $base(), 'removed')->count());
        $this->assertSame(2, $filter->apply(app(NovaRequest::class), $base(), 'all')->count());
    }
}
