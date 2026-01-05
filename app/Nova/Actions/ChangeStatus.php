<?php

namespace App\Nova\Actions;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\StoryLog;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
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
     * @param \App\Models\Story|null $model
     * @return array
     */
    private function getAvailableStatuses($currentStatus, $model = null)
    {
        $availableStatuses = [];

        // Se lo stato corrente è PROBLEM o WAITING, recupera solo lo stato precedente da story_logs
        if (in_array($currentStatus, [StoryStatus::Problem->value, StoryStatus::Waiting->value]) && $model) {
            $previousStatus = $this->getPreviousStatusFromLogs($model);
            if ($previousStatus) {
                return [StoryStatus::from($previousStatus)];
            }
        }

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
     * Get resource IDs from Nova request (handles both single and bulk actions)
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array|null
     */
    private function getResourceIdsFromRequest(NovaRequest $request)
    {
        // Se è un'azione su un singolo elemento (detail view)
        if ($request->resourceId) {
            return [$request->resourceId];
        }
        
        // Prova diversi modi per ottenere i resource IDs per bulk actions
        $resourceIds = null;
        
        // Metodo 1: dalla proprietà resources
        if ($request->resources) {
            $resourceIds = is_string($request->resources) ? explode(',', $request->resources) : $request->resources;
        }
        // Metodo 2: dalla query string
        elseif ($request->query('resources')) {
            $resources = $request->query('resources');
            if (is_string($resources)) {
                // Potrebbe essere JSON o comma-separated
                $decoded = json_decode($resources, true);
                $resourceIds = $decoded !== null ? $decoded : explode(',', $resources);
            } else {
                $resourceIds = $resources;
            }
        }
        // Metodo 3: dalla query string come array
        elseif ($request->has('resources')) {
            $resources = $request->input('resources');
            if (is_string($resources)) {
                $decoded = json_decode($resources, true);
                $resourceIds = $decoded !== null ? $decoded : explode(',', $resources);
            } else {
                $resourceIds = $resources;
            }
        }
        
        return $resourceIds && !empty($resourceIds) ? (is_array($resourceIds) ? $resourceIds : [$resourceIds]) : null;
    }

    /**
     * Get the previous status from story_logs before the ticket was set to PROBLEM or WAITING
     *
     * @param \App\Models\Story $model
     * @return string|null
     */
    private function getPreviousStatusFromLogs($model)
    {
        // Cerca il log più recente dove lo stato è cambiato a PROBLEM o WAITING
        $statusChangeLog = StoryLog::where('story_id', $model->id)
            ->where(function ($query) {
                $query->where('changes->status', StoryStatus::Problem->value)
                    ->orWhere('changes->status', StoryStatus::Waiting->value);
            })
            ->orderBy('viewed_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$statusChangeLog) {
            return null;
        }

        // Cerca tutti i log precedenti a quello che ha cambiato lo stato a PROBLEM/WAITING
        // ordinati per data decrescente
        $allLogs = StoryLog::where('story_id', $model->id)
            ->where(function ($query) use ($statusChangeLog) {
                $query->where('viewed_at', '<', $statusChangeLog->viewed_at)
                    ->orWhere(function ($q) use ($statusChangeLog) {
                        $q->where('viewed_at', '=', $statusChangeLog->viewed_at)
                            ->where('id', '<', $statusChangeLog->id);
                    });
            })
            ->orderBy('viewed_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Cerca il primo log che ha uno stato diverso da PROBLEM/WAITING
        foreach ($allLogs as $log) {
            if (isset($log->changes['status'])) {
                $logStatus = $log->changes['status'];
                if (!in_array($logStatus, [StoryStatus::Problem->value, StoryStatus::Waiting->value])) {
                    return $logStatus;
                }
            }
        }

        return null;
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
        
        // Recupera i resource IDs dalla request
        $resourceIds = $this->getResourceIdsFromRequest($request);
        
        if ($resourceIds && !empty($resourceIds)) {
            // Prendi il primo elemento per determinare lo stato corrente
            $model = \App\Models\Story::find($resourceIds[0]);
            if ($model) {
                $currentStatus = $model->status;
            }
        }

        // Ottieni gli stati disponibili in base allo stato corrente
        $availableStatuses = $this->getAvailableStatuses($currentStatus ?? StoryStatus::New->value, $model);

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
                    $resourceIds = $this->getResourceIdsFromRequest($request);
                    if ($resourceIds && !empty($resourceIds)) {
                        $model = \App\Models\Story::find($resourceIds[0]);
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
                        $resourceIds = $this->getResourceIdsFromRequest($request);
                        if ($resourceIds && !empty($resourceIds)) {
                            $model = \App\Models\Story::find($resourceIds[0]);
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
     * Determine if the action is visible for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToSee($request)
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        // Admin, Manager e Developer possono vedere l'azione
        return $user->hasRole(UserRole::Admin) 
            || $user->hasRole(UserRole::Manager) 
            || $user->hasRole(UserRole::Developer);
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
        $user = $request->user();
        if (!$user) {
            return false;
        }

        // Se non è un'istanza di Story, non procedere
        if (!$model instanceof Story) {
            return false;
        }

        // Admin e Manager possono modificare tutti i ticket
        if ($user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager)) {
            // Continua con le altre verifiche
        }
        // Developer può modificare solo i ticket assegnati o di cui è tester
        elseif ($user->hasRole(UserRole::Developer)) {
            if ($model->user_id !== $user->id && $model->tester_id !== $user->id) {
                return false;
            }
        }
        // Altri ruoli non possono modificare
        else {
            return false;
        }

        // Ottieni lo stato corrente
        $currentStatus = $model->status ?? null;
        
        // Se lo stato è Done o Rejected, non permettere transizioni
        if (in_array($currentStatus, [StoryStatus::Done->value, StoryStatus::Rejected->value])) {
            return false;
        }

        // Verifica che ci siano stati disponibili
        $availableStatuses = $this->getAvailableStatuses($currentStatus ?? StoryStatus::New->value, $model);
        return !empty($availableStatuses);
    }
}

