<?php

namespace App\Nova;

use App\Enums\UserRole;
use Eminiarts\Tabs\Tabs;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Color;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Textarea;
use Davidpiesse\NovaToggle\Toggle;
use Outl1ne\MultiselectField\Multiselect;
use Laravel\Nova\Http\Requests\NovaRequest;
use Kraftbit\NovaTinymce5Editor\NovaTinymce5Editor;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;



class App extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\App>
     */
    public static $model = \App\Models\App::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'app_id'
    ];

    private $languages  = [
        'en' => 'English',
        'it' => 'Italiano',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'es' => 'español'
    ];

    private $poi_interactions = [
        'no_interaction' => 'Nessuna interazione sul POI',
        'tooltip' => 'Apre un tooltip con informazioni minime',
        'popup' => ' Apre il popup',
        'tooltip_popup' => 'apre Tooltip con X per chiudere Tooltip oppure un bottone che apre il popup'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')->sortable(),
            HasMany::make('Layers'), //display the relation with layers in nova field
            Text::make('API type', 'api')->sortable()->onlyOnDetail(),
            Text::make('Customer Name'),
        ];
    }

    public function fieldsForIndex()
    {

        return [
            ID::make(__('ID'), 'id')->sortable(),
            Text::make('API type', 'api')->sortable(),
            Text::make('Name')->sortable(),
            Text::make('Customer Name'),
            Text::make(__('APP'), function () {
                $urlAny = 'https://' . $this->model()->id . '.app.webmapp.it';
                $urlDesktop = 'https://' . $this->model()->id . '.app.geohub.webmapp.it';
                $urlMobile = 'https://' . $this->model()->id . '.mobile.webmapp.it';
                return "
                        <a style='display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding-left: 0.75rem;
                        padding-right: 0.75rem;
                        margin: 2px;
                        font-size: 1rem;
                        line-height: 1.5;
                        text-align: center;
                        cursor: pointer;
                        background-color: #79c35b;
                        color: #fff;
                        border-radius: 0.25rem;'
                        onmouseover='this.style.backgroundColor = \"#5a9c3f\";'
                        onmouseout='this.style.backgroundColor = \"#79c35b\";'
                        href='$urlAny' target='_blank'>ANY</a>

                        <a style='display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding-left: 0.75rem;
                        padding-right: 0.75rem;
                        margin: 2px;
                        font-size: 1rem;
                        line-height: 1.5;
                        text-align: center;
                        cursor: pointer;
                        background-color: #79c35b;
                        color: #fff;
                        border-radius: 0.25rem;'
                        onmouseover='this.style.backgroundColor = \"#5a9c3f\";'
                        onmouseout='this.style.backgroundColor = \"#79c35b\";'
                        href='$urlDesktop' target='_blank'>DESKTOP</a>

                        <a style='display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding-left: 0.75rem;
                        padding-right: 0.75rem;
                        margin: 2px;
                        font-size: 1rem;
                        line-height: 1.5;
                        text-align: center;
                        cursor: pointer;
                        background-color: #79c35b;
                        color: #fff;
                        border-radius: 0.25rem;'
                        onmouseover='this.style.backgroundColor = \"#5a9c3f\";'
                        onmouseout='this.style.backgroundColor = \"#79c35b\";'
                        href='$urlMobile' target='_blank'>MOBILE</a>";
            })->asHtml()
        ];
    }

    public function fieldsForDetail(Request $request)
    {

        if ($request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Editor)) {
            return [
                (new Tabs("APP Details: {$this->name} ({$this->id})", $this->sections()))->withToolbar(),
            ];
        }
    }

    public function fieldsForCreate()
    {
        $availableLanguages = is_null($this->model()->available_languages) ? [] : json_decode($this->model()->available_languages, true);

        return [
            Select::make(__('Api API'), 'api')->options(
                [
                    'elbrus' => 'Elbrus',
                    'webmapp' => 'WebMapp',
                    'webapp' => 'WebApp',
                ]
            )->required(),
            Text::make(__('App Id'), 'app_id')->required(),
            Text::make(__('Name'), 'name')->sortable()->required(),
            Text::make(__('Customer Name'), 'customer_name')->sortable()->required(),
            Select::make(__('Default Language'), 'default_language')->hideFromIndex()->options($this->languages)->displayUsingLabels()->required(),
            //todo: fix multiselect (array to string conversion error)
            //! update: works on update. Doesn't work on create
            Multiselect::make(__('Available Languages'), 'available_languages')->hideFromIndex()->options($this->languages, $availableLanguages)
        ];
    }

    public function fieldsForUpdate(Request $request)
    {
        if ($request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Editor)) {
            return [
                (new Tabs("APP Details: {$this->name} ({$this->id})", $this->sections()))->withToolbar(),
            ];
        }
    }


    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }

    public function sections()
    {
        return [
            'APP' => $this->app_tab(),
            'WEBAPP' => $this->webapp_tab(),
            'HOME' => $this->home_tab(),
            'PROJECT' => $this->project_tab(),
            'AUTH' => $this->auth_tab(),
            'OFFLINE' => $this->offline_tab(),
            'ICONS' => $this->icons_tab(),
            'LANGUAGES' => $this->languages_tab(),
            'MAP' => $this->map_tab(),
            'OPTIONS' => $this->options_tab(),
            'ROUTING' => $this->routing_tab(),
            'TABLE' => $this->table_tab(),
            'THEME' => $this->theme_tab(),

        ];
    }


    protected function app_tab(): array
    {
        return [
            Select::make(__('API type'), 'api')->options(
                [
                    'elbrus' => 'Elbrus',
                    'webmapp' => 'WebMapp',
                    'webapp' => 'WebApp',
                ]
            )->required(),
            Text::make(__('App Id'), 'app_id')->required(),
            Text::make(__('Name'), 'name')->sortable()->required(),
            Text::make(__('Customer Name'), 'customer_name')->sortable()->required(),
            Text::make(__('Play Store link (android)'), 'android_store_link'),
            Text::make(__('App Store link (iOS)'), 'ios_store_link'),
            Text::make(__('APP'), function () {
                $urlAny = 'https://' . $this->model()->id . '.app.webmapp.it';
                $urlDesktop = 'https://' . $this->model()->id . '.app.geohub.webmapp.it';
                $urlMobile = 'https://' . $this->model()->id . '.mobile.webmapp.it';
                return "
                        <a style='display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding-left: 0.75rem;
                        padding-right: 0.75rem;
                        margin: 2px;
                        font-size: 1rem;
                        line-height: 1.5;
                        text-align: center;
                        cursor: pointer;
                        background-color: #79c35b;
                        color: #fff;
                        border-radius: 0.25rem;'
                        onmouseover='this.style.backgroundColor = \"#5a9c3f\";'
                        onmouseout='this.style.backgroundColor = \"#79c35b\";'
                        href='$urlAny' target='_blank'>ANY</a>

                        <a style='display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding-left: 0.75rem;
                        padding-right: 0.75rem;
                        margin: 2px;
                        font-size: 1rem;
                        line-height: 1.5;
                        text-align: center;
                        cursor: pointer;
                        background-color: #79c35b;
                        color: #fff;
                        border-radius: 0.25rem;'
                        onmouseover='this.style.backgroundColor = \"#5a9c3f\";'
                        onmouseout='this.style.backgroundColor = \"#79c35b\";'
                        href='$urlDesktop' target='_blank'>DESKTOP</a>

                        <a style='display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding-left: 0.75rem;
                        padding-right: 0.75rem;
                        margin: 2px;
                        font-size: 1rem;
                        line-height: 1.5;
                        text-align: center;
                        cursor: pointer;
                        background-color: #79c35b;
                        color: #fff;
                        border-radius: 0.25rem;'
                        onmouseover='this.style.backgroundColor = \"#5a9c3f\";'
                        onmouseout='this.style.backgroundColor = \"#79c35b\";'
                        href='$urlMobile' target='_blank'>MOBILE</a>";
            })->asHtml()->onlyOnDetail(),
            Textarea::make('social_track_text')
                ->help(__('Add a description for meta tags of social share. You can customize the description with these keywords: {app.name} e {track.name}'))
                ->placeholder('Add social Meta Tag for description'),
            Boolean::make('dashboard_show'),
        ];
    }

    protected function webapp_tab(): array
    {
        return [
            Boolean::make(__('Show draw track'), 'draw_track_show')
                ->default(false),
            Boolean::make(__('Show editing inline'), 'editing_inline_show')
                ->default(false)

        ];
    }

    protected function home_tab(): array
    {
        return [
            NovaTabTranslatable::make([
                Text::make(__('Welcome'), 'welcome')
                    ->help(__('This is the welcome message displayed as the first element of the home.')),
            ]),
            Code::Make('Config Home')->language('json')->rules('json')->default('{"HOME": []}')
            //->help(
            //view('layers', ['layers' => $this->layers])->render()

        ];
    }

    protected function project_tab(): array
    {
        return [
            //!not working
            NovaTinymce5Editor::make('Page Project', 'page_project'),
            //* working
            Text::make('Page Project', 'page_project')->asHtml()

        ];
    }

    protected function auth_tab(): array
    {

        //? should i have to install Davidpiesse\NovaToggle\Toggle or i can do it with Nova Boolean?
        //* update: package installed, refactor code


        return [
            Toggle::make(__('Show Auth at startup'), 'auth_show_at_startup')
        ];
    }

    protected function offline_tab(): array
    {
        return [
            //* with Boolean field
            Boolean::make(__('Enable Offline'), 'offline_enable')
                ->default(false),
            Boolean::make(__('Force Auth'), 'offline_force_auth')
                ->default(false),
            Boolean::make(__('Tracks on payment'), 'tracks_on_payment')
                ->default(false)
        ];
    }

    protected function icons_tab(): array
    {
        return [
            Image::make(__('Icon'), 'icon')
                ->rules('image', 'mimes:png', 'dimensions:width=1024,height=1024')
                ->disk('public')
                ->path('api/app/' . $this->model()->id . '/resources')
                ->storeAs(function () {
                    return 'icon.png';
                })
                ->help(__('Required size is :widthpx:heightpx', ['width' => 1024, 'height' => 1024])),
            Image::make(__('Splash image'), 'splash')
                ->rules('image', 'mimes:png', 'dimensions:width=2732,height=2732')
                ->disk('public')
                ->path('api/app/' . $this->model()->id . '/resources')
                ->storeAs(function () {
                    return 'splash.png';
                })
                ->help(__('Required size is :widthpx:heightpx', ['width' => 2732, 'height' => 2732])),
            Image::make(__('Icon small'), 'icon_small')
                ->rules('image', 'mimes:png', 'dimensions:width=512,height=512')
                ->disk('public')
                ->path('api/app/' . $this->model()->id . '/resources')
                ->storeAs(function () {
                    return 'icon_small.png';
                })
                ->help(__('Required size is :widthpx:heightpx', ['width' => 512, 'height' => 512])),

            Image::make(__('Feature image'), 'feature_image')
                ->rules('image', 'mimes:png', 'dimensions:width=1024,height=500')
                ->disk('public')
                ->path('api/app/' . $this->model()->id . '/resources')
                ->storeAs(function () {
                    return 'feature_image.png';
                })
                ->help(__('Required size is :widthpx:heightpx', ['width' => 1024, 'height' => 500])),

            Image::make(__('Icon Notify'), 'icon_notify')
                ->rules('image', 'mimes:png', 'dimensions:ratio=1')
                ->disk('public')
                ->path('api/app/' . $this->model()->id . '/resources')
                ->storeAs(function () {
                    return 'icon_notify.png';
                })
                ->help(__('Required square png. Transparency is allowed and recommended for the background')),

            Image::make(__('Logo Homepage'), 'logo_homepage')
                ->rules('image', 'mimes:svg')
                ->disk('public')
                ->path('api/app/' . $this->model()->id . '/resources')
                ->storeAs(function () {
                    return 'logo_homepage.svg';
                })
                ->help(__('Required svg image'))
                ->hideFromIndex(),

            Code::Make(__('iconmoon selection.json'), 'iconmoon_selection')->language('json')->rules('nullable', 'json')->help(
                'import icoonmoon selection.json file'
            )
        ];
    }

    protected function languages_tab(): array
    {

        $availableLanguages = is_null($this->model()->available_languages) ? [] : json_decode($this->model()->available_languages, true);

        return [
            Text::make(__('Default Language'), 'default_language'),
            //! Multiselect is the same as in the fieldsForCreate() method, but works when App is updated, not when created.
            Multiselect::make(__('Available Languages'), 'available_languages')->options($this->languages, $availableLanguages)
        ];
    }

    protected function map_tab(): array
    {
        $selectedTileLayers = is_null($this->model()->tiles) ? [] : json_decode($this->model()->tiles, true);
        $mapTilerApiKey = '0Z7ou7nfFFXipdDXHChf';
        return [
            Multiselect::make(__('Tiles'), 'tiles')->options([
                "{\"notile\":\"\"}" => 'no tile',
                "{\"webmapp\":\"https://api.webmapp.it/tiles/{z}/{x}/{y}.png\"}" => 'webmapp',
                "{\"mute\":\"http://tiles.webmapp.it/blankmap/{z}/{x}/{y}.png\"}" => 'mute',
                "{\"satellite\":\"https://api.maptiler.com/tiles/satellite/{z}/{x}/{y}.jpg?key=$mapTilerApiKey\"}" => 'satellite',
                "{\"GOMBITELLI\":\"https://tiles.webmapp.it/mappa_gombitelli/{z}/{x}/{y}.png\"}" => 'GOMBITELLI',
            ], $selectedTileLayers)->help(__('seleziona quali tile layer verranno utilizzati dalla app, l\' lordine è il medesimo di inserimento quindi l\'ultimo inserito sarà quello visibile per primo')),
            Number::make(__('Max Zoom'), 'map_max_zoom')
                ->min(5)
                ->max(25)
                ->default(16)
                ->onlyOnForms(),
            Number::make(__('Max Stroke width'), 'map_max_stroke_width')
                ->min(0)
                ->max(19)
                ->default(6)
                ->help('Set max stoke width of line string, the max stroke width is applyed when the app is on max level zoom'),
            Number::make(__('Min Zoom'), 'map_min_zoom')
                ->min(5)
                ->max(19)
                ->default(12),
            Number::make(__('Min Stroke width'), 'map_min_stroke_width')
                ->min(0)
                ->max(19)
                ->default(3)
                ->help('Set min stoke width of line string, the min stroke width is applyed when the app is on min level zoom'),
            Number::make(__('Def Zoom'), 'map_def_zoom')
                ->min(5)
                ->max(19)
                ->default(12)
                ->onlyOnForms(),
            Text::make(__('Bounding BOX'), 'map_bbox')
                ->nullable()
                ->onlyOnForms()
                ->rules([
                    function ($attribute, $value, $fail) {
                        $decoded = json_decode($value);
                        if (is_array($decoded) == false) {
                            $fail('The ' . $attribute . ' is invalid. follow the example [9.9456,43.9116,11.3524,45.0186]');
                        }
                    }
                ])->help('Set the bounding box of the map in square [] brackets. example [9.9456,43.9116,11.3524,45.0186]'),

            Number::make(__('Max Zoom'), 'map_max_zoom')->onlyOnDetail(),
            Number::make(__('Min Zoom'), 'minZoom')->onlyOnDetail(),
            Number::make(__('Def Zoom'), 'defZoom')->onlyOnDetail(),
            Text::make(__('Bounding BOX'), 'bbox')->onlyOnDetail(),
            Number::make(__('start_end_icons_min_zoom'))->min(10)->max(20)
                ->help('Set minimum zoom at which start and end icons are shown in general maps (start_end_icons_show must be true)'),
            Number::make(__('ref_on_track_min_zoom'))->min(10)->max(20)
                ->help('Set minimum zoom at which ref parameter is shown on tracks line in general maps (ref_on_track_show must be true)'),
            // Text::make(__('POIS API'), function () {
            //     $url = '/api/v1/app/' . $this->model()->id . '/pois.geojson';
            //     return "<a class='btn btn-default btn-primary' href='$url' target='_blank'>POIS API</a>";
            // })->asHtml()->onlyOnDetail(),
            Toggle::make('start_end_icons_show')
                ->help('Activate this option if you want to show start and end point of all tracks in the general maps. Use the start_end_icons_min_zoom option to set the minum zoom at which thi feature is activated.'),
            Toggle::make('ref_on_track_show')
                ->help('Activate this option if you want to show ref parameter on tracks line. Use the ref_on_track_min_zoom option to set the minum zoom at which thi feature is activated.'),
            Toggle::make(__('geolocation_record_enable'), 'geolocation_record_enable')
                //->trueValue('On')
                //->falseValue('Off') //! error both methods do not exist
                ->default(false)
                ->hideFromIndex()
                ->help('Activate this option if you want enable user track record'),
            Toggle::make('alert_poi_show')
                ->help('Activate this option if you want to show a poi proximity alert'),
            Number::make(__('alert_poi_radius'))->default(100)->help('set the radius(in meters) of the activation circle with center the user position, the nearest poi inside the circle trigger the alert'),
            Toggle::make('flow_line_quote_show')
                ->help('Activate this option if you want to color track by quote'),
            Number::make(__('flow_line_quote_orange'))->default(800)->help('defines the elevation by which the track turns orange'),
            Number::make(__('flow_line_quote_red'))->default(1500)->help('defines the elevation by which the track turns red'),
        ];
    }

    protected function options_tab(): array
    {
        return [
            Select::make(__('Start Url'), 'start_url')
                ->options([
                    '/main/explore' => 'Home',
                    '/main/map' => 'Map',
                ])
                ->default('/main/explore'),
            Toggle::make(__('Show Edit Link'), 'show_edit_link')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(false)
                ->onlyOnForms(),
            Toggle::make(__('Skip Route Index Download'), 'skip_route_index_download')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->onlyOnForms(),
            Toggle::make(__('Show Track Ref Label'), 'show_track_ref_label')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(false)
                ->hideFromIndex(),
            Toggle::make(__('download_track_enable'), 'download_track_enable')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex()
                ->help(__('Enable download of ever app track in GPX, KML, GEOJSON')),
            Toggle::make(__('print_track_enable'), 'print_track_enable')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex()
                ->help(__('Enable print of ever app track in PDF')),

        ];
    }

    protected function routing_tab(): array
    {
        return [
            Toggle::make(__('Enable Routing'), 'enable_routing')
                // ->trueValue('On')
                //     ->falseValue('Off')
                ->default(false)
                ->hideFromIndex(),
        ];
    }

    protected function table_tab(): array
    {
        return [
            Toggle::make(__('Show Related POI'), 'table_details_show_related_poi')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(false)
                ->hideFromIndex(),
            Toggle::make(__('Show Duration'), 'table_details_show_duration_forward')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Duration Backward'), 'table_details_show_duration_backward')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex()
                ->hideFromIndex(),
            Toggle::make(__('Show Distance'), 'table_details_show_distance')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Ascent'), 'table_details_show_ascent')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Descent'), 'table_details_show_descent')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Ele Max'), 'table_details_show_ele_max')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Ele Min'), 'table_details_show_ele_min')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Ele From'), 'table_details_show_ele_from')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Ele To'), 'table_details_show_ele_to')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Scale'), 'table_details_show_scale')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Cai Scale'), 'table_details_show_cai_scale')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Mtb Scale'), 'table_details_show_mtb_scale')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Ref'), 'table_details_show_ref')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(true)
                ->hideFromIndex(),
            Toggle::make(__('Show Surface'), 'table_details_show_surface')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(false)
                ->hideFromIndex(),
            Toggle::make(__('Show GPX Download'), 'table_details_show_gpx_download')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(false)
                ->hideFromIndex(),
            Toggle::make(__('Show KML Download'), 'table_details_show_kml_download')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(false)
                ->hideFromIndex(),
            Toggle::make(__('Show Geojson Download'), 'table_details_show_geojson_download')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(false)
                ->hideFromIndex(),
            Toggle::make(__('Show Shapefile Download'), 'table_details_show_shapefile_download')
                // ->trueValue('On')
                // ->falseValue('Off')
                ->default(false)
                ->hideFromIndex()
        ];
    }

    protected function theme_tab(): array
    {
        $fontsOptions = [
            'Helvetica' => ['label' => 'Helvetica'],
            'Inter' => ['label' => 'Inter'],
            'Lato' => ['label' => 'Lato'],
            'Merriweather' => ['label' => 'Merriweather'],
            'Montserrat' => ['label' => 'Montserrat'],
            'Montserrat Light' => ['label' => 'Montserrat Light'],
            'Monrope' => ['label' => 'Monrope'],
            'Noto Sans' => ['label' => 'Noto Sans'],
            'Noto Serif' => ['label' => 'Noto Serif'],
            'Open Sans' => ['label' => 'Roboto'],
            'Roboto' => ['label' => 'Noto Serif'],
            'Roboto Slab' => ['label' => 'Roboto Slab'],
            'Sora' => ['label' => 'Sora'],
            'Source Sans Pro' => ['label' => 'Source Sans Pro']
        ];

        return [
            Select::make(__('Font Family Header'), 'font_family_header')
                ->options($fontsOptions)
                ->default('Roboto Slab')
                ->hideFromIndex(),
            Select::make(__('Font Family Content'), 'font_family_content')
                ->options($fontsOptions)
                ->default('Roboto')
                ->hideFromIndex(),
            Color::make(__('Default Feature Color'), 'default_feature_color')
                ->default('#de1b0d')
                ->hideFromIndex(),
            Color::make(__('Primary color'), 'primary_color')
                ->default('#de1b0d')
                ->hideFromIndex(),
        ];
    }
}
