<?php

namespace App\Services\Shards;

use Illuminate\Support\Collection;

class ShardRegistry
{
    /** @return Collection<int, Shard> */
    public function all(): Collection
    {
        return collect(config('shards', []))
            ->map(fn (array $cfg, string $slug) => new Shard(
                slug: $slug,
                url: rtrim($cfg['url'], '/'),
                driver: $cfg['driver'],
                enabled: (bool) ($cfg['enabled'] ?? true),
                token: $cfg['token'] ?? null,
            ))
            ->values();
    }

    /** @return Collection<int, Shard> */
    public function enabled(): Collection
    {
        return $this->all()->filter(fn (Shard $shard) => $shard->enabled)->values();
    }

    public function get(string $slug): ?Shard
    {
        return $this->all()->firstWhere('slug', $slug);
    }
}
