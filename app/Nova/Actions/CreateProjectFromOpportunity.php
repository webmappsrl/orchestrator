<?php

namespace App\Nova\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\FundraisingProject;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Number;
use Outl1ne\MultiselectField\Multiselect;
use Laravel\Nova\Http\Requests\NovaRequest;

class CreateProjectFromOpportunity extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Crea Progetto FRP';
    
    /**
     * Indicates if this action is only available on the resource index.
     *
     * @var bool
     */
    public $onlyOnIndex = false;

    /**
     * Indicates if this action is available on the resource's table row.
     *
     * @var bool
     */
    public $showOnTableRow = true;

    /**
     * Indicates if this action is available on the resource's detail view.
     *
     * @var bool
     */
    public $showOnDetail = true;

    /**
     * Indicates if this action should be available inline on the resource's table row.
     *
     * @var bool
     */
    public $showInline = true;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $opportunity = $models->first();
        
        if (!$opportunity) {
            return Action::danger('Nessuna opportunità selezionata.');
        }

        // Crea il progetto FRP
        $projectData = [
            'title' => $fields->title,
            'fundraising_opportunity_id' => $opportunity->id,
            'lead_user_id' => $fields->lead_user_id,
            'responsible_user_id' => $fields->responsible_user_id,
            'status' => 'draft',
        ];

        // Aggiungi campi opzionali solo se presenti
        if (!empty($fields->description)) {
            $projectData['description'] = $fields->description;
        }

        if (!empty($fields->requested_amount)) {
            $projectData['requested_amount'] = $fields->requested_amount;
        }

        $project = FundraisingProject::create($projectData);

        // Se ci sono partner selezionati, li aggiungi
        if (!empty($fields->partners)) {
            $project->partners()->sync($fields->partners);
        }

        return Action::redirect('/resources/fundraising-projects/' . $project->id);
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $customers = User::whereJsonContains('roles', UserRole::Customer)
            ->pluck('name', 'id')
            ->toArray();

        $fundraisingUsers = User::whereJsonContains('roles', UserRole::Fundraising)
            ->pluck('name', 'id')
            ->toArray();

        return [
            Text::make('Titolo del Progetto', 'title')
                ->rules('required', 'max:255')
                ->help('Inserisci un titolo descrittivo per il progetto FRP'),

            Select::make('Capofila', 'lead_user_id')
                ->options($customers)
                ->rules('required')
                ->searchable()
                ->help('Seleziona il customer che sarà il capofila del progetto')
                ->displayUsingLabels(),

            Select::make('Responsabile', 'responsible_user_id')
                ->options($fundraisingUsers)
                ->rules('required')
                ->help('Seleziona l\'utente fundraising responsabile del progetto')
                ->displayUsingLabels(),

            Textarea::make('Idea Progettuale', 'description')
                ->rows(4)
                ->help('Inserisci un abstract del progetto che descriva gli obiettivi, le attività principali e i risultati attesi'),

            Number::make('Importo Richiesto', 'requested_amount')
                ->step(0.01)
                ->help('Inserisci l\'importo che si intende richiedere (opzionale)')
                ->displayUsing(function ($value) {
                    return $value ? '€ ' . number_format($value, 2) : null;
                }),
        ];
    }

    /**
     * Determine if the action is executable for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToSee($request)
    {
        $user = $request->user();
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine if the action is executable for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function authorizedToRun($request, $model)
    {
        $user = $request->user();
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }
}
