<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\Tag;
use App\Models\User;
use App\Services\Shards\AppSyncService;
use App\Services\Shards\Shard;
use App\Services\Shards\ShardDriverFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppShardSyncTest extends TestCase
{
    use DatabaseTransactions;

    private function shard(string $slug = 'maphub'): Shard
    {
        return new Shard(slug: $slug, url: "https://{$slug}.test", driver: 'wmpackage', enabled: true, token: 'tok');
    }

    private function sync(): AppSyncService
    {
        return new AppSyncService(new ShardDriverFactory());
    }

    private function fakeShardApps(string $slug, array $apps): void
    {
        $this->fakeShardAppsSequence($slug, [$apps]);
    }

    /**
     * Registra UNA sola fake con una coda di payload: Http::fake() successivi
     * sulla stessa URL non sovrascrivono il primo stub (factory singleton),
     * quindi i test multi-sync devono accodare qui tutte le risposte.
     */
    private function fakeShardAppsSequence(string $slug, array $payloads): void
    {
        Http::fake(function ($request) use (&$payloads) {
            $apps = count($payloads) > 1 ? array_shift($payloads) : $payloads[0];

            return Http::response([
                'data' => $apps,
                'links' => ['next' => null],
            ]);
        });
    }

    public function test_sync_creates_apps_and_same_remote_id_coexists_across_shards(): void
    {
        App::factory()->create(['shard' => 'geohub', 'app_id' => '1', 'name' => 'Geohub app']);

        $this->fakeShardApps('maphub', [['id' => 1, 'name' => 'Maphub app']]);

        $result = $this->sync()->syncShard($this->shard());

        $this->assertSame(['synced' => 1, 'created' => 1, 'removed' => 0], $result);
        $this->assertSame(2, App::where('app_id', '1')->count());
    }

    public function test_second_sync_updates_shard_fields_but_preserves_local_crm(): void
    {
        $this->fakeShardAppsSequence('maphub', [
            [['id' => 5, 'name' => 'Prima', 'customer_name' => 'Remoto', 'author_email' => 'x@y.z']],
            [['id' => 5, 'name' => 'Dopo', 'customer_name' => 'Remoto cambiato', 'author_email' => 'nuovo@y.z']],
        ]);

        $this->sync()->syncShard($this->shard());

        // CRM curato a mano su Orchestrator
        $app = App::where('shard', 'maphub')->where('app_id', '5')->first();
        $localUser = User::factory()->create();
        $app->forceFill(['user_id' => $localUser->id, 'customer_name' => 'Cliente CRM'])->saveQuietly();

        $this->sync()->syncShard($this->shard());

        $app->refresh();
        $this->assertSame('Dopo', $app->name);              // shard-owned: aggiornato
        $this->assertSame('nuovo@y.z', $app->user_email);   // shard-owned: aggiornato
        $this->assertSame('Cliente CRM', $app->customer_name); // orchestrator-owned: intatto
        $this->assertSame($localUser->id, $app->user_id);      // orchestrator-owned: intatto
    }

    public function test_auto_link_populates_user_id_by_email_only_when_null(): void
    {
        $user = User::factory()->create(['email' => 'Owner@Example.ORG']);

        $this->fakeShardApps('maphub', [['id' => 9, 'name' => 'X', 'author_email' => 'owner@example.org']]);
        $this->sync()->syncShard($this->shard());

        $this->assertSame($user->id, App::where('shard', 'maphub')->where('app_id', '9')->first()->user_id);
    }

    public function test_invalid_or_empty_payload_is_a_noop(): void
    {
        App::factory()->create(['shard' => 'maphub', 'app_id' => '1']);

        $this->fakeShardApps('maphub', []);
        $this->assertNull($this->sync()->syncShard($this->shard()));

        $this->assertNull(App::where('shard', 'maphub')->first()->removed_from_shard_at);
    }

    public function test_reconciliation_guard_aborts_mass_removals(): void
    {
        foreach (range(1, 10) as $i) {
            App::factory()->create(['shard' => 'maphub', 'app_id' => (string) $i]);
        }

        // Solo 5 app su 10 nel payload → 5 rimozioni = 50% > 30%: abort
        $this->fakeShardApps('maphub', array_map(fn ($i) => ['id' => $i, 'name' => "App $i"], range(1, 5)));
        $result = $this->sync()->syncShard($this->shard());

        $this->assertSame(0, $result['removed']);
        $this->assertSame(0, App::where('shard', 'maphub')->whereNotNull('removed_from_shard_at')->count());
    }

    public function test_missing_app_is_stamped_and_reappearing_app_is_reactivated(): void
    {
        foreach (range(1, 10) as $i) {
            App::factory()->create(['shard' => 'maphub', 'app_id' => (string) $i]);
        }

        $this->fakeShardAppsSequence('maphub', [
            array_map(fn ($i) => ['id' => $i, 'name' => "App $i"], range(1, 9)),
            array_map(fn ($i) => ['id' => $i, 'name' => "App $i"], range(1, 10)),
        ]);

        // 9 app su 10: la mancante (10%) viene dismessa
        $result = $this->sync()->syncShard($this->shard());

        $this->assertSame(1, $result['removed']);
        $removed = App::where('shard', 'maphub')->where('app_id', '10')->first();
        $this->assertNotNull($removed->removed_from_shard_at);

        // L'app ricompare → riattivata
        $this->sync()->syncShard($this->shard());

        $this->assertNull($removed->refresh()->removed_from_shard_at);
    }

    public function test_sync_never_fires_eloquent_events(): void
    {
        $this->fakeShardApps('maphub', [['id' => 77, 'name' => 'Quiet']]);
        $this->sync()->syncShard($this->shard());

        $app = App::where('shard', 'maphub')->where('app_id', '77')->first();

        // Il hook created() del modello creerebbe un Tag: non deve esistere.
        $this->assertSame(0, Tag::where('taggable_type', App::class)->where('taggable_id', $app->id)->count());
    }
}
