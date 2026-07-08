<?php

namespace App\Services\Shards;

use InvalidArgumentException;

class ShardDriverFactory
{
    public function for(Shard $shard): ShardDriver
    {
        return match ($shard->driver) {
            'geohub' => app(GeohubShardDriver::class),
            'wmpackage' => app(WmPackageShardDriver::class),
            default => throw new InvalidArgumentException("Driver shard sconosciuto: {$shard->driver}"),
        };
    }
}
