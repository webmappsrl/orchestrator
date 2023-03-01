<?php

namespace App\Nova;

use App\Enums\UserRole;
use Eminiarts\Tabs\Tabs;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Http\Requests\NovaRequest;


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
            // 'HOME' => $this->home_tab(),
            // 'PROJECT' => $this->project_tab(),
            // 'AUTH' => $this->auth_tab(),
            // 'OFFLINE' => $this->offline_tab(),
            // 'ICONS' => $this->icons_tab(),
            // 'LANGUAGES' => $this->languages_tab(),
            // 'MAP' => $this->map_tab(),
            // 'OPTIONS' => $this->options_tab(),
            // 'ROUTING' => $this->routing_tab(),
            // 'TABLE' => $this->table_tab(),
            // 'THEME' => $this->theme_tab(),

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
}
