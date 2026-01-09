<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Models\Tag;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;

class StoryShowedByCustomer extends Story
{

    public $hideFields = ['description', 'deadlines', 'updated_at', 'project', 'creator', 'developer', 'relationship'];

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
            $this->singleTagField(),

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
     * Customer non può modificare le storie, solo visualizzarle.
     */
    public function authorizedToUpdate(\Illuminate\Http\Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        
        // Customer non può modificare le storie
        if ($user->hasRole(UserRole::Customer)) {
            return false;
        }
        
        // Admin, Manager, Developer possono aggiornare (logica dalla classe parent)
        return parent::authorizedToUpdate($request);
    }

    /**
     * Handle any post-creation processing.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public static function afterCreate(NovaRequest $request, $model)
    {
        // Gestisci il tag singolo dopo la creazione
        $tagId = $request->input('single_tag');
        if ($tagId !== null && $tagId !== '') {
            $tagId = (int) $tagId;
            $tagExists = \App\Models\Tag::find($tagId);
            if ($tagExists) {
                $model->tags()->sync([$tagId]);
            }
        }
    }

    /**
     * Handle any post-update processing.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public static function afterUpdate(NovaRequest $request, $model)
    {
        // Gestisci il tag singolo dopo l'aggiornamento
        $tagId = $request->input('single_tag');
        if ($tagId !== null && $tagId !== '') {
            $tagId = (int) $tagId;
            $tagExists = \App\Models\Tag::find($tagId);
            if ($tagExists) {
                $model->tags()->sync([$tagId]);
            }
        } else {
            // Se il campo è vuoto, rimuovi tutti i tag
            $model->tags()->sync([]);
        }
    }

    /**
     * Campo tag personalizzato per questa risorsa: select singola invece di many-to-many
     */
    public function singleTagField()
    {
        return Select::make(__('Tag'), 'single_tag')
            ->options(function () {
                return Tag::orderBy('name')->pluck('name', 'id')->toArray();
            })
            ->nullable()
            ->displayUsing(function ($value, $resource) {
                // Per la visualizzazione, mostra il primo tag associato
                $tag = $resource->tags->first();
                return $tag ? $tag->name : null;
            })
            ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                // Non fare nulla qui - gestiamo il salvataggio in afterCreate/afterUpdate
                // per evitare che il campo venga incluso negli attributi del modello
            })
            ->resolveUsing(function ($value, $resource) {
                // Restituisci l'ID del primo tag per la select
                $tag = $resource->tags->first();
                return $tag ? $tag->id : null;
            })
            ->help(__('Select a single tag to categorize this ticket.'));
    }

    /**
     * Sovrascrivi fieldsInEdit per usare il campo tag personalizzato
     */
    public function fieldsInEdit(NovaRequest $request)
    {
        $fields = [
            ID::make()->sortable(),
            $this->titleField(),
            $this->waitingReasonField(),
            $this->problemReasonField(),
            $this->creatorField(),
            $this->assignedToField(),
            $this->testedByField(),
            $this->singleTagField(),
        ];
        
        $fields = array_merge($fields, [
            $this->typeField($request),
            $this->descriptionField(),
            \Ebess\AdvancedNovaMediaLibrary\Fields\Files::make('Documents', 'documents')
                ->singleMediaRules(static::getDocumentsMimetypesRule())
                ->help(static::getDocumentsHelpText()),
            $this->estimatedHoursField($request),
            $this->customerRequestField($request)
                ->help(
                    $request->resourceId
                        ? null
                        : __('Enter all the necessary information, such as the ID of the content you want to verify. You can insert images via `Add Image`. If you also want to send us a video, we recommend uploading it to a service like Google Drive, enabling link sharing, and inserting the link here. The more details you provide, the easier it will be for us to resolve the issue.')
                ),
            $this->answerToTicketField()
                ->help(
                    $request->resourceId
                        ? __('Enter all the necessary information, such as the ID of the content you want to verify. You can insert images via `Add Image`. If you also want to send us a video, we recommend uploading it to a service like Google Drive, enabling link sharing, and inserting the link here. The more details you provide, the easier it will be for us to resolve the issue.')
                        : null
                ),
            \Laravel\Nova\Fields\BelongsTo::make(__('Parent Story'), 'parentStory', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return ! $request->user()->hasRole(UserRole::Customer);
                })
                ->help(__('Here you can attach the ticket that has the same issue. If multiple tickets share the same issue, attach the main ticket to all related tickets. You can find the main ticket by searching for its title. It is important to note that when the main ticket status changes, the status of all related tickets will also be updated.')),
        ]);

        return array_map(function ($field) {
            return $field->onlyOnForms();
        }, $fields);
    }

    /**
     * Sovrascrivi fieldsInDetails per usare il campo tag personalizzato
     */
    public function fieldsInDetails(NovaRequest $request)
    {
        $fields = [
            ID::make()->sortable(),
            $this->typeField($request),
            $this->statusField($request),
            $this->waitingReasonField(),
            $this->problemReasonField(),
            $this->creatorField(),
            $this->assignedToField(),
            $this->testedByField(),
            $this->infoField($request),
            $this->estimatedHoursField($request),
            $this->deadlineField($request),
            Text::make(__('Tag'), 'single_tag', function () {
                $tag = $this->resource->tags->first();
                return $tag ? $tag->name : '-';
            }),
            \Ebess\AdvancedNovaMediaLibrary\Fields\Files::make(__('Documents'), 'documents')
                ->singleMediaRules(static::getDocumentsMimetypesRule())
                ->help(static::getDocumentsHelpText())
                ->onlyOnDetail(),
            $this->descriptionField(),
            $this->titleField(),
            $this->customerRequestField($request),
            
            // Ticket history and activities panel
            \Laravel\Nova\Panel::make(__('Ticket history and activities'), [
                $this->historyLogField()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return ! $request->user()->hasRole(UserRole::Customer);
                }),
                $this->userActivityField()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return ! $request->user()->hasRole(UserRole::Customer);
                }),
            ])->collapsible(),
            
            \Laravel\Nova\Fields\BelongsToMany::make(__('Child Stories'), 'childStories', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return empty($this->parent_id) && ! $request->user()->hasRole(UserRole::Customer);
                })->filterable(),
            \Laravel\Nova\Fields\BelongsTo::make(__('Parent Story'), 'parentStory', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return ! $request->user()->hasRole(UserRole::Customer);
                }),
            \Laravel\Nova\Fields\MorphToMany::make(__('Deadlines'), 'deadlines', \App\Nova\Deadline::class)
                ->showCreateRelationButton(),

        ];

        return array_map(function ($field) {
            // Panel doesn't support onlyOnDetail(), so skip it
            if ($field instanceof \Laravel\Nova\Panel) {
                return $field;
            }
            return $field->onlyOnDetail();
        }, $fields);
    }
}
