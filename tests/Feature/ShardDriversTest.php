<?php

namespace Tests\Feature;

use App\Services\Shards\GeohubShardDriver;
use App\Services\Shards\Shard;
use App\Services\Shards\WmPackageShardDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShardDriversTest extends TestCase
{
    private function wmShard(): Shard
    {
        return new Shard(slug: 'maphub', url: 'https://maphub.test', driver: 'wmpackage', enabled: true, token: 'tok');
    }

    private function geohubShard(): Shard
    {
        return new Shard(slug: 'geohub', url: 'https://geohub.test', driver: 'geohub', enabled: true);
    }

    public function test_wmpackage_driver_normalizes_the_v1_contract(): void
    {
        Http::fake([
            'https://maphub.test/api/v1/export/apps' => Http::response([
                'data' => [[
                    'id' => 12, 'sku' => 'it.webmapp.demo', 'name' => 'Demo',
                    'customer_name' => 'ACME', 'api' => 'elbrus',
                    'ios_store_link' => null, 'android_store_link' => null,
                    'default_language' => 'it', 'available_languages' => ['it', 'en'],
                    'welcome' => ['it' => 'Ciao'], 'dashboard_show' => true,
                    'author_name' => 'Owner', 'author_email' => 'owner@example.org',
                    'created_at' => '2026-01-01T00:00:00+00:00', 'updated_at' => '2026-06-01T00:00:00+00:00',
                ]],
                'links' => ['next' => null],
                'meta' => [],
            ]),
        ]);

        $apps = (new WmPackageShardDriver())->fetchApps($this->wmShard());

        $this->assertCount(1, $apps);
        $this->assertSame('12', $apps[0]['app_id']);
        $this->assertSame('it.webmapp.demo', $apps[0]['sku']);
        $this->assertSame('owner@example.org', $apps[0]['user_email']);
        $this->assertJson($apps[0]['available_languages']);
    }

    public function test_wmpackage_driver_follows_pagination(): void
    {
        Http::fake([
            'https://maphub.test/api/v1/export/apps?page=2' => Http::response([
                'data' => [['id' => 2, 'name' => 'B']],
                'links' => ['next' => null],
            ]),
            'https://maphub.test/api/v1/export/apps' => Http::response([
                'data' => [['id' => 1, 'name' => 'A']],
                'links' => ['next' => 'https://maphub.test/api/v1/export/apps?page=2'],
            ]),
        ]);

        $apps = (new WmPackageShardDriver())->fetchApps($this->wmShard());

        $this->assertSame(['1', '2'], array_column($apps, 'app_id'));
    }

    public function test_wmpackage_driver_returns_null_on_error_or_missing_token(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        $this->assertNull((new WmPackageShardDriver())->fetchApps($this->wmShard()));

        $noToken = new Shard(slug: 'maphub', url: 'https://maphub.test', driver: 'wmpackage', enabled: true, token: null);
        $this->assertNull((new WmPackageShardDriver())->fetchApps($noToken));
    }

    public function test_geohub_driver_maps_the_legacy_payload(): void
    {
        Http::fake([
            'https://geohub.test/api/v1/app/all' => Http::response([
                ['id' => 50, 'app_id' => null, 'name' => 'Parco', 'user_id' => 21697,
                    'user_email' => 'parco@webmapp.it', 'customer_name' => 'Parco',
                    'available_languages' => ['it'], 'campo_ignoto_futuro' => 'x'],
            ]),
        ]);

        $apps = (new GeohubShardDriver())->fetchApps($this->geohubShard());

        $this->assertCount(1, $apps);
        $this->assertSame('50', $apps[0]['app_id']); // fallback su id remoto
        $this->assertSame('parco@webmapp.it', $apps[0]['user_email']);
        $this->assertArrayNotHasKey('user_id', $apps[0]); // mai la FK remota
        $this->assertArrayNotHasKey('campo_ignoto_futuro', $apps[0]); // whitelist, non pass-through
    }

    public function test_geohub_driver_returns_null_on_invalid_payload(): void
    {
        Http::fake(['https://geohub.test/api/v1/app/all' => Http::response('not json array', 200)]);

        $this->assertNull((new GeohubShardDriver())->fetchApps($this->geohubShard()));
    }
}
