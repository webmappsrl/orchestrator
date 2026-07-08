<?php

namespace Tests\Feature;

use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncShardAppsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shards' => [
            'alpha' => ['url' => 'https://alpha.test', 'driver' => 'wmpackage', 'enabled' => true, 'token' => 'tok'],
            'beta' => ['url' => 'https://beta.test', 'driver' => 'wmpackage', 'enabled' => true, 'token' => 'tok'],
            'spento' => ['url' => 'https://spento.test', 'driver' => 'wmpackage', 'enabled' => false, 'token' => 'tok'],
        ]]);
    }

    public function test_command_syncs_all_enabled_shards_and_isolates_failures(): void
    {
        Http::fake([
            'https://alpha.test/api/v1/export/apps' => Http::response([
                'data' => [['id' => 1, 'name' => 'Alpha One']],
                'links' => ['next' => null],
            ]),
            'https://beta.test/api/v1/export/apps' => Http::response(null, 500), // beta giù
        ]);

        $this->artisan('apps:sync')->assertSuccessful();

        // alpha sincronizzata nonostante beta sia giù; spento mai chiamato
        $this->assertSame(1, App::where('shard', 'alpha')->count());
        $this->assertSame(0, App::where('shard', 'beta')->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'spento.test'));
    }

    public function test_command_accepts_a_single_shard_option(): void
    {
        Http::fake([
            'https://alpha.test/api/v1/export/apps' => Http::response([
                'data' => [['id' => 1, 'name' => 'Alpha One']],
                'links' => ['next' => null],
            ]),
        ]);

        $this->artisan('apps:sync', ['--shard' => 'alpha'])->assertSuccessful();

        $this->assertSame(1, App::where('shard', 'alpha')->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'beta.test'));
    }

    public function test_the_old_destructive_import_is_gone(): void
    {
        $this->assertFalse(class_exists(\App\Console\Commands\OrchestratorImport::class), 'OrchestratorImport (App::truncate) deve essere rimosso');
    }
}
