<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // SyncDeveloperCalendarJob keeps its unique lock on the redis cache
        // store (see uniqueVia), and the lock is acquired on dispatch even
        // when the Bus is faked: any test saving a Story with a status change
        // would open a Redis connection. CI runners have no Redis service —
        // point the redis store to the array driver for the whole suite.
        config(['cache.stores.redis.driver' => 'array']);
    }
}
