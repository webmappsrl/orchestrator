<?php

return [
    'projects' => collect($_ENV ?? [])
        ->filter(fn($v, $k) => str_starts_with($k, 'HETZNER_TOKEN_'))
        ->mapWithKeys(fn($token, $key) => [
            strtolower(str_replace('HETZNER_TOKEN_', '', $key)) => $token,
        ])
        ->toArray(),

    'cache_ttl' => env('HETZNER_CACHE_TTL', 900),

    // Link al registro del censimento server (oc:8877), mostrato in testa alla dashboard
    'censimento_url' => env('HETZNER_CENSIMENTO_URL'),
];
