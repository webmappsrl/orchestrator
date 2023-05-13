<?php

namespace App\Traits;

use App\Models\App;
use App\Models\EcMedia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

trait ConfTrait
{
    /**
     * Display the specified resource.
     *
     * @param int $id the app id in the database
     *
     * @return JsonResponse
     */
    public function config()
    {

        $data = [];

        $data = array_merge($data, $this->config_section_app());
        $data = array_merge($data, $this->config_section_webapp());
        $data = array_merge($data, $this->config_section_home());
        $data = array_merge($data, $this->config_section_languages());
        $data = array_merge($data, $this->config_section_map());
        $data = array_merge($data, $this->config_section_project());
        $data = array_merge($data, $this->config_section_theme());
        //$data = array_merge($data, $this->config_section_options());
        $data = array_merge($data, $this->config_section_tables());
        $data = array_merge($data, $this->config_section_routing());
        $data = array_merge($data, $this->config_section_report());
        $data = array_merge($data, $this->config_section_geolocation());
        $data = array_merge($data, $this->config_section_auth());
        // $data = array_merge($data, $this->config_section_offline());

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_app(): array
    {
        $data = [];

        $data['APP']['name'] = $this->name;
        $data['APP']['id'] = $this->app_id;
        $data['APP']['customerName'] = $this->customer_name;
        $data['APP']['geohubId'] = $this->id;

        if (!is_null($this->welcome)) {
            $data['APP']['welcome'] = [];
            $welcome = $this->toArray()['welcome'];
            $data['APP']['welcome'] = $welcome;
        }
        if ($this->android_store_link)
            $data['APP']['androidStore'] = $this->android_store_link;

        if ($this->ios_store_link)
            $data['APP']['iosStore'] = $this->ios_store_link;

        if ($this->social_track_text)
            $data['APP']['socialTrackText'] = $this->social_track_text;
        if ($this->poi_acquisition_form)
            $data['APP']['poi_acquisition_form'] =  json_decode($this->poi_acquisition_form, TRUE);

        return $data;
    }
    /**
     * @param 
     *
     * @return array
     */
    private function config_section_webapp(): array
    {
        $data = [];

        $data['WEBAPP']['draw_track_show'] = $this->draw_track_show;
        $data['WEBAPP']['editing_inline_show'] = $this->editing_inline_show;

        return $data;
    }
    /**
     * @param 
     *
     * @return array
     */
    private function config_section_home(): array
    {
        $data = [];

        $data['HOME'][] = [
            'view' => 'title',
            'title' => $this->name
        ];

        if (!empty($this->config_home)) {
            $data = json_decode($this->config_home, TRUE);
        } else if ($this->layers->count() > 0) {
            foreach ($this->layers()->orderBy('rank')->get() as $layer) {
                $data['HOME'][] = [
                    'view' => 'compact-horizontal',
                    'title' => $layer->title,
                    'terms' => [$layer->id]
                ];
            }
        }

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_languages(): array
    {
        $data['LANGUAGES']['default'] = $this->default_language;
        if (isset($this->available_languages))
            $data['LANGUAGES']['available'] = json_decode($this->available_languages, true);
        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_map(): array
    {
        $data = [];
        // MAP section (zoom)
        $data['MAP']['defZoom'] = $this->map_def_zoom;
        $data['MAP']['maxZoom'] = $this->map_max_zoom;
        $data['MAP']['minZoom'] = $this->map_min_zoom;
        $data['MAP']['maxStrokeWidth'] = $this->map_max_stroke_width;
        $data['MAP']['minStrokeWidth'] = $this->map_min_stroke_width;
        $data['MAP']['tiles'] = array_map(function ($v) {
            return json_decode($v);
        }, json_decode($this->tiles, true));

        if (is_null($this->map_bbox)) {
            $data['MAP']['bbox'] = $this->_getBBox();
        } else {
            $data['MAP']['bbox'] = json_decode($this->map_bbox, true);
        }

        // MAP section (bbox)
        if (in_array($this->api, ['elbrus'])) {
            $data['MAP']['bbox'] = $this->_getBBox();
            // Map section layers
            $data['MAP']['layers'][0]['label'] = 'Mappa';
            $data['MAP']['layers'][0]['type'] = 'maptile';
            $data['MAP']['layers'][0]['tilesUrl'] = 'https://api.webmapp.it/tiles/';
            try {
                $data['MAP']['overlays'] = json_decode($this->external_overlays);
            } catch (\Exception $e) {
                Log::warning("The overlays in the app " . $this->$id . " are not correctly mapped. Error: " . $e->getMessage());
            }
        }

        if ($this->layers->count() > 0) {
            $layers = [];
            foreach ($this->layers as $layer) {
                $item = $layer->toArray();
                try {

                    if (isset($item['bbox'])) {
                        $item['bbox'] = array_map('floatval', json_decode(strval($item['bbox']), true));
                    }
                } catch (\Exception  $e) {
                    Log::warning("The bbox value " . $layer->id  . " are not correct. Error: " . $e->getMessage());
                }
                // style
                foreach (['color', 'fill_color', 'fill_opacity', 'stroke_width', 'stroke_opacity', 'zindex', 'line_dash'] as $field) {
                    $item['style'][$field] = $item[$field];
                    unset($item[$field]);
                }
                // behaviour
                foreach (['noDetails', 'noInteraction', 'minZoom', 'maxZoom', 'preventFilter', 'invertPolygons', 'alert', 'show_label'] as $field) {
                    $item['behaviour'][$field] = $item[$field];
                    unset($item[$field]);
                }
                unset($item['created_at']);
                unset($item['updated_at']);
                unset($item['app_id']);

                // FEATURE IMAGE:
                $feature_image = null;
                if ($layer->taxonomyWheres->count() > 0) {
                    foreach ($layer->taxonomyWheres as $term) {
                        if (isset($term->feature_image) && !empty($term->feature_image)) {
                            $feature_image = $term->feature_image;
                        }
                        if (isset($term->geometry)) {
                            unset($term->geometry);
                        }
                    }
                }
                if ($feature_image == null && $layer->taxonomyThemes->count() > 0) {
                    foreach ($layer->taxonomyThemes as $term) {
                        if (isset($term->feature_image) && !empty($term->feature_image)) {
                            $feature_image = $term->feature_image;
                        }
                    }
                }

                if ($feature_image == null && $layer->taxonomyActivities->count() > 0) {
                    foreach ($layer->taxonomyActivities as $term) {
                        if (isset($term->feature_image) && !empty($term->feature_image)) {
                            $feature_image = $term->feature_image;
                        }
                    }
                }

                if ($feature_image == null && $layer->taxonomyWhens->count() > 0) {
                    foreach ($layer->taxonomyWhens as $term) {
                        if (isset($term->feature_image) && !empty($term->feature_image)) {
                            $feature_image = $term->feature_image;
                        }
                    }
                }

                if ($feature_image == null && $layer->taxonomyTargets->count() > 0) {
                    foreach ($layer->taxonomyTargets as $term) {
                        if (isset($term->feature_image) && !empty($term->feature_image)) {
                            $feature_image = $term->feature_image;
                        }
                    }
                }

                if ($feature_image == null && $layer->taxonomyPoiTypes->count() > 0) {
                    foreach ($layer->taxonomyPoiTypes as $term) {
                        if (isset($term->feature_image) && !empty($term->feature_image)) {
                            $feature_image = $term->feature_image;
                        }
                    }
                }



                if ($feature_image != null) {
                    // Retrieve proper image
                    $image = EcMedia::find($feature_image);
                    if (!is_null($image->thumbnail('400x200'))) {
                        $item['feature_image'] = $image->thumbnail('400x200');
                    }
                }

                $layers[] = $item;
            }

            $rank = array_column($layers, 'rank');
            array_multisort($rank, SORT_ASC, $layers);
            $data['MAP']['layers'] = $layers;
        }

        // POIS section
        $data['MAP']['pois']['apppoisApiLayer'] = $this->app_pois_api_layer;
        $data['MAP']['pois']['poiMinZoom'] = $this->poi_min_zoom;
        // $data['MAP']['pois']['taxonomies'] = $this->getAllPoiTaxonomies();
        $data['MAP']['pois']['poi_interaction'] = $this->poi_interaction;

        // Other Options
        $data['MAP']['start_end_icons_show'] = $this->start_end_icons_show;
        $data['MAP']['start_end_icons_min_zoom'] = $this->start_end_icons_min_zoom;
        $data['MAP']['ref_on_track_show'] = $this->ref_on_track_show;
        $data['MAP']['ref_on_track_min_zoom'] = $this->ref_on_track_min_zoom;
        $data['MAP']['record_track_show'] = $this->geolocation_record_enable;
        $data['MAP']['alert_poi_show'] = $this->alert_poi_show;
        $data['MAP']['alert_poi_radius'] = $this->alert_poi_radius;
        $data['MAP']['flow_line_quote_show'] = $this->flow_line_quote_show;
        $data['MAP']['flow_line_quote_orange'] = $this->flow_line_quote_orange;
        $data['MAP']['flow_line_quote_red'] = $this->flow_line_quote_red;

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_theme(): array
    {
        $data = [];
        // THEME section

        $data['THEME']['fontFamilyHeader'] = $this->font_family_header;
        $data['THEME']['fontFamilyContent'] = $this->font_family_content;
        $data['THEME']['defaultFeatureColor'] = $this->default_feature_color;
        $data['THEME']['primary'] = $this->primary_color;

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_project(): array
    {
        $data = [];
        // PROJECT section

        $data['PROJECT']['HTML'] = $this->page_project;


        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_options(): array
    {
        $data = [];
        if (in_array($this->api, ['elbrus'])) {
            // OPTIONS section
            $data['OPTIONS']['baseUrl'] = 'https://geohub.webmapp.it/api/app/elbrus/' . $this->id . '/';
        }

        $data['OPTIONS']['startUrl'] = $this->start_url;
        $data['OPTIONS']['showEditLink'] = $this->show_edit_link;
        $data['OPTIONS']['skipRouteIndexDownload'] = $this->skip_route_index_download;
        $data['OPTIONS']['showTrackRefLabel'] = $this->show_track_ref_label;
        $data['OPTIONS']['download_track_enable'] = $this->download_track_enable;
        $data['OPTIONS']['print_track_enable'] = $this->print_track_enable;


        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_tables(): array
    {
        $data = [];
        if (in_array($this->api, ['elbrus'])) {
            // TABLES section
            $data['TABLES']['details']['showGpxDownload'] = !!$this->table_details_show_gpx_download;
            $data['TABLES']['details']['showKmlDownload'] = !!$this->table_details_show_kml_download;
            $data['TABLES']['details']['showRelatedPoi'] = !!$this->table_details_show_related_poi;
            $data['TABLES']['details']['hide_duration:forward'] = !$this->table_details_show_duration_forward;
            $data['TABLES']['details']['hide_duration:backward'] = !$this->table_details_show_duration_backward;
            $data['TABLES']['details']['hide_distance'] = !$this->table_details_show_distance;
            $data['TABLES']['details']['hide_ascent'] = !$this->table_details_show_ascent;
            $data['TABLES']['details']['hide_descent'] = !$this->table_details_show_descent;
            $data['TABLES']['details']['hide_ele:max'] = !$this->table_details_show_ele_max;
            $data['TABLES']['details']['hide_ele:min'] = !$this->table_details_show_ele_min;
            $data['TABLES']['details']['hide_ele:from'] = !$this->table_details_show_ele_from;
            $data['TABLES']['details']['hide_ele:to'] = !$this->table_details_show_ele_to;
            $data['TABLES']['details']['hide_scale'] = !$this->table_details_show_scale;
            $data['TABLES']['details']['hide_cai_scale'] = !$this->table_details_show_cai_scale;
            $data['TABLES']['details']['hide_mtb_scale'] = !$this->table_details_show_mtb_scale;
            $data['TABLES']['details']['hide_ref'] = !$this->table_details_show_ref;
            $data['TABLES']['details']['hide_surface'] = !$this->table_details_show_surface;
            $data['TABLES']['details']['showGeojsonDownload'] = !!$this->table_details_show_geojson_download;
            $data['TABLES']['details']['showShapefileDownload'] = !!$this->table_details_show_shapefile_download;
        }

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_routing(): array
    {
        $data = [];
        if (in_array($this->api, ['elbrus'])) {
            // ROUTING section
            $data['ROUTING']['enable'] = $this->enable_routing;
        }

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_report(): array
    {
        $data = [];
        if (in_array($this->api, ['elbrus'])) {
            // REPORT SECION
            $data['REPORTS'] = $this->_getReportSection();
        }

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_geolocation(): array
    {
        $data = [];
        if (in_array($this->api, ['elbrus'])) {
            // GEOLOCATION SECTION
            $data['GEOLOCATION']['record']['enable'] = !!$this->geolocation_record_enable;
            $data['GEOLOCATION']['record']['export'] = true;
            $data['GEOLOCATION']['record']['uploadUrl'] = 'https://geohub.webmapp.it/api/usergenerateddata/store';
        } else {
            if (!!$this->geolocation_record_enable)
                $data['GEOLOCATION']['record']['enable'] = !!$this->geolocation_record_enable;
        }

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_auth(): array
    {
        $data = [];
        if (in_array($this->api, ['elbrus'])) {
            // AUTH section
            $data['AUTH']['showAtStartup'] = false;
            if ($this->auth_show_at_startup) {
                $data['AUTH']['showAtStartup'] = true;
            }
            $data['AUTH']['enable'] = true;
            $data['AUTH']['loginToGeohub'] = true;
        } else {
            if ($this->auth_show_at_startup) {
                $data['AUTH']['enable'] = true;
                $data['AUTH']['showAtStartup'] = true;
            } else {
                $data['AUTH']['enable'] = false;
            }
        }

        return $data;
    }

    /**
     * @param 
     *
     * @return array
     */
    private function config_section_offline(): array
    {
        $data = [];
        // OFFLINE section
        $data['OFFLINE']['enable'] = false;
        if ($this->offline_enable) {
            $data['OFFLINE']['enable'] = true;
        }
        $data['OFFLINE']['forceAuth'] = false;
        if ($this->offline_force_auth) {
            $data['OFFLINE']['forceAuth'] = true;
        }
        $data['OFFLINE']['tracksOnPayment'] = false;
        if ($this->tracks_on_payment) {
            $data['OFFLINE']['tracksOnPayment'] = true;
        }

        return $data;
    }

    /**
     * Returns bbox array
     * [lon0,lat0,lon1,lat1]
     *
     * @param App $app
     *
     * @return array
     */
    private function _getBBox(): array
    {
        $bbox = [];
        $q = "select ST_Extent(geometry::geometry) as bbox from ec_tracks where user_id=$this->user_id;";
        //$q = "select name,ST_AsGeojson(geometry) as bbox from ec_tracks where user_id=$app->user_id;";
        $res = DB::select($q);
        if (count($res) > 0) {
            if (!is_null($res[0]->bbox)) {
                preg_match('/\((.*?)\)/', $res[0]->bbox, $match);
                $coords = $match[1];
                $coord_array = explode(',', $coords);
                $coord_min_str = $coord_array[0];
                $coord_max_str = $coord_array[1];
                $coord_min = explode(' ', $coord_min_str);
                $coord_max = explode(' ', $coord_max_str);
                $bbox = [$coord_min[0], $coord_min[1], $coord_max[0], $coord_max[1]];
            }
        }

        return $bbox;
    }


    private function _getReportSection()
    {
        $json_string = <<<EOT
 {
    "enable": true,
    "url": "https://geohub.webmapp.it/api/usergenerateddata/store",
    "items": [
    {
    "title": "Crea un nuovo waypoint",
    "success": "Waypoint creato con successo",
    "url": "https://geohub.webmapp.it/api/usergenerateddata/store",
    "type": "geohub",
    "fields": [
    {
    "label": "Nome",
    "name": "title",
    "mandatory": true,
    "type": "text",
    "placeholder": "Scrivi qua il nome del waypoint"
    },
    {
    "label": "Descrizione",
    "name": "description",
    "mandatory": true,
    "type": "textarea",
    "placeholder": "Descrivi brevemente il waypoint"
    },
    {
    "label": "Foto",
    "name": "gallery",
    "mandatory": false,
    "type": "gallery",
    "limit": 5,
    "placeholder": "Aggiungi qualche foto descrittiva del waypoint"
    }
    ]
    }
    ]
    }
EOT;

        return json_decode($json_string, true);
    }
}
