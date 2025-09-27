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
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Http\Requests\NovaRequest;

class FundraisingOpportunity extends Resource
{
    /**
     * The model the resource corresponds to.
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
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        // Se siamo nella vista index, usa i campi ridotti
        if ($request->isResourceIndexRequest() && !$request->isCreateOrAttachRequest() && !$request->isUpdateOrUpdateAttachedRequest()) {
            return $this->fieldsForIndex($request);
        }

        // Per dettaglio, creazione e modifica usa tutti i campi
        return [
            ID::make()->sortable(),

            Text::make('Nome del Bando', 'name')
                ->rules('required', 'max:255')
                ->sortable(),

            URL::make('URL Ufficiale', 'official_url')
                ->nullable()
                ->displayUsing(function ($url) {
                    return $url ? parse_url($url, PHP_URL_HOST) : null;
                }),

            Number::make('Fondo di Dotazione', 'endowment_fund')
                ->step(0.01)
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                }),

            Date::make('Data di Scadenza', 'deadline')
                ->rules('required')
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : null;
                }),

            Text::make('Nome del Programma', 'program_name')
                ->nullable()
                ->sortable(),

            Text::make('Sponsor', 'sponsor')
                ->nullable()
                ->sortable(),

            Number::make('Quota Cofinanziamento (%)', 'cofinancing_quota')
                ->step(0.01)
                ->min(0)
                ->max(100)
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? $value . '%' : null;
                }),

            Number::make('Contributo Massimo', 'max_contribution')
                ->step(0.01)
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                }),

            Select::make('Scope Territoriale', 'territorial_scope')
                ->options([
                    'cooperation' => 'Cooperazione',
                    'european' => 'Europeo',
                    'national' => 'Nazionale',
                    'regional' => 'Regionale',
                    'territorial' => 'Territoriale',
                    'municipalities' => 'Comuni',
                ])
                ->rules('required')
                ->sortable(),

            Textarea::make('Requisiti del Beneficiario', 'beneficiary_requirements')
                ->nullable()
                ->rows(3),

            Textarea::make('Requisiti del Capofila', 'lead_requirements')
                ->nullable()
                ->rows(3),

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

            Boolean::make('Scaduto', function () {
                return $this->isExpired();
            })->exceptOnForms(),

            Text::make('Progetti Associati', function () {
                $projects = $this->projects;
                if ($projects->isEmpty()) {
                    return 'Nessun progetto associato';
                }
                
                $projectLinks = $projects->map(function ($project) {
                    $url = '/resources/fundraising-projects/' . $project->id;
                    $title = htmlspecialchars($project->title);
                    $status = ucfirst($project->status);
                    return '<a href="' . $url . '" class="link-default">' . $title . '</a> (' . $status . ')';
                })->toArray();
                
                return implode(', ', $projectLinks);
            })->onlyOnDetail()->asHtml(),
        ];
    }

    /**
     * Get the fields for the index view.
     */
    public function fieldsForIndex(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Nome del Bando', 'name')
                ->rules('required', 'max:255')
                ->sortable(),

            Date::make('Data di Scadenza', 'deadline')
                ->rules('required')
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : null;
                }),

            Text::make('Sponsor', 'sponsor')
                ->nullable()
                ->sortable(),

            Select::make('Scope Territoriale', 'territorial_scope')
                ->options([
                    'cooperation' => 'Cooperazione',
                    'european' => 'Europeo',
                    'national' => 'Nazionale',
                    'regional' => 'Regionale',
                    'territorial' => 'Territoriale',
                    'municipalities' => 'Comuni',
                ])
                ->rules('required')
                ->sortable(),

            BelongsTo::make('Responsabile', 'responsibleUser', User::class)
                ->searchable(),

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
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [
            new \App\Nova\Actions\ExportFundraisingOpportunityPdf,
        ];
    }

    /**
     * Get the URI key for the resource.
     */
    public static function uriKey(): string
    {
        return 'fundraising-opportunities';
    }
}
