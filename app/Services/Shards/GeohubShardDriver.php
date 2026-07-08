<?php

namespace App\Services\Shards;

use Illuminate\Support\Facades\Http;

class GeohubShardDriver implements ShardDriver
{
    /**
     * Whitelist esplicita payload geohub → colonne apps (mai pass-through:
     * campi remoti sconosciuti vengono ignorati, campi assenti restano NULL).
     * user_id remoto ESCLUSO by design: è FK locale orchestrator-owned.
     */
    private const FIELDS = [
        'name', 'customer_name', 'user_email', 'api', 'ios_store_link', 'android_store_link',
        'default_language', 'available_languages', 'welcome', 'page_project',
        'map_max_zoom', 'map_min_zoom', 'map_def_zoom', 'map_bbox',
        'primary_color', 'default_feature_color', 'font_family_header', 'font_family_content',
        'icon', 'splash', 'icon_small', 'feature_image', 'logo_homepage', 'icon_notify',
        'start_url', 'show_edit_link', 'poi_min_zoom', 'show_track_ref_label', 'enable_routing',
        'auth_show_at_startup', 'offline_enable', 'offline_force_auth', 'geolocation_record_enable',
        'tracks_on_payment', 'config_home', 'app_pois_api_layer', 'tiles',
        'start_end_icons_show', 'start_end_icons_min_zoom', 'ref_on_track_show', 'ref_on_track_min_zoom',
        'alert_poi_show', 'alert_poi_radius', 'social_track_text', 'draw_track_show',
        'iconmoon_selection', 'editing_inline_show', 'flow_line_quote_show', 'flow_line_quote_orange',
        'flow_line_quote_red', 'map_max_stroke_width', 'map_min_stroke_width',
        'download_track_enable', 'dashboard_show', 'print_track_enable', 'poi_interaction',
        'external_overlays',
    ];

    public function fetchApps(Shard $shard): ?array
    {
        return $this->fetch($shard, 30);
    }

    public function fetchApp(Shard $shard, string $remoteId): ?array
    {
        // Il geohub non espone lettura singola: full fetch (timeout corto) e filtro.
        $apps = $this->fetch($shard, 3);

        return collect($apps ?? [])->firstWhere('app_id', $remoteId);
    }

    private function fetch(Shard $shard, int $timeout): ?array
    {
        $response = Http::timeout($timeout)->acceptJson()->get($shard->url . '/api/v1/app/all');

        if (! $response->ok() || ! is_array($response->json())) {
            return null;
        }

        $apps = [];
        foreach ($response->json() as $element) {
            $normalized = $this->normalize($element);
            if ($normalized !== null) {
                $apps[] = $normalized;
            }
        }

        return $apps;
    }

    private function normalize(mixed $element): ?array
    {
        if (! is_array($element)) {
            return null;
        }

        $remoteId = $element['app_id'] ?? $element['id'] ?? null;
        if ($remoteId === null || $remoteId === '') {
            return null;
        }

        $attributes = ['app_id' => (string) $remoteId];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $element)) {
                $attributes[$field] = is_array($element[$field])
                    ? json_encode($element[$field])
                    : $element[$field];
            }
        }

        return $attributes;
    }
}
