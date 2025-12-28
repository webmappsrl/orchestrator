<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Http\Requests\NovaRequest;

class CustomerFundraisingOpportunity extends Resource
{
    /**
     * The model the resource corresponds to.
     * Usa lo stesso modello di FundraisingOpportunity
     *
     * @var class-string<\App\Models\FundraisingOpportunity>
     */
    public static $model = \App\Models\FundraisingOpportunity::class;

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
        'name', 'program_name', 'sponsor'
    ];

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'FundRaising Customer';

    /**
     * Indicates if the resource should be displayed in the sidebar.
     */
    public static $displayInNavigation = true;

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Bandi');
    }

    /**
     * Determine if the current user can view any resources.
     */
    public static function authorizedToViewAny(Request $request): bool
    {
        $user = $request->user();
        if ($user == null) {
            return false;
        }
        return $user->hasRole(\App\Enums\UserRole::Customer);
    }

    /**
     * Get the fields displayed by the resource.
     * Versione semplificata per i customer - solo visualizzazione
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Nome del Bando', 'name')
                ->sortable()
                ->readonly(),

            URL::make('URL Ufficiale', 'official_url')
                ->displayUsing(function ($url) {
                    return $url ? parse_url($url, PHP_URL_HOST) : null;
                })
                ->readonly(),

            Number::make('Fondo di Dotazione', 'endowment_fund')
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                })
                ->readonly(),

            Date::make('Data di Scadenza', 'deadline')
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : null;
                })
                ->readonly(),

            Text::make('Nome del Programma', 'program_name')
                ->sortable()
                ->readonly(),

            Text::make('Sponsor', 'sponsor')
                ->sortable()
                ->readonly(),

            Number::make('Quota Cofinanziamento (%)', 'cofinancing_quota')
                ->displayUsing(function ($value) {
                    return $value ? $value . '%' : null;
                })
                ->readonly(),

            Number::make('Contributo Massimo', 'max_contribution')
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                })
                ->readonly(),

            Select::make('Scope Territoriale', 'territorial_scope')
                ->options([
                    'cooperation' => 'Cooperazione',
                    'european' => 'Europeo',
                    'national' => 'Nazionale',
                    'regional' => 'Regionale',
                    'territorial' => 'Territoriale',
                    'municipalities' => 'Comuni',
                ])
                ->sortable()
                ->readonly(),

            Textarea::make('Requisiti del Beneficiario', 'beneficiary_requirements')
                ->rows(3)
                ->readonly(),

            Textarea::make('Requisiti del Capofila', 'lead_requirements')
                ->rows(3)
                ->readonly(),

            BelongsTo::make('Responsabile', 'responsibleUser', User::class)
                ->readonly(),

            Boolean::make('Scaduto', function () {
                return $this->isExpired();
            }),
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
        return [
            new \App\Nova\Filters\TerritorialScopeFilter,
            new \App\Nova\Filters\ExpiredFilter,
        ];
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
     * Solo azioni per customer: creazione Story
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [
            new \App\Nova\Actions\CreateStoryFromFundraisingOpportunity,
        ];
    }
}
