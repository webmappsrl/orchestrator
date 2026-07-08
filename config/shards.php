<?php

/*
 * Registry degli shard sincronizzati da Orchestrator (oc:8242).
 *
 * ⚠️ Lo slug (chiave dell'array) è IMMUTABILE: entra nell'identità
 * composita (shard, app_id) delle app. Rinominarlo orfanizza tutte le
 * app dello shard (verrebbero dismesse e re-importate da zero, perdendo
 * il CRM locale). Aggiungere nuovi shard è sempre sicuro.
 *
 * 'enabled' => false è il kill switch operativo: ferma la sync dello
 * shard senza perdita dati (rollback senza toccare migration).
 */
return [
    'geohub' => [
        'url' => env('SHARD_URL_GEOHUB', 'https://geohub.webmapp.it'),
        'driver' => 'geohub',
        'enabled' => (bool) env('SHARD_ENABLED_GEOHUB', true),
        'token' => null, // endpoint legacy pubblico
    ],
    'maphub' => [
        'url' => env('SHARD_URL_MAPHUB', 'https://maphub.it'),
        'driver' => 'wmpackage',
        'enabled' => (bool) env('SHARD_ENABLED_MAPHUB', true),
        'token' => env('SHARD_TOKEN_MAPHUB'),
    ],
    'camminiditalia' => [
        'url' => env('SHARD_URL_CAMMINIDITALIA', 'https://camminiditalia.maphub.it'),
        'driver' => 'wmpackage',
        'enabled' => (bool) env('SHARD_ENABLED_CAMMINIDITALIA', true),
        'token' => env('SHARD_TOKEN_CAMMINIDITALIA'),
    ],
    'osm2cai' => [
        'url' => env('SHARD_URL_OSM2CAI', 'https://osm2cai.cai.it'),
        'driver' => 'wmpackage',
        'enabled' => (bool) env('SHARD_ENABLED_OSM2CAI', true),
        'token' => env('SHARD_TOKEN_OSM2CAI'),
    ],
];
