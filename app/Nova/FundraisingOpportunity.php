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
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;
use Eminiarts\Tabs\Tabs;
use Eminiarts\Tabs\Tab;
use Eminiarts\Tabs\Traits\HasTabs;

class FundraisingOpportunity extends Resource
{
    use HasTabs;
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
        'name', 'program_name', 'sponsor', 'responsibleUser.name', 'responsibleUser.email'
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
     * Mostra solo le opportunità attive (deadline >= now).
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Filtra solo le opportunità attive (non scadute)
        $query = $query->where('deadline', '>=', now());

        // Se l'utente non ha specificato un ordinamento personalizzato, ordina per deadline ASC
        if (!$request->get('orderBy')) {
            // Rimuovi eventuali ordinamenti esistenti e applica quello di default
            return $query->reorder()->orderBy('deadline', 'asc');
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

        // Costruisci l'array di tab dinamicamente
        $tabs = [
            new Tab('Principale', $this->getMainTabFields($request)),
            new Tab('Economics', $this->getEconomicsTabFields()),
        ];

        // Aggiungi tab Valutazione se siamo in dettaglio o modifica
        if ($request->isResourceDetailRequest() || $request->isUpdateOrUpdateAttachedRequest() || $request->isCreateOrAttachRequest()) {
            $tabs[] = new Tab('Valutazione', $this->getEvaluationTabFields($request));
        }

        // Mostra sempre il tab "Progetti" nella vista detail con il conteggio dei progetti
        if ($request->isResourceDetailRequest() && $this->resource && $this->resource->exists) {
            $projectsCount = $this->resource->projects()->count();
            $tabs[] = new Tab('Progetti (' . $projectsCount . ')', $this->getProjectsTabFields());
        }

        // Per dettaglio, creazione e modifica usa tutti i campi organizzati in tab
        return [
            new Tabs('Dettagli Opportunità', $tabs),
        ];
    }

    /**
     * Get fields for the "Principale" tab.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    private function getMainTabFields(NovaRequest $request): array
    {
        $fields = [
            ID::make()->sortable(),

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
                ->sortable()
                ->showOnUpdating()
                ->showOnCreating(),

            Text::make('Sponsor', 'sponsor')
                ->nullable()
                ->sortable()
                ->showOnUpdating()
                ->showOnCreating(),

            Text::make('Nome del Programma', 'program_name')
                ->nullable()
                ->sortable()
                ->showOnUpdating()
                ->showOnCreating(),

            Text::make('Nome del Bando', 'name')
                ->rules('required', 'max:255')
                ->sortable()
                ->showOnUpdating()
                ->showOnCreating(),

            Date::make('Data di Scadenza', 'deadline')
                ->rules('required')
                ->showOnUpdating()
                ->showOnCreating(),

            Text::make('Scadenza', function () {
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
                
                // Aggiungi i giorni rimanenti
                if ($daysRemaining < 0) {
                    $daysText = abs($daysRemaining) == 1 ? 'giorno fa' : 'giorni fa';
                    $html .= ' <span style="margin-left: 4px; color: #dc2626;">(scaduto ' . abs($daysRemaining) . ' ' . $daysText . ')</span>';
                } else {
                    $daysText = $daysRemaining == 1 ? 'giorno' : 'giorni';
                    $html .= ' <span style="margin-left: 4px;">(' . $daysRemaining . ' ' . $daysText . ' rimanenti)</span>';
                }
                
                return $html;
            })->exceptOnForms()->asHtml()->sortable(),

            BelongsTo::make('Responsabile', 'responsibleUser', User::class)
                ->rules('required')
                ->help('Solo utenti con ruolo fundraising')
                ->relatableQueryUsing(function (NovaRequest $request, $query) {
                    return $query->whereJsonContains('roles', 'fundraising');
                })
                ->showOnUpdating()
                ->showOnCreating(),

            BelongsTo::make('Creato da', 'creator', User::class)
                ->exceptOnForms()
                ->showOnDetail()
                ->showOnIndex(false),
        ];

        return $fields;
    }

    /**
     * Get fields for the "Economics" tab.
     *
     * @return array
     */
    private function getEconomicsTabFields(): array
    {
        return [
            Number::make('Fondo di Dotazione', 'endowment_fund')
                ->step(0.01)
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                }),

            Number::make('Quota Cofinanziamento (%)', 'cofinancing_quota')
                ->step(0.01)
                ->min(0)
                ->max(100)
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? $value . '%' : null;
                }),

            Textarea::make('Requisiti del Beneficiario', 'beneficiary_requirements')
                ->nullable()
                ->rows(10)
                ->alwaysShow(),

            Textarea::make('Requisiti del Capofila', 'lead_requirements')
                ->nullable()
                ->rows(10)
                ->alwaysShow(),
        ];
    }

    /**
     * Get fields for the "Progetti" tab.
     *
     * @return array
     */
    private function getProjectsTabFields(): array
    {
        return [
            Text::make('Progetti Associati', function () {
                $projects = $this->projects;
                if ($projects->isEmpty()) {
                    return '<p style="color: #6b7280; font-style: italic;">Non sono ancora stati attivati progetti in questa opportunità</p>';
                }
                
                $projectLinks = $projects->map(function ($project) {
                    $url = '/resources/fundraising-projects/' . $project->id;
                    $title = htmlspecialchars($project->title);
                    return '<li style="margin-bottom: 8px;"><a href="' . $url . '" class="link-default" style="text-decoration: none; color: #2563eb;">' . $title . '</a></li>';
                })->toArray();
                
                return '<ul style="list-style-type: disc; padding-left: 20px; margin: 0;">' . implode('', $projectLinks) . '</ul>';
            })->asHtml(),
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

            Number::make('P+', 'evaluation_total_positive')
                ->sortable()
                ->displayUsing(function ($value) {
                    return $value ?? 0;
                }),

            Number::make('P-', 'evaluation_total_negative')
                ->sortable()
                ->displayUsing(function ($value) {
                    return $value ?? 0;
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

    /**
     * Get fields for the "Valutazione" tab.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    private function getEvaluationTabFields(NovaRequest $request): array
    {
        $evaluationTabs = [
            new Tab('Parte 1 - Criteri Principali', $this->getMainCriteriaFields()),
            new Tab('Requisiti di Base', $this->getBaseRequirementsFields()),
            new Tab('Valutazione Qualitativa', $this->getQualitativeEvaluationFields()),
            new Tab('Fattori Premiali', $this->getBonusFactorsFields()),
            new Tab('Rischi', $this->getRisksFields()),
            new Tab('Riepilogo', $this->getSummaryFields()),
        ];

        return [
            new Tabs('Valutazione Progetto', $evaluationTabs),
        ];
    }

    /**
     * Get fields for main criteria (Parte 1).
     */
    private function getMainCriteriaFields(): array
    {
        return [
            Number::make('A. Coerenza e rilevanza della proposta - Punteggio', 'evaluation_criterion_a_score')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),
            
            Textarea::make('A. Coerenza e rilevanza della proposta - Descrizione', 'evaluation_criterion_a_description')
                ->nullable()
                ->rows(3),

            Number::make('B. Qualità dell\'idea e fattibilità tecnica/organizzativa - Punteggio', 'evaluation_criterion_b_score')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),
            
            Textarea::make('B. Qualità dell\'idea e fattibilità tecnica/organizzativa - Descrizione', 'evaluation_criterion_b_description')
                ->nullable()
                ->rows(3),

            Number::make('C. Impatto su soci, territorio e comunità - Punteggio', 'evaluation_criterion_c_score')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),
            
            Textarea::make('C. Impatto su soci, territorio e comunità - Descrizione', 'evaluation_criterion_c_description')
                ->nullable()
                ->rows(3),

            Number::make('D. Valore aggiunto e replicabilità - Punteggio', 'evaluation_criterion_d_score')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),
            
            Textarea::make('D. Valore aggiunto e replicabilità - Descrizione', 'evaluation_criterion_d_description')
                ->nullable()
                ->rows(3),

            Number::make('E. Partenariato e capacità operativa - Punteggio', 'evaluation_criterion_e_score')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),
            
            Textarea::make('E. Partenariato e capacità operativa - Descrizione', 'evaluation_criterion_e_description')
                ->nullable()
                ->rows(3),

            Number::make('F. Sostenibilità economica e gestionale - Punteggio', 'evaluation_criterion_f_score')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),
            
            Textarea::make('F. Sostenibilità economica e gestionale - Descrizione', 'evaluation_criterion_f_description')
                ->nullable()
                ->rows(3),
        ];
    }

    /**
     * Get fields for base requirements.
     */
    private function getBaseRequirementsFields(): array
    {
        return [
            Number::make('Coerenza bando', 'evaluation_base_coerenza_bando')
                ->min(0)
                ->max(1)
                ->step(1)
                ->nullable()
                ->help('Punteggio: 0 o 1'),

            Number::make('Capofila idoneo', 'evaluation_base_capofila_idoneo')
                ->min(0)
                ->max(1)
                ->step(1)
                ->nullable()
                ->help('Punteggio: 0 o 1'),

            Number::make('Partner minimi', 'evaluation_base_partner_minimi')
                ->min(0)
                ->max(1)
                ->step(1)
                ->nullable()
                ->help('Punteggio: 0 o 1'),

            Number::make('Cofinanziamento', 'evaluation_base_cofinanziamento')
                ->min(0)
                ->max(1)
                ->step(1)
                ->nullable()
                ->help('Punteggio: 0 o 1'),

            Number::make('Tempistiche', 'evaluation_base_tempistiche')
                ->min(0)
                ->max(1)
                ->step(1)
                ->nullable()
                ->help('Punteggio: 0 o 1'),
        ];
    }

    /**
     * Get fields for qualitative evaluation.
     */
    private function getQualitativeEvaluationFields(): array
    {
        return [
            Number::make('Coerenza CAI', 'evaluation_qual_coerenza_cai')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),

            Number::make('Impatto Ambientale', 'evaluation_qual_imp_ambientale')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),

            Number::make('Impatto Sociale', 'evaluation_qual_imp_sociale')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),

            Number::make('Impatto Economico', 'evaluation_qual_imp_economico')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),

            Number::make('Obiettivi chiari', 'evaluation_qual_obiettivi_chiari')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),

            Number::make('Solidità azioni', 'evaluation_qual_solidita_azioni')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),

            Number::make('Capacità partner', 'evaluation_qual_capacita_partner')
                ->min(0)
                ->max(5)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 5'),
        ];
    }

    /**
     * Get fields for bonus factors.
     */
    private function getBonusFactorsFields(): array
    {
        return [
            Number::make('Innovazione', 'evaluation_prem_innovazione')
                ->min(0)
                ->max(3)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 3'),

            Number::make('Replicabilità', 'evaluation_prem_replicabilita')
                ->min(0)
                ->max(3)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 3'),

            Number::make('Comunità', 'evaluation_prem_comunita')
                ->min(0)
                ->max(3)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 3'),

            Number::make('Sostenibilità', 'evaluation_prem_sostenibilita')
                ->min(0)
                ->max(3)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 3'),
        ];
    }

    /**
     * Get fields for risks.
     */
    private function getRisksFields(): array
    {
        return [
            Number::make('Rischi tecnici', 'evaluation_risk_tecnici')
                ->min(0)
                ->max(3)
                ->step(1)
                ->nullable()
                ->help('Punteggio da 0 a 3'),

            Number::make('Rischi finanziari', 'evaluation_risk_finanziari')
                ->min(-3)
                ->max(3)
                ->step(1)
                ->nullable()
                ->help('Punteggio da -3 a 3'),

            Number::make('Rischi organizzativi', 'evaluation_risk_organizzativi')
                ->min(-2)
                ->max(2)
                ->step(1)
                ->nullable()
                ->help('Punteggio da -2 a 2'),

            Number::make('Rischi logistici', 'evaluation_risk_logistici')
                ->min(-2)
                ->max(2)
                ->step(1)
                ->nullable()
                ->help('Punteggio da -2 a 2'),
        ];
    }

    /**
     * Get fields for summary (read-only totals).
     */
    private function getSummaryFields(): array
    {
        return [
            BelongsTo::make('Valutato da', 'evaluatedBy', User::class)
                ->nullable()
                ->exceptOnForms()
                ->showOnDetail(),

            DateTime::make('Valutato il', 'evaluation_evaluated_at')
                ->nullable()
                ->exceptOnForms()
                ->showOnDetail(),

            Number::make('Totale Positivo', 'evaluation_total_positive')
                ->exceptOnForms()
                ->displayUsing(function ($value) {
                    return $value ?? 0;
                })
                ->help('Somma di tutti i punteggi positivi'),

            Number::make('Totale Negativo', 'evaluation_total_negative')
                ->exceptOnForms()
                ->displayUsing(function ($value) {
                    return $value ?? 0;
                })
                ->help('Somma dei punteggi negativi'),

            Number::make('Totale Complessivo', 'evaluation_total_score')
                ->exceptOnForms()
                ->displayUsing(function ($value) {
                    return $value ?? 0;
                })
                ->help('Totale Positivo - Totale Negativo'),
        ];
    }
}
