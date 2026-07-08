<?php

namespace Tests\Feature;

use App\Services\Shards\Shard;
use App\Services\Shards\ShardRegistry;
use Tests\TestCase;

class ShardRegistryTest extends TestCase
{
    public function test_registry_reads_shards_from_config(): void
    {
        config(['shards' => [
            'alpha' => ['url' => 'https://alpha.test/', 'driver' => 'wmpackage', 'enabled' => true, 'token' => 'tok-a'],
            'beta' => ['url' => 'https://beta.test', 'driver' => 'geohub', 'enabled' => false, 'token' => null],
        ]]);

        $registry = new ShardRegistry();

        $this->assertCount(2, $registry->all());
        $this->assertCount(1, $registry->enabled());

        $alpha = $registry->get('alpha');
        $this->assertInstanceOf(Shard::class, $alpha);
        $this->assertSame('https://alpha.test', $alpha->url); // trailing slash rimosso
        $this->assertSame('tok-a', $alpha->token);
        $this->assertNull($registry->get('missing'));
    }

    public function test_default_config_contains_the_four_seed_shards(): void
    {
        $slugs = array_keys(require base_path('config/shards.php'));

        $this->assertSame(['geohub', 'maphub', 'camminiditalia', 'osm2cai'], $slugs);
    }
}
