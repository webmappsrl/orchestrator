<?php

namespace App\Services\Shards;

class Shard
{
    public function __construct(
        public readonly string $slug,
        public readonly string $url,
        public readonly string $driver,
        public readonly bool $enabled,
        public readonly ?string $token = null,
    ) {
    }
}
