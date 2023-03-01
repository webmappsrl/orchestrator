<?php

namespace App\Nova;

use Eminiarts\Tabs\Tabs;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
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

            Tabs::make('App', [
                'General' => [
                    Text::make('App ID')->sortable(),
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
                    })->asHtml(),


                ]
            ])
        ];
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
}
