<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\MorphTo;
use App\Nova\Metrics\TagSal;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Fields\MorphToMany;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Laravel\Nova\Fields\Boolean;

class Tag extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Tag::class;

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
        'id',
        'name'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),
            Number::make('Estimate (Hours)', 'estimate') // Specifica che è in ore
                ->min(0) // Imposta un valore minimo di 0
                ->step(
                    1,  // Permette incrementi di 0.5 ore
                )->onlyOnForms(),
            Text::make('Sal')->resolveUsing(function () {
                $empty = __('Empty');
                $totalHours = $this->getTotalHoursAttribute() ?? $empty; // Calcola la somma delle ore
                $estimate = $this->estimate ?? $empty; // Ottieni il valore stimato
                $color = $this->isClosed() ? 'green' : 'orange';
                $salPercentage = $this->calculateSalPercentage();
                $trend = $salPercentage >= 100 ? '😡' : '😊';

                if (!$this->getTotalHoursAttribute() && !$this->estimate) {
                    return  <<<HTML
                        <a style="font-weight:bold;"> $empty </a> 
                    HTML;
                }
                if (!$this->getTotalHoursAttribute() || !$this->estimate) {
                    return  <<<HTML
                        <a style="font-weight:bold;"> $totalHours / $estimate </a> 
                    HTML;
                }

                return  <<<HTML
                    $trend
                    <a style="color:{$color}; font-weight:bold;"> $totalHours / $estimate </a> 
                    <a style="color:{$color}; font-weight:bold;"> [$salPercentage%] </a>
                HTML;
            })->asHtml()->onlyOnIndex(),
            MarkdownTui::make('Description')
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),
            Number::make('Estimate', 'estimate')
                ->min(0)
                ->step(1)
                ->onlyOnDetail(),
            MorphTo::make('Taggable')->types([
                \App\Nova\Customer::class,
                \App\Nova\App::class,
                \App\Nova\Documentation::class
            ])->nullable()->hideFromIndex(),
            MorphToMany::make('Tagged', 'tagged', \App\Nova\Story::class),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [
            (new TagSal())->onlyOnDetail()
        ];
    }

    /**
     * Get the filters available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}
