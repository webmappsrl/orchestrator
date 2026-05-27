<?php

return [
    'projects' => collect($_ENV ?? [])
        ->filter(fn($v, $k) => str_starts_with($k, 'HETZNER_TOKEN_'))
        ->mapWithKeys(fn($token, $key) => [
            strtolower(str_replace('HETZNER_TOKEN_', '', $key)) => $token,
        ])
        ->toArray(),

    'cache_ttl' => env('HETZNER_CACHE_TTL', 900),
];
