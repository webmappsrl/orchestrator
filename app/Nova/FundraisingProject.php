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

class FundraisingProject extends Resource
{
    /**
     * The model the resource corresponds to.
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
     * Get the fields for the detail view.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fieldsForDetail(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Titolo del Progetto', 'title')
                ->rules('required', 'max:255')
                ->sortable(),

            BelongsTo::make('Opportunità di Finanziamento', 'fundraisingOpportunity', FundraisingOpportunity::class)
                ->rules('required')
                ->searchable(),

            BelongsTo::make('Capofila', 'leadUser', User::class)
                ->rules('required')
                ->searchable()
                ->help('Utente con ruolo customer')
                ->relatableQueryUsing(function (NovaRequest $request, $query) {
                    return $query->whereJsonContains('roles', 'customer');
                }),

            BelongsToMany::make('Partner', 'partners', User::class)
                ->searchable()
                ->showOnDetail()
                ->showOnIndex()
                ->showOnCreating()
                ->showOnUpdating()
                ->nullable()
                ->help('Clicca su "Attach" per aggiungere partner o "Detach" per rimuoverli'),

            BelongsTo::make('Creato da', 'creator', User::class)
                ->exceptOnForms()
                ->showOnDetail()
                ->showOnIndex(false),

            BelongsTo::make('Responsabile', 'responsibleUser', User::class)
                ->rules('required')
                ->help('Solo utenti con ruolo fundraising')
                ->relatableQueryUsing(function (NovaRequest $request, $query) {
                    return $query->whereJsonContains('roles', 'fundraising');
                }),

            Textarea::make('Idea Progettuale', 'description')
                ->nullable()
                ->rows(4)
                ->alwaysShow()
                ->help('Inserisci un abstract del progetto che descriva gli obiettivi, le attività principali e i risultati attesi del progetto di fundraising'),

            Select::make('Stato', 'status')
                ->options([
                    'draft' => 'Bozza',
                    'submitted' => 'Presentato',
                    'approved' => 'Approvato',
                    'rejected' => 'Respinto',
                    'completed' => 'Completato',
                ])
                ->rules('required')
                ->sortable(),

            Number::make('Importo Richiesto', 'requested_amount')
                ->step(0.01)
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                }),

            Number::make('Importo Approvato', 'approved_amount')
                ->step(0.01)
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                }),

            Date::make('Data di Presentazione', 'submission_date')
                ->nullable()
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : null;
                }),

            Date::make('Data di Decisione', 'decision_date')
                ->nullable()
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : null;
                }),
        ];
    }

    /**
     * Get the fields for the index view.
     */
    public function fieldsForIndex(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Titolo del Progetto', 'title')
                ->rules('required', 'max:255')
                ->sortable(),

            BelongsTo::make('Opportunità di Finanziamento', 'fundraisingOpportunity', FundraisingOpportunity::class)
                ->searchable(),

            BelongsTo::make('Capofila', 'leadUser', User::class)
                ->searchable()
                ->relatableQueryUsing(function (NovaRequest $request, $query) {
                    return $query->whereJsonContains('roles', 'customer');
                }),

            Select::make('Stato', 'status')
                ->options([
                    'draft' => 'Bozza',
                    'submitted' => 'Presentato',
                    'approved' => 'Approvato',
                    'rejected' => 'Respinto',
                    'completed' => 'Completato',
                ])
                ->rules('required')
                ->sortable(),

            Number::make('Importo Richiesto', 'requested_amount')
                ->step(0.01)
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                })
                ->sortable(),

            BelongsTo::make('Responsabile', 'responsibleUser', User::class)
                ->searchable(),
        ];
    }

    public function fields(NovaRequest $request)
    {
        // Se siamo nella vista index, usa i campi ridotti
        if ($request->isResourceIndexRequest() && !$request->isCreateOrAttachRequest() && !$request->isUpdateOrUpdateAttachedRequest()) {
            return $this->fieldsForIndex($request);
        }

        // Per dettaglio, creazione e modifica usa tutti i campi
        return $this->fieldsForDetail($request);
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

    /**
     * Determine if the current user can create new resources.
     */
    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    /**
     * Determine if the current user can update the given resource.
     */
    public function authorizedToUpdate(Request $request): bool
    {
        $user = $request->user();
        return $user->hasRole(\App\Enums\UserRole::Fundraising) || 
               $user->hasRole(\App\Enums\UserRole::Admin);
    }

    /**
     * Get the URI key for the resource.
     */
    public static function uriKey(): string
    {
        return 'fundraising-projects';
    }
}
