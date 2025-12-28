<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Http\Requests\NovaRequest;

class CustomerFundraisingProject extends Resource
{
    /**
     * The model the resource corresponds to.
     * Usa lo stesso modello di FundraisingProject
     *
     * @var class-string<\App\Models\FundraisingProject>
     */
    public static $model = \App\Models\FundraisingProject::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'title';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'title', 'description'
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
        return __('Progetti');
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
     * Build an "index" query for the given resource.
     * Filtra solo i progetti dove l'utente è coinvolto (capofila o partner)
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user() && $request->user()->hasRole(\App\Enums\UserRole::Customer)) {
            return $query->whereInvolved($request->user()->id);
        }
        return $query;
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

            Text::make('Titolo del Progetto', 'title')
                ->sortable()
                ->readonly(),

            BelongsTo::make('Opportunità di Finanziamento', 'fundraisingOpportunity', CustomerFundraisingOpportunity::class)
                ->searchable()
                ->readonly(),

            BelongsTo::make('Capofila', 'leadUser', User::class)
                ->searchable()
                ->readonly(),

            BelongsToMany::make('Partner', 'partners', User::class)
                ->searchable()
                ->readonly(),

            Textarea::make('Descrizione', 'description')
                ->rows(4)
                ->readonly(),

            Select::make('Stato', 'status')
                ->options([
                    'draft' => 'Bozza',
                    'submitted' => 'Presentato',
                    'approved' => 'Approvato',
                    'rejected' => 'Respinto',
                    'completed' => 'Completato',
                ])
                ->sortable()
                ->readonly(),

            Number::make('Importo Richiesto', 'requested_amount')
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                })
                ->readonly(),

            Number::make('Importo Approvato', 'approved_amount')
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                })
                ->readonly(),

            Date::make('Data di Presentazione', 'submission_date')
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : null;
                })
                ->readonly(),

            Date::make('Data di Decisione', 'decision_date')
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : null;
                })
                ->readonly(),

            HasMany::make('Storie/Ticket', 'stories', Story::class),
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
            new \App\Nova\Filters\ProjectStatusFilter,
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
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
