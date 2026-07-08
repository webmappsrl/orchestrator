<?php

namespace App\Services\Shards;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WmPackageShardDriver implements ShardDriver
{
    public function fetchApps(Shard $shard): ?array
    {
        if (empty($shard->token)) {
            Log::warning("Shard [{$shard->slug}]: SHARD_TOKEN mancante, sync saltata");

            return null;
        }

        $url = $shard->url . '/api/v1/export/apps';
        $apps = [];

        do {
            $response = Http::timeout(30)->acceptJson()->withToken($shard->token)->get($url);

            if (! $response->ok()) {
                return null;
            }

            $json = $response->json();
            if (! is_array($json) || ! array_key_exists('data', $json)) {
                return null;
            }

            foreach ($json['data'] as $element) {
                $normalized = $this->normalize($element);
                if ($normalized !== null) {
                    $apps[] = $normalized;
                }
            }

            $url = $json['links']['next'] ?? null;
        } while ($url);

        return $apps;
    }

    public function fetchApp(Shard $shard, string $remoteId): ?array
    {
        if (empty($shard->token)) {
            return null;
        }

        $response = Http::timeout(3)->acceptJson()->withToken($shard->token)
            ->get($shard->url . '/api/v1/export/apps/' . $remoteId);

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $this->normalize($data) : null;
    }

    /** Mapping contratto v1 → colonne apps di Orchestrator. */
    private function normalize(mixed $element): ?array
    {
        if (! is_array($element) || empty($element['id'])) {
            return null;
        }

        return [
            'app_id' => (string) $element['id'],
            'sku' => $element['sku'] ?? null,
            'name' => $element['name'] ?? null,
            'customer_name' => $element['customer_name'] ?? null,
            'user_email' => $element['author_email'] ?? null,
            'api' => $element['api'] ?? null,
            'ios_store_link' => $element['ios_store_link'] ?? null,
            'android_store_link' => $element['android_store_link'] ?? null,
            'default_language' => $element['default_language'] ?? null,
            'available_languages' => $this->jsonOrNull($element['available_languages'] ?? null),
            'welcome' => $this->jsonOrNull($element['welcome'] ?? null),
            'dashboard_show' => $element['dashboard_show'] ?? null,
        ];
    }

    private function jsonOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }
}
