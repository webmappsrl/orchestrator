<?php

namespace App\Console\Commands;

use App\Services\Shards\AppSyncService;
use App\Services\Shards\ShardRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncShardApps extends Command
{
    protected $signature = 'apps:sync {--shard= : Slug del singolo shard da sincronizzare}';

    protected $description = 'Sincronizza le app da tutti gli shard abilitati (config/shards.php)';

    public function handle(ShardRegistry $registry, AppSyncService $sync): int
    {
        $shards = $this->option('shard')
            ? collect([$registry->get($this->option('shard'))])->filter()
            : $registry->enabled();

        if ($shards->isEmpty()) {
            $this->error('Nessuno shard da sincronizzare (slug inesistente o tutti disabilitati).');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($shards as $shard) {
            // Lock per shard: evita sovrapposizioni tra giro schedulato e run manuali.
            $lock = Cache::lock("apps_sync_shard_{$shard->slug}", 600);

            if (! $lock->get()) {
                $this->warn("[{$shard->slug}] sync già in corso, salto.");

                continue;
            }

            try {
                $result = $sync->syncShard($shard);

                if ($result === null) {
                    $this->warn("[{$shard->slug}] payload invalido o vuoto — nessuna azione.");
                    $failures++;
                } else {
                    $this->info("[{$shard->slug}] {$result['synced']} sincronizzate, {$result['created']} nuove, {$result['removed']} dismesse.");
                }
            } catch (\Throwable $e) {
                // Errori isolati per shard: gli altri proseguono.
                Log::error("apps:sync [{$shard->slug}] fallita", ['exception' => $e]);
                $this->error("[{$shard->slug}] errore: {$e->getMessage()}");
                $failures++;
            } finally {
                $lock->release();
            }
        }

        return $failures === $shards->count() ? self::FAILURE : self::SUCCESS;
    }
}
