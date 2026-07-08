<?php

namespace Tests\Feature;

use App\Jobs\SyncShardAppJob;
use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncShardAppJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shards' => [
            'maphub' => ['url' => 'https://maphub.test', 'driver' => 'wmpackage', 'enabled' => true, 'token' => 'tok'],
        ]]);
        Cache::flush();
    }

    public function test_job_refreshes_the_app_from_its_shard(): void
    {
        $app = App::factory()->create(['shard' => 'maphub', 'app_id' => '5', 'name' => 'Stantia']);

        Http::fake([
            'https://maphub.test/api/v1/export/apps/5' => Http::response([
                'data' => ['id' => 5, 'name' => 'Fresca'],
            ]),
        ]);

        SyncShardAppJob::dispatchSync($app->id);

        $this->assertSame('Fresca', $app->refresh()->name);
    }

    public function test_job_is_throttled_per_app(): void
    {
        $app = App::factory()->create(['shard' => 'maphub', 'app_id' => '5', 'name' => 'Stantia']);

        Http::fake([
            'https://maphub.test/api/v1/export/apps/5' => Http::response(['data' => ['id' => 5, 'name' => 'Fresca']]),
        ]);

        SyncShardAppJob::dispatchSync($app->id);
        SyncShardAppJob::dispatchSync($app->id); // seconda apertura nel giro di 180s

        Http::assertSentCount(1);
    }

    public function test_job_falls_back_silently_when_the_shard_is_down(): void
    {
        $app = App::factory()->create(['shard' => 'maphub', 'app_id' => '5', 'name' => 'Locale']);

        Http::fake(['*' => Http::response(null, 500)]);

        SyncShardAppJob::dispatchSync($app->id); // nessuna eccezione

        $this->assertSame('Locale', $app->refresh()->name);
    }

    public function test_job_ignores_apps_of_unknown_or_disabled_shards(): void
    {
        $app = App::factory()->create(['shard' => 'sconosciuto', 'app_id' => '5']);

        Http::fake();
        SyncShardAppJob::dispatchSync($app->id);

        Http::assertNothingSent();
    }
}
