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
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return 'Opportunities';
    }

    /**
     * Wrap text at specified length without breaking words.
     *
     * @param  string  $text
     * @param  int  $length
     * @return string
     */
    private function wrapText($text, $length = 80)
    {
        if (empty($text)) {
            return '';
        }

        if (mb_strlen($text) <= $length) {
            return htmlspecialchars($text);
        }

        $wrapped = '';
        $words = explode(' ', $text);
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
            
            if (mb_strlen($testLine) <= $length) {
                $currentLine = $testLine;
            } else {
                if ($currentLine !== '') {
                    $wrapped .= htmlspecialchars($currentLine) . '<br>';
                    $currentLine = $word;
                } else {
                    // Word is longer than length, force break
                    $wrapped .= htmlspecialchars($word) . '<br>';
                    $currentLine = '';
                }
            }
        }

        if ($currentLine !== '') {
            $wrapped .= htmlspecialchars($currentLine);
        }

        return $wrapped;
    }

    /**
     * Get the color for the deadline based on days remaining.
     *
     * @param  \Carbon\Carbon|null  $deadline
     * @return string
     */
    private function getDeadlineColor($deadline)
    {
        if (!$deadline) {
            return '#6b7280'; // gray
        }

        $now = now();
        $daysRemaining = $now->diffInDays($deadline, false);

        // Scaduto
        if ($daysRemaining < 0) {
            return '#dc2626'; // red
        }

        // ~30 giorni (0-30 giorni rimanenti)
        if ($daysRemaining <= 30) {
            return '#ea580c'; // orange
        }

        // ~60 giorni (31-90 giorni rimanenti)
        if ($daysRemaining <= 90) {
            return '#eab308'; // yellow
        }

        // > 90 giorni
        return '#22c55e'; // green
    }

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Se l'utente non ha specificato un ordinamento personalizzato, ordina per deadline ASC (dalla più lontana alla più vicina)
        if (!$request->get('orderBy')) {
            return $query->orderBy('deadline', 'asc');
        }

        return $query;
    }

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
                ->onlyOnForms(),

            Text::make('Data di Scadenza', function () {
                $date = $this->deadline;
                if (!$date) {
                    return null;
                }
                $bgColor = $this->getDeadlineColor($date);
                $formattedDate = $date->format('d/m/Y');
                // Usa testo bianco per rosso e arancione, testo scuro per giallo e verde
                $textColor = (in_array($bgColor, ['#dc2626', '#ea580c'])) ? '#ffffff' : '#1f2937';
                return '<span style="background-color: ' . $bgColor . '; color: ' . $textColor . '; padding: 4px 8px; border-radius: 4px; font-weight: 600; display: inline-block;">' . htmlspecialchars($formattedDate) . '</span>';
            })->exceptOnForms()->asHtml()->sortable(),

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

            Text::make('Bando', function () {
                $scopeLabel = $this->territorial_scope_label ?? $this->territorial_scope ?? '';
                $sponsor = $this->sponsor ?? '';
                $name = $this->name ?? '';
                
                // Riga 1: [scope territoriale] / [sponsor] o "(no sponsor)" se manca
                $sponsorText = $sponsor ? $sponsor : '(no sponsor)';
                $row1 = trim($scopeLabel . ' / ' . $sponsorText);
                
                // Riga 2: [nome bando] con word-wrap dopo 80 caratteri
                $row2 = $this->wrapText($name, 80);
                
                $html = '<div>';
                if ($row1) {
                    $html .= '<div style="font-weight: 500; font-style: italic; margin-bottom: 4px;">' . htmlspecialchars($row1) . '</div>';
                }
                if ($row2) {
                    $html .= '<div style="line-height: 1.4;">' . $row2 . '</div>';
                }
                $html .= '</div>';
                
                return $html;
            })->asHtml(),

            Date::make('Data di Scadenza', 'deadline')
                ->sortable()
                ->hideFromIndex(),

            Text::make('Data di Scadenza', function () {
                $date = $this->deadline;
                if (!$date) {
                    return null;
                }
                $bgColor = $this->getDeadlineColor($date);
                $formattedDate = $date->format('d/m/Y');
                // Usa testo bianco per rosso e arancione, testo scuro per giallo e verde
                $textColor = (in_array($bgColor, ['#dc2626', '#ea580c'])) ? '#ffffff' : '#1f2937';
                
                $now = now();
                $daysRemaining = $now->diffInDays($date, false);
                
                $html = '<span style="background-color: ' . $bgColor . '; color: ' . $textColor . '; padding: 4px 8px; border-radius: 4px; font-weight: 600; display: inline-block;">' . htmlspecialchars($formattedDate) . '</span>';
                
                // Per i bandi non ancora scaduti, aggiungi i giorni rimanenti tra parentesi fuori dal colore
                if ($daysRemaining >= 0) {
                    $daysText = $daysRemaining == 1 ? 'giorno' : 'giorni';
                    $html .= ' <span style="margin-left: 4px;">(' . $daysRemaining . ' ' . $daysText . ')</span>';
                }
                
                return $html;
            })->asHtml()->onlyOnIndex(),

            Number::make('Cofin.', 'cofinancing_quota')
                ->displayUsing(function ($value) {
                    return $value ? $value . '%' : null;
                })
                ->sortable(),

            BelongsTo::make('Responsabile', 'responsibleUser', User::class)
                ->searchable(),
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
            new \App\Nova\Filters\CofinancingFilter,
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
            new \App\Nova\Actions\CreateFundraisingOpportunityFromJson,
            new \App\Nova\Actions\CreateProjectFromOpportunity,
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
