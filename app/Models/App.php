<?php

namespace App\Models;

use App\Models\Layer;
use App\Observers\AppObserver;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Arr;
use App\Traits\ConfTrait;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class App extends Model
{
    use HasFactory, ConfTrait, HasTranslations;




    //translatable fields
    public $translatable = ['welcome'];


    public $fillable = [
        "id", "created_at", "updated_at", "name", "app_id", "customer_name", "map_max_zoom",
        "map_min_zoom", "map_def_zoom", "font_family_header", "font_family_content", "default_feature_color",
        "primary_color", "start_url", "show_edit_link", "poi_min_zoom", "show_track_ref_label",
        "table_details_show_gpx_download", "table_details_show_kml_download",  "table_details_show_related_poi", "enable_routing",
        "user_id", "external_overlays", "icon", "splash", "icon_small", "feature_image", "default_language", "available_languages",
        "auth_show_at_startup", "offline_enable", "offline_force_auth", "geolocation_record_enable", "table_details_show_duration_forward",
        "table_details_show_duration_backward", "table_details_show_distance", "table_details_show_ascent", "table_details_show_descent",
        "table_details_show_ele_max", "table_details_show_ele_min", "table_details_show_ele_from", "table_details_show_ele_to",
        "table_details_show_scale", "table_details_show_cai_scale", "table_details_show_mtb_scale", "table_details_show_ref",
        "table_details_show_surface", "table_details_show_geojson_download", "table_details_show_shapefile_download", "api",
        "icon_notify", "logo_homepage", "map_bbox", "tracks_on_payment", "ios_store_link", "android_store_link",
        "config_home", "app_pois_api_layer", "page_project", "tiles", "start_end_icons_show", "start_end_icons_min_zoom",
        "ref_on_track_show", "ref_on_track_min_zoom", "alert_poi_show", "alert_poi_radius", "social_track_text",
        "draw_track_show", "welcome", "iconmoon_selection", "editing_inline_show", "flow_line_quote_show", "flow_line_quote_orange",  "flow_line_quote_red", "map_max_stroke_width", "map_min_stroke_width", "download_track_enable", "dashboard_show",
        "print_track_enable", "poi_interaction", "user_email", "page_project",
        "shard", "removed_from_shard_at", "sku"
    ];

    protected $guarded = [];

    protected $casts = [
        'removed_from_shard_at' => 'datetime',
    ];

    /**
     * Scope: app presenti sul proprio shard (non dismesse).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('removed_from_shard_at');
    }

    /**
     * L'app risulta pubblicata sugli store? (store link presente o app_id
     * in formato bundle). Condizione per il report PDF (oc:8242).
     */
    public function hasStorePresence(): bool
    {
        return ! empty($this->ios_store_link)
            || ! empty($this->android_store_link)
            || ($this->app_id && str_contains($this->app_id, '.'));
    }

    /**
     * Bundle/package per lo store lookup del report (oc:8242).
     * L'app_id delle app sincronizzate è l'id numerico dello shard, non un
     * bundle. Ordine: package dal link Play Store → app_id solo se è un
     * bundle vero → null (lo script farà fuzzy match sul nome).
     */
    public function storeBundleId(): ?string
    {
        if ($this->android_store_link && preg_match('/[?&]id=([\w.]+)/', $this->android_store_link, $matches)) {
            return $matches[1];
        }

        if ($this->app_id && str_contains($this->app_id, '.')) {
            return $this->app_id;
        }

        return null;
    }

    /**
     * Path del PDF report del mese corrente, shard-qualificato: due app
     * omonime su shard diversi non devono condividere lo stesso file (oc:8242).
     */
    public function reportPdfPath(): string
    {
        $safeName = preg_replace('/[^\w\-]/u', '_', $this->name);
        $month = now()->format('Y-m');
        $dir = storage_path('app/reports');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return "{$dir}/webmapp_report_app_{$this->shard}_{$safeName}_{$month}.pdf";
    }




    /**
     * relationship with layers
     * @return hasMany
     */
    public function layers()
    {
        return $this->hasMany(Layer::class);
    }

    /**
     * Get all the tags for the project.
     */
    public function tags()
    {
        return $this->morphMany(Tag::class, 'taggable');
    }
    function BuildConfJson()
    {
        $confUri = $this->id . ".json";
        $json = $this->config();
        $jidoTime = $this->config_get_jido_time();
        if (!is_null($jidoTime)) {
            $json['JIDO_UPDATE_TIME'] = $jidoTime;
        }
        Storage::disk('conf')->put($confUri, json_encode($json));
        return $json;
    }

    public function GenerateAppConfig()
    {
        $this->BuildConfJson();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_app', 'app_id', 'user_id');
    }

    public function config_update_jido_time()
    {
        $confUri = $this->id . ".json";
        if (Storage::disk('conf')->exists($confUri)) {
            $json = json_decode(Storage::disk('conf')->get($confUri));
            $json->JIDO_UPDATE_TIME = floor(microtime(true) * 1000);
            Storage::disk('conf')->put($confUri, json_encode($json));
        }
    }

    public function config_get_jido_time()
    {
        $confUri = $this->id . ".json";
        if (Storage::disk('conf')->exists($confUri)) {
            $json = json_decode(Storage::disk('conf')->get($confUri));
            if (isset($json->JIDO_UPDATE_TIME)) {
                return $json->JIDO_UPDATE_TIME;
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * @param string $url
     * @param string $type
     * @param string $posts
     */
    private function _curlExec(string $url, string $type, string $posts): void
    {
        Log::info("CURL EXEC TYPE:$type URL:$url");

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $type,
            CURLOPT_POSTFIELDS => $posts,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic ' . config('services.elastic.key')
            ),
        ));
        if (str_contains(env('ELASTIC_HOST'), 'localhost')) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }
        $response = curl_exec($curl);
        if ($response === false) {
            throw new Exception(curl_error($curl), curl_errno($curl));
        }
        curl_close($curl);
    }



    protected static function boot()
    {
        parent::boot();
        static::created(function (App $entity) {
            try {
                $tag = Tag::firstOrCreate([
                    'name' => $entity->name,
                    'taggable_id' => $entity->id,
                    'taggable_type' => get_class($entity)
                ]);
                if ($tag && $entity) {
                    $entity->tags()->saveQuietly($tag);
                }
            } catch (Exception $e) {
                // Logga l'errore con maggiori dettagli
                Log::error('Error saving tags: ' . $e->getMessage(), [
                    'entity' => $entity,
                    'tag' => isset($tag) ? $tag : null,
                ]);
            }
        });
        App::observe(AppObserver::class);
    }
}
