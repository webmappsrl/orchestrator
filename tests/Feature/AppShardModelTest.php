<?php

namespace Tests\Feature;

use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AppShardModelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_same_remote_app_id_on_different_shards_is_allowed(): void
    {
        $a = App::factory()->create(['shard' => 'geohub', 'app_id' => '1']);
        $b = App::factory()->create(['shard' => 'maphub', 'app_id' => '1']);

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(2, App::where('app_id', '1')->count());
    }

    public function test_same_remote_app_id_on_same_shard_is_rejected(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '7']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        App::factory()->create(['shard' => 'geohub', 'app_id' => '7']);
    }

    public function test_active_scope_excludes_removed_apps(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '10']);
        App::factory()->create(['shard' => 'geohub', 'app_id' => '11', 'removed_from_shard_at' => now()]);

        $this->assertSame(1, App::active()->whereIn('app_id', ['10', '11'])->count());
    }

    public function test_dead_code_is_gone_from_app_model(): void
    {
        foreach (['ugc_medias', 'ugc_pois', 'ugc_tracks', 'getGeojson', 'getMostViewedPoiGeojson', 'getUGCPoiGeojson', 'getUGCMediaGeojson', 'getiUGCTrackGeojson', 'getAppfillables'] as $method) {
            $this->assertFalse(method_exists(App::class, $method), "App::{$method}() dovrebbe essere rimosso");
        }
    }
}
