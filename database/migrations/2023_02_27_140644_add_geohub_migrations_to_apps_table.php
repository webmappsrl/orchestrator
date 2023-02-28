<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->string('app_id')->unique()->nullable();
            $table->string('customer_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('page_project', 99999)->nullable();


            // MAP section (zoom)
            $table->integer('map_max_zoom')->default(16);
            $table->integer('map_min_zoom')->default(12);
            $table->integer('map_def_zoom')->default(12);

            // THEME section
            $table->string('font_family_header')->default('Roboto Slab');
            $table->string('font_family_content')->default('Roboto');
            $table->string('default_feature_color')->default('#de1b0d');
            $table->string('primary_color')->default('#de1b0d');

            // OPTIONS section
            $table->string('start_url')->default('/main/explore');
            $table->boolean('show_edit_link')->default(false);
            $table->boolean('skip_route_index_download')->default(true);
            $table->float('poi_min_radius')->default(0.5);
            $table->float('poi_max_radius')->default(1.2);
            $table->float('poi_icon_zoom')->default(16);
            $table->float('poi_icon_radius')->default(1);
            $table->float('poi_min_zoom')->default(13);
            $table->float('poi_label_min_zoom')->default(10.5);
            $table->boolean('show_track_ref_label')->default(false);

            // TABLE section
            $table->boolean('table_details_show_gpx_download')->default(false);
            $table->boolean('table_details_show_kml_download')->default(false);
            $table->boolean('table_details_show_related_poi')->default(false);

            // ROUTING
            $table->boolean('enable_routing')->default(false);

            //EXTERNAL OVERLAYS
            $table->text('external_overlays')->nullable();

            //FIELDS CONFIG IMAGES
            $table->string('icon')->nullable();
            $table->string('splash')->nullable();
            $table->string('icon_small')->nullable();
            $table->string('feature_image')->nullable();

            //LANGUAGES SECTION
            $table->string('default_language', 2)->default('it');
            $table->json('available_languages')->nullable();

            //AUTH SHOW AT STARTUP
            $table->boolean('auth_show_at_startup')->default(false);

            //OFFLINE
            $table->boolean('offline_enable')->default(false);

            //OFFLINE FORCE AUTH
            $table->boolean('offline_force_auth')->default(false);


            //GEOLOCATION RECORD ENABLE
            $table->boolean('geolocation_record_enable')->default(false);

            //DETAILS TABLE FIELD
            $table->boolean('table_details_show_duration_forward')->default(true);
            $table->boolean('table_details_show_duration_backward')->default(false);
            $table->boolean('table_details_show_distance')->default(true);
            $table->boolean('table_details_show_ascent')->default(true);
            $table->boolean('table_details_show_descent')->default(true);
            $table->boolean('table_details_show_ele_max')->default(true);
            $table->boolean('table_details_show_ele_min')->default(true);
            $table->boolean('table_details_show_ele_from')->default(false);
            $table->boolean('table_details_show_ele_to')->default(false);
            $table->boolean('table_details_show_scale')->default(true);
            $table->boolean('table_details_show_cai_scale')->default(false);
            $table->boolean('table_details_show_mtb_scale')->default(false);
            $table->boolean('table_details_show_ref')->default(true);
            $table->boolean('table_details_show_surface')->default(false);
            $table->boolean('table_details_show_geojson_download')->default(false);
            $table->boolean('table_details_show_shapefile_download')->default(false);

            //API TYPE
            $table->string('api')->default('elbrus')->nullable();

            //ICON NOTIFY AND LOGO
            $table->string('icon_notify')->nullable();
            $table->string('logo_homepage')->nullable();

            //BBOX
            $table->string('map_bbox')->nullable();

            //TRACKS ON PAYMENT
            $table->boolean('tracks_on_payment')->default(false);

            //STORE LINKS
            $table->text('ios_store_link')->nullable();
            $table->text('android_store_link')->nullable();

            //CONFIG HOME
            $table->string('config_home', 99999)->nullable();

            //POIS LAYER
            $table->boolean('app_pois_api_layer')->default(false);

            //TILES TO APP
            $def = json_encode(array("{\"webmapp\":\"https://api.webmapp.it/tiles/{z}/{x}/{y}.png\"}"));
            $table->json('tiles')->default($def);

            //START END ICONS AND REF TRACKS
            $table->boolean('start_end_icons_show')->default(false);
            $table->integer('start_end_icons_min_zoom')->default(10);
            $table->boolean('ref_on_track_show')->default(false);
            $table->integer('ref_on_track_min_zoom')->default(10);

            //ALERT POI
            $table->boolean('alert_poi_show')->default(false);
            $table->integer('alert_poi_radius')->default(100);

            //SOCIAL TRACK TEXT
            $table->text('social_track_text')->nullable();

            //WEBAPP SECTION
            $table->boolean('draw_track_show')->default(false);

            //HOME WELCOME TITLE
            $table->text('welcome')->nullable();

            //ICONMOON SELECTION
            $table->text('iconmoon_selection')->nullable();

            //EDITING INLINE SHOW
            $table->boolean('editing_inline_show')->default(false);

            //FLOW LINE OPTION
            $table->boolean('flow_line_quote_show')->default(false);
            $table->integer('flow_line_quote_orange')->default(800);
            $table->integer('flow_line_quote_red')->default(1500);

            //MIN MAX STROKE WIDTH
            $table->integer('map_max_stroke_width')->default(6);
            $table->integer('map_min_stroke_width')->default(3);

            //DASHBOARD INFO
            $table->boolean('dashboard_show')->default(false);

            //DOWNLOAD TRACK ENABLE OPTION
            $table->boolean('download_track_enable')->default(true);

            //PRINT TRACK ENABLE OPTION
            $table->boolean('print_track_enable')->default(false);

            //POI TYPE OPEN SELECTOR
            $table->string('poi_interaction')->default('popup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn([
                'app_id', 'customer_name', 'user_email', 'user_id', 'max_zoom', 'min_zoom', 'def_zoom',
                'font_family_header', 'font_family_content', 'default_feature_color', 'primary', 'start_url', 'show_edit_link',
                'skip_route_index_download', 'poi_min_radius', 'poi_max_radius', 'poi_icon_zoom', 'poi_icon_radius', 'poi_min_zoom',
                'poi_label_min_zoom', 'show_track_ref_label', 'show_gpx_download', 'show_kml_download', 'show_related_poi', 'enable_routing',
                'external_overlays', 'icon', 'splash', 'icon_small', 'feature_image', 'default_language', 'available_languages',
                'auth_show_at_startup', 'offline_enable', 'offline_force_auth', 'geolocation_record_enable',
                'table_details_show_duration_forward', 'table_details_show_duration_backward', 'table_details_show_distance',
                'table_details_show_ascent', 'table_details_show_descent', 'table_details_show_ele_max', 'table_details_show_ele_min',
                'table_details_show_ele_from', 'table_details_show_ele_to', 'table_details_show_scale', 'table_details_show_cai_scale',
                'table_details_show_mtb_scale', 'table_details_show_ref', 'table_details_show_surface', 'table_details_show_geojson_download',
                'table_details_show_shapefile_download', 'api', 'icon_notify', 'logo_homepage', 'map_bbox', 'tracks_on_payment',
                'ios_store_link', 'android_store_link', 'config_home', 'app_pois_api_layer', 'tiles', 'start_end_icons_show',
                'start_end_icons_min_zoom', 'ref_on_track_show', 'ref_on_track_min_zoom', 'alert_poi_show', 'alert_poi_radius',
                'social_track_text'
            ]);
        });
    }
};
