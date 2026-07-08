<?php

namespace App\Services\Shards;

interface ShardDriver
{
    /**
     * Lista completa delle app dello shard, normalizzate come array
     * [colonna apps => valore] con 'app_id' sempre presente (stringa).
     * Ritorna null se la risposta è invalida (≠ lista vuota legittima:
     * anche quella viene trattata come no-op dal chiamante).
     */
    public function fetchApps(Shard $shard): ?array;

    /**
     * Singola app per id remoto (timeout corto: usata dal detail Nova).
     * Null se non trovata, non configurata o errore.
     */
    public function fetchApp(Shard $shard, string $remoteId): ?array;
}
