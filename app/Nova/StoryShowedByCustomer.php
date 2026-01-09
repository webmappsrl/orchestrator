<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Stack;

class StoryShowedByCustomer extends Story
{

    public $hideFields = ['description', 'deadlines', 'updated_at', 'project', 'creator', 'developer', 'relationship', 'tags'];

    public static function label()
    {
        return __('my stories');
    }
    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereNotIn = [StoryStatus::Done->value, StoryStatus::Rejected->value];

        if ($request->user()->hasRole(UserRole::Customer)) {
            return $query
                ->where('creator_id', $request->user()->id)
                ->whereNotIn('status', $whereNotIn);
        }
    }
    public  function fieldsInIndex(NovaRequest $request)
    {
        $fields = [
            Stack::make(__('MAIN INFO'), [
                $this->clickableIdField(),
                $this->statusField($request),
                $this->assignedUserTextField(),
            ]),
            $this->typeField($request),
            $this->infoField($request),
            $this->titleField(),
            $this->relationshipField($request),
            $this->estimatedHoursField($request),
            $this->historyField(),
            $this->deadlineField($request),

        ];
        return array_map(function ($field) {
            return $field->onlyOnIndex();
        }, $fields);
    }
    public function statusField($view, $fieldName = 'status')
    {
        return  parent::statusField($view)->readonly(function ($request) {
            return  $this->resource->status !== StoryStatus::Released->value;
        });
    }

    public function getOptions(): array
    {
        if (!$this->resource || $this->resource->status == null) {
            $statusValue = StoryStatus::New->value;
        } else {
            $statusValue = $this->resource->status;
        }
        
        $statusLabel = $this->getStatusLabel($statusValue);
        $storyStatusOptions = [
            StoryStatus::Done->value => StoryStatus::Done,
            ...(is_array($statusLabel) ? $statusLabel : [])
        ];

        return $storyStatusOptions;
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
            new filters\StoryStatusFilter,
            new filters\StoryTypeFilter,
        ];
    }


    public function cards(NovaRequest $request)
    {
        return parent::cards($request);
    }

    /**
     * Determine if the current user can create new resources.
     */
    public static function authorizedToCreate(\Illuminate\Http\Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        return $user->hasRole(UserRole::Customer);
    }

    /**
     * Determine if the current user can view any resources.
     */
    public static function authorizedToViewAny(\Illuminate\Http\Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        return $user->hasRole(UserRole::Customer);
    }

    /**
     * Determine if the current user can view the given resource.
     */
    public function authorizedToView(\Illuminate\Http\Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        
        // Customer può vedere solo le storie di cui è creator (usando creator_id, non user_id)
        if ($user->hasRole(UserRole::Customer)) {
            $story = null;
            $resourceId = null;
            
            // Prova a ottenere la story da $this->resource
            if ($this->resource && isset($this->resource->creator_id)) {
                $story = $this->resource;
            }
            // Altrimenti, prova a caricare il modello dal request
            else {
                // Prova diversi modi per ottenere l'ID
                if ($request instanceof NovaRequest) {
                    $resourceId = $request->resourceId ?? $request->route('resourceId');
                }
                
                // Se non trovato, prova a estrarre dalla route
                if (!$resourceId && $request->route()) {
                    $resourceId = $request->route('resourceId') ?? $request->route('id');
                }
                
                // Se ancora non trovato, prova a estrarre dal path
                if (!$resourceId) {
                    $path = $request->path();
                    if (preg_match('#/story-showed-by-customers/(\d+)#', $path, $matches)) {
                        $resourceId = $matches[1];
                    }
                }
                
                if ($resourceId) {
                    $story = static::$model::find($resourceId);
                }
            }
            
            // Verifica che la story esista e che il creator_id corrisponda all'utente
            if ($story) {
                return $story->creator_id === $user->id;
            }
            
            return false;
        }
        
        // Admin, Manager, Developer possono vedere tutte le storie
        return $user->hasRole(UserRole::Admin) 
            || $user->hasRole(UserRole::Manager) 
            || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine if the current user can update the given resource.
     */
    public function authorizedToUpdate(\Illuminate\Http\Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        
        // Customer può aggiornare solo le storie di cui è creator (usando creator_id, non user_id)
        if ($user->hasRole(UserRole::Customer)) {
            $story = null;
            
            // Prova a ottenere la story da $this->resource
            if ($this->resource && isset($this->resource->creator_id)) {
                $story = $this->resource;
            }
            // Altrimenti, prova a caricare il modello dal request
            elseif ($request instanceof NovaRequest) {
                $resourceId = $request->resourceId ?? $request->route('resourceId');
                if ($resourceId) {
                    $story = static::$model::find($resourceId);
                }
            }
            
            // Verifica che la story esista e che il creator_id corrisponda all'utente
            if ($story) {
                return $story->creator_id === $user->id;
            }
            
            return false;
        }
        
        // Admin, Manager, Developer possono aggiornare (logica dalla classe parent)
        return parent::authorizedToUpdate($request);
    }
}
