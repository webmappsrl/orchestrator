<?php

namespace App\Jobs;

use App\Models\App;
use App\Services\Shards\AppSyncService;
use App\Services\Shards\ShardRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sync on-demand di una singola app dal suo shard (oc:8242).
 * Usato con dispatchSync() dall'apertura del detail Nova: il driver ha
 * timeout 3s e ogni errore è silenzioso (fallback alla copia locale).
 */
class SyncShardAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Finestra di throttle per app (secondi). */
    public const THROTTLE_SECONDS = 180;

    public function __construct(public readonly int $appId)
    {
    }

    public function handle(ShardRegistry $registry, AppSyncService $sync): void
    {
        $app = App::find($this->appId);

        if ($app === null || empty($app->shard) || empty($app->app_id)) {
            return;
        }

        $shard = $registry->get($app->shard);

        if ($shard === null || ! $shard->enabled) {
            return;
        }

        // Throttle per app: al massimo una fetch ogni THROTTLE_SECONDS.
        if (! Cache::add("shard_app_refresh_{$app->id}", 1, self::THROTTLE_SECONDS)) {
            return;
        }

        try {
            $sync->syncOne($shard, $app->app_id);
        } catch (\Throwable $e) {
            // Fallback silenzioso alla copia locale.
            Log::warning("Sync on-demand app {$app->id} [{$app->shard}] fallita: {$e->getMessage()}");
        }
    }
}
