<?php

namespace App\Nova\Actions;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class ChangeStatus extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Change Status';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $status = $fields->status;
        $waitingReason = $fields->waiting_reason ?? null;
        $problemReason = $fields->problem_reason ?? null;
        $developerId = $fields->developer_id ?? null;
        $testerId = $fields->tester_id ?? null;
        $testFailureReason = $fields->test_failure_reason ?? null;

        foreach ($models as $model) {
            // Salva lo stato precedente
            $previousStatus = $model->status;

            // Verifica che il developer sia assegnato quando lo stato è "assigned"
            if ($status === StoryStatus::Assigned->value && empty($developerId)) {
                return Action::danger('E\' obbligatorio assegnare un developer');
            }

            // Verifica che il tester sia assegnato prima di passare a "testing"
            if ($status === StoryStatus::Test->value && empty($testerId)) {
                return Action::danger('Impossibile cambiare lo stato a "Da testare" senza avere assegnato un tester.');
            }

            // Verifica che waiting_reason sia valorizzato quando lo stato è "waiting"
            if ($status === StoryStatus::Waiting->value && empty($waitingReason)) {
                return Action::danger('Impossibile cambiare lo stato a "In attesa" senza specificare il motivo dell\'attesa.');
            }

            // Verifica che problem_reason sia valorizzato quando lo stato è "problem"
            if ($status === StoryStatus::Problem->value && empty($problemReason)) {
                return Action::danger('Impossibile cambiare lo stato a "Problema" senza specificare la descrizione del problema.');
            }

            // Verifica che test_failure_reason sia valorizzato quando si passa da Test a Todo
            if ($status === StoryStatus::Todo->value && $previousStatus === StoryStatus::Test->value && empty($testFailureReason)) {
                return Action::danger('Impossibile cambiare lo stato da "Da testare" a "Da fare" senza specificare la ragione del fallimento del test.');
            }

            // Prepara i dati per l'aggiornamento
            $updateData = ['status' => $status];

            // Aggiungi developer_id se presente quando lo stato è "assigned"
            if ($status === StoryStatus::Assigned->value && $developerId) {
                $updateData['user_id'] = $developerId;
            }

            // Aggiungi tester_id se presente quando lo stato è "test"
            if ($status === StoryStatus::Test->value && $testerId) {
                $updateData['tester_id'] = $testerId;
            }

            // Aggiungi waiting_reason se presente
            if ($status === StoryStatus::Waiting->value && $waitingReason) {
                $updateData['waiting_reason'] = $waitingReason;
            }

            // Aggiungi problem_reason se presente
            if ($status === StoryStatus::Problem->value && $problemReason) {
                $updateData['problem_reason'] = $problemReason;
            }

            // Se si passa da Test a Todo, aggiungi la nota nel campo description
            if ($status === StoryStatus::Todo->value && $previousStatus === StoryStatus::Test->value && $testFailureReason) {
                $tester = $model->tester;
                $testerName = $tester ? $tester->name : 'N/A';
                $dateTime = now()->format('d/m/Y H:i');
                
                $failureNote = "TEST FALLITO / {$dateTime} / {$testerName}. {$testFailureReason}";
                
                // Aggiungi la nota in cima alle note di sviluppo esistenti
                $existingDescription = $model->description ?? '';
                $updateData['description'] = $existingDescription 
                    ? $failureNote . "\n\n" . $existingDescription
                    : $failureNote;
            }

            $model->update($updateData);
        }

        $statusEnum = StoryStatus::from($status);
        $statusLabel = $statusEnum->icon() . ' ' . $statusEnum->name;
        return Action::message("Status cambiato correttamente a: {$statusLabel}");
    }

    /**
     * Get available status transitions based on current status
     *
     * @param string $currentStatus
     * @return array
     */
    private function getAvailableStatuses($currentStatus)
    {
        $availableStatuses = [];

        switch ($currentStatus) {
            case StoryStatus::New->value:
                $availableStatuses = [
                    StoryStatus::Assigned,
                    StoryStatus::Backlog,
                    StoryStatus::Rejected,
                    StoryStatus::Problem,
                    StoryStatus::Waiting,
                ];
                break;

            case StoryStatus::Backlog->value:
                $availableStatuses = [
                    StoryStatus::Assigned,
                    StoryStatus::Problem,
                    StoryStatus::Waiting,
                ];
                break;

            case StoryStatus::Assigned->value:
                $availableStatuses = [
                    StoryStatus::Todo,
                    StoryStatus::Problem,
                    StoryStatus::Waiting,
                ];
                break;

            case StoryStatus::Todo->value:
                $availableStatuses = [
                    StoryStatus::Progress,
                    StoryStatus::Problem,
                    StoryStatus::Waiting,
                ];
                break;

            case StoryStatus::Progress->value:
                $availableStatuses = [
                    StoryStatus::Test,
                    StoryStatus::Released,
                    StoryStatus::Todo,
                    StoryStatus::Problem,
                    StoryStatus::Waiting,
                ];
                break;

            case StoryStatus::Test->value:
                $availableStatuses = [
                    StoryStatus::Tested,
                    StoryStatus::Released,
                    StoryStatus::Todo,
                ];
                break;

            case StoryStatus::Tested->value:
                $availableStatuses = [
                    StoryStatus::Released,
                ];
                break;

            case StoryStatus::Released->value:
                $availableStatuses = [
                    StoryStatus::Done,
                ];
                break;

            case StoryStatus::Waiting->value:
                // Da Waiting si può tornare solo agli stati da cui si può passare a Waiting
                $availableStatuses = [
                    StoryStatus::New,
                    StoryStatus::Backlog,
                    StoryStatus::Assigned,
                    StoryStatus::Todo,
                    StoryStatus::Progress,
                ];
                break;

            case StoryStatus::Problem->value:
                // Da Problem si può tornare solo agli stati da cui si può passare a Problem
                $availableStatuses = [
                    StoryStatus::New,
                    StoryStatus::Backlog,
                    StoryStatus::Assigned,
                    StoryStatus::Todo,
                    StoryStatus::Progress,
                ];
                break;

            case StoryStatus::Done->value:
            case StoryStatus::Rejected->value:
                // Stati finali: nessuna transizione possibile
                $availableStatuses = [];
                break;

            default:
                // Fallback: mostra tutti gli stati
                $availableStatuses = StoryStatus::cases();
                break;
        }

        return $availableStatuses;
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        // Ottieni lo stato corrente del ticket
        $currentStatus = null;
        $model = null;
        
        // Se è un'azione su un singolo elemento (detail view)
        if ($request->resourceId) {
            $model = \App\Models\Story::find($request->resourceId);
            if ($model) {
                $currentStatus = $model->status;
            }
        }
        // Se è un'azione bulk (index view), prendi il primo modello
        elseif ($request->resources) {
            $resourceIds = is_string($request->resources) ? explode(',', $request->resources) : $request->resources;
            if (!empty($resourceIds)) {
                $model = \App\Models\Story::find($resourceIds[0]);
                if ($model) {
                    $currentStatus = $model->status;
                }
            }
        }

        // Ottieni gli stati disponibili in base allo stato corrente
        $availableStatuses = $this->getAvailableStatuses($currentStatus ?? StoryStatus::New->value);

        // Crea un array di opzioni per il dropdown degli stati
        $statusOptions = [];
        foreach ($availableStatuses as $status) {
            $statusOptions[$status->value] = $status->icon() . ' ' . $status->name;
        }

        return [
            Select::make(__('Status'), 'status')
                ->options($statusOptions)
                ->required()
                ->rules('required')
                ->help(__('Seleziona il nuovo stato per il ticket')),

            Select::make(__('Developer'), 'developer_id')
                ->options(function () {
                    return User::whereJsonContains('roles', UserRole::Developer->value)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->searchable()
                ->help(__('Seleziona lo sviluppatore a cui assegnare il ticket'))
                ->dependsOn(['status'], function (Select $field, NovaRequest $request, $formData) {
                    if (!isset($formData->status) || $formData->status !== StoryStatus::Assigned->value) {
                        $field->hide();
                    }
                })
                ->rules(function (NovaRequest $request) {
                    if ($request->input('status') === StoryStatus::Assigned->value) {
                        return ['required'];
                    }
                    return [];
                }),

            Select::make(__('Tester'), 'tester_id')
                ->options(function () {
                    return User::whereJsonDoesntContain('roles', UserRole::Customer->value)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->searchable()
                ->default(function () use ($model) {
                    return $model && $model->tester_id ? $model->tester_id : null;
                })
                ->help(__('Seleziona il tester a cui assegnare il ticket'))
                ->dependsOn(['status'], function (Select $field, NovaRequest $request, $formData) {
                    if (!isset($formData->status) || $formData->status !== StoryStatus::Test->value) {
                        $field->hide();
                    }
                })
                ->rules(function (NovaRequest $request) {
                    if ($request->input('status') === StoryStatus::Test->value) {
                        return ['required'];
                    }
                    return [];
                }),

            Textarea::make(__('Waiting Reason'), 'waiting_reason')
                ->help(__('Specifica il motivo dell\'attesa'))
                ->dependsOn(['status'], function (Textarea $field, NovaRequest $request, $formData) {
                    if (!isset($formData->status) || $formData->status !== StoryStatus::Waiting->value) {
                        $field->hide();
                    }
                })
                ->rules(function (NovaRequest $request) {
                    if ($request->input('status') === StoryStatus::Waiting->value) {
                        return ['required'];
                    }
                    return [];
                }),

            Textarea::make(__('Problem Reason'), 'problem_reason')
                ->help(__('Specifica la descrizione del problema'))
                ->dependsOn(['status'], function (Textarea $field, NovaRequest $request, $formData) {
                    if (!isset($formData->status) || $formData->status !== StoryStatus::Problem->value) {
                        $field->hide();
                    }
                })
                ->rules(function (NovaRequest $request) {
                    if ($request->input('status') === StoryStatus::Problem->value) {
                        return ['required'];
                    }
                    return [];
                }),

            Textarea::make(__('Ragione del fallimento del test'), 'test_failure_reason')
                ->help(__('Specifica la ragione del fallimento del test'))
                ->dependsOn(['status'], function (Textarea $field, NovaRequest $request, $formData) {
                    // Mostra solo se lo stato corrente è Test E si sta selezionando Todo
                    $newStatus = $formData->status ?? null;
                    
                    // Recupera il modello per verificare lo stato corrente
                    $model = null;
                    if ($request->resourceId) {
                        $model = \App\Models\Story::find($request->resourceId);
                    } elseif ($request->resources) {
                        $resourceIds = is_string($request->resources) ? explode(',', $request->resources) : $request->resources;
                        if (!empty($resourceIds)) {
                            $model = \App\Models\Story::find($resourceIds[0]);
                        }
                    }
                    
                    $currentStatus = $model ? $model->status : null;
                    
                    if ($newStatus !== StoryStatus::Todo->value || $currentStatus !== StoryStatus::Test->value) {
                        $field->hide();
                    }
                })
                ->rules(function (NovaRequest $request) {
                    // Verifica se lo stato corrente è Test e quello nuovo è Todo
                    $newStatus = $request->input('status');
                    if ($newStatus === StoryStatus::Todo->value) {
                        // Recupera il modello per verificare lo stato corrente
                        $model = null;
                        if ($request->resourceId) {
                            $model = \App\Models\Story::find($request->resourceId);
                        } elseif ($request->resources) {
                            $resourceIds = is_string($request->resources) ? explode(',', $request->resources) : $request->resources;
                            if (!empty($resourceIds)) {
                                $model = \App\Models\Story::find($resourceIds[0]);
                            }
                        }
                        
                        if ($model && $model->status === StoryStatus::Test->value) {
                            return ['required'];
                        }
                    }
                    return [];
                }),
        ];
    }

    /**
     * Get the displayable name of the action.
     *
     * @return string
     */
    public function name()
    {
        return __('Change Status');
    }

    /**
     * Determine if the action is executable for the given request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function authorizedToRun($request, $model)
    {
        // Ottieni lo stato corrente
        $currentStatus = $model->status ?? null;
        
        // Se lo stato è Done o Rejected, non permettere transizioni
        if (in_array($currentStatus, [StoryStatus::Done->value, StoryStatus::Rejected->value])) {
            return false;
        }

        // Verifica che ci siano stati disponibili
        $availableStatuses = $this->getAvailableStatuses($currentStatus ?? StoryStatus::New->value);
        return !empty($availableStatuses);
    }
}

