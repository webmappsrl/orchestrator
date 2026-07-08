<?php

namespace App\Services\Shards;

use App\Models\App;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AppSyncService
{
    /**
     * Colonne CRM orchestrator-owned: seminate alla creazione,
     * MAI aggiornate dalla sync sui record esistenti.
     */
    private const SEED_ONLY = ['customer_name'];

    /** Guardia riconciliazione: max frazione di app attive dismissibili in un giro. */
    private const MAX_REMOVAL_RATIO = 0.30;

    public function __construct(private readonly ShardDriverFactory $drivers)
    {
    }

    /**
     * Full sync di uno shard: upsert + riconciliazione dismesse.
     *
     * @return array{synced: int, created: int, removed: int}|null
     *         null = no-op (payload invalido o vuoto: non si tocca nulla)
     */
    public function syncShard(Shard $shard): ?array
    {
        $apps = $this->drivers->for($shard)->fetchApps($shard);

        if ($apps === null || $apps === []) {
            Log::warning("apps:sync [{$shard->slug}] payload invalido o vuoto — nessuna azione");

            return null;
        }

        $created = 0;
        $seenRemoteIds = [];

        foreach ($apps as $attributes) {
            $seenRemoteIds[] = $attributes['app_id'];
            if ($this->upsert($shard, $attributes)) {
                $created++;
            }
        }

        $removed = $this->reconcile($shard, $seenRemoteIds);

        return ['synced' => count($apps), 'created' => $created, 'removed' => $removed];
    }

    /** Sync on-demand di una singola app (detail Nova). */
    public function syncOne(Shard $shard, string $remoteId): bool
    {
        $attributes = $this->drivers->for($shard)->fetchApp($shard, $remoteId);

        if ($attributes === null) {
            return false;
        }

        $this->upsert($shard, $attributes);

        return true;
    }

    /** @return bool true se l'app è stata creata */
    private function upsert(Shard $shard, array $attributes): bool
    {
        $app = App::where('shard', $shard->slug)
            ->where('app_id', $attributes['app_id'])
            ->first();

        $wasCreated = $app === null;

        if ($wasCreated) {
            $app = new App();
            $app->shard = $shard->slug;
        } else {
            foreach (self::SEED_ONLY as $column) {
                unset($attributes[$column]);
            }
        }

        // I null del payload non si scrivono mai: al create lasciano agire i
        // default NOT NULL del DB, all'update non azzerano valori esistenti.
        $app->fill(array_filter($attributes, fn ($value) => $value !== null));

        // Sync-owned: presente nel payload dello shard → attiva (riattivazione inclusa).
        $app->removed_from_shard_at = null;

        $this->autoLinkUser($app);

        // Mai eventi Eloquent dalla sync: niente observer, tag automatici o BuildConfJson.
        $app->saveQuietly();

        return $wasCreated;
    }

    private function autoLinkUser(App $app): void
    {
        if ($app->user_id !== null || empty($app->user_email)) {
            return;
        }

        $user = User::whereRaw('lower(email) = ?', [mb_strtolower($app->user_email)])->first();

        if ($user !== null) {
            $app->user_id = $user->id;
        }
    }

    /** @return int numero di app dismesse */
    private function reconcile(Shard $shard, array $seenRemoteIds): int
    {
        $missing = App::where('shard', $shard->slug)
            ->active()
            ->whereNotIn('app_id', $seenRemoteIds)
            ->get();

        if ($missing->isEmpty()) {
            return 0;
        }

        $activeCount = App::where('shard', $shard->slug)->active()->count();

        if ($missing->count() / max($activeCount, 1) > self::MAX_REMOVAL_RATIO) {
            Log::error("apps:sync [{$shard->slug}] guardia riconciliazione: {$missing->count()}/{$activeCount} rimozioni in un giro — abort");

            return 0;
        }

        foreach ($missing as $app) {
            $app->removed_from_shard_at = now();
            $app->saveQuietly();
        }

        return $missing->count();
    }
}
