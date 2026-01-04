<?php

namespace App\Traits;

use App\Enums\DocumentationCategory;
use Carbon\Carbon;
use App\Models\Epic;
use App\Enums\UserRole;
use App\Models\Project;
use App\Nova\Project as novaProject;
use App\Nova\Tag as novaTag;
use App\Enums\StoryType;
use Manogi\Tiptap\Tiptap;
use App\Enums\StoryStatus;
use App\Enums\StoryPriority;
use App\Models\Documentation;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Textarea;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\Tag;

trait fieldTrait
{

    public $hideFields = [];
    public $tiptapAllButtons = [
        'heading',
        '|',
        'italic',
        'bold',
        '|',
        'link',
        'code',
        'strike',
        'underline',
        'highlight',
        '|',
        'bulletList',
        'orderedList',
        'br',
        'codeBlock',
        'blockquote',
        '|',
        'horizontalRule',
        'hardBreak',
        '|',
        'table',
        '|',
        'image',
        '|',
        'textAlign',
        '|',
        'rtl',
        '|',
        'history',
        '|',
        'editHtml',
    ];

    public function canSee($fieldName)
    {
        return function ($request) use ($fieldName) {
            return !in_array($fieldName, $this->hideFields);
        };
    }

    public function titleField($fieldName = 'name')
    {
        return Text::make(__('Title'), $fieldName)
            ->displayUsing(function ($name, $a, $b) {
                return $this->trimText($name);
            })
            ->sortable()
            ->readonly(function ($request) {
                if ($request->resourceId == null) {
                    return false;
                }
                return $request->user()->hasRole(UserRole::Customer);
            })
            ->required()
            ->help(__('Enter a title for the ticket.'))
            ->asHtml();
    }

    public function trimText($text, $treshold = 50)
    {
        $wrappedText = wordwrap($text, $treshold, "\n", true);
        $htmlText = str_replace("\n", '<br>', $wrappedText);
        return $htmlText;
    }

    public function createdAtField($fieldName = 'created_at')
    {
        return DateTime::make(__('Created At'), $fieldName)
            ->sortable()
            ->displayUsing(function ($createdAt) {
                return Carbon::parse($createdAt)->format('d/m/Y');
            })
            ->canSee($this->canSee($fieldName));
    }

    public function updatedAtField($fieldName = 'updated_at')
    {
        return DateTime::make(__('Updated At'), $fieldName)
            ->sortable()
            ->displayUsing(function ($createdAt) {
                return Carbon::parse($createdAt)->format('d/m/Y H:i');
            })
            ->canSee($this->canSee($fieldName));
    }

    public function historyField()
    {
        return Text::make(__('History'), 'history', function () {
            $history = [];
            
            if ($this->created_at) {
                $history[] = __('Created At') . ': ' . Carbon::parse($this->created_at)->format('d/m/Y H:i');
            }
            
            if ($this->updated_at) {
                $history[] = __('Updated At') . ': ' . Carbon::parse($this->updated_at)->format('d/m/Y H:i');
            }
            
            if ($this->released_at) {
                $history[] = __('Released At') . ': ' . Carbon::parse($this->released_at)->format('d/m/Y H:i');
            }
            
            if ($this->done_at) {
                $history[] = __('Done At') . ': ' . Carbon::parse($this->done_at)->format('d/m/Y H:i');
            }
            
            // Add Effective hours as last row
            $hours = $this->hours ?? 0;
            $history[] = __('Effective Hours') . ': ' . $hours;
            
            return !empty($history) ? implode('<br>', $history) : '-';
        })
            ->asHtml()
            ->canSee($this->canSee('history'));
    }

    public function typeField(NovaRequest $request, $fieldName = 'type')
    {
        $isEdit = $request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest();
        if ($isEdit) {
            return  Select::make(__('Type'), $fieldName)
                ->options(function () {
                    return [
                        StoryType::Feature->value =>  StoryType::Feature,
                        StoryType::Bug->value => StoryType::Bug,
                        StoryType::Helpdesk->value => StoryType::Helpdesk,
                        StoryType::Scrum->value => StoryType::Scrum
                    ];
                })
                ->default(StoryType::Helpdesk->value)
                ->help(__('Assign the type of the ticket.'))
                ->readonly(function ($request) {
                    return $this->type === StoryType::Scrum->value;
                })
                ->canSee(function ($request) {
                    return  !$request->user()->hasRole(UserRole::Customer);
                });
        } else {
            return Text::make(__('Type'), $fieldName, function () {
                $color = 'green';
                if ($this->type === StoryType::Bug->value) {
                    $color = 'red';
                } elseif ($this->type === StoryType::Feature->value) {
                    $color = 'blue'; // Assumendo che 'Feature' debba essere blu
                }

                return <<<HTML
    <span style="color:{$color}; font-weight: bold;">{$this->type}</span>
    HTML;
            })
                ->asHtml()
                ->canSee($this->canSee($fieldName));
        }
    }

    public function priorityField()
    {
        return  Select::make(__('Priority'), 'priority')->options([
            StoryPriority::Low->value => 'Low',
            StoryPriority::Medium->value => 'Medium',
            StoryPriority::High->value => 'High',
        ])
            ->default($this->priority ?? StoryPriority::High->value)
            ->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            });
    }

    public function historyLogField()
    {
        return Text::make(__('Ticket Changes'), 'ticket_changes', function () {
            // Load ALL story logs with user relationship (no limit)
            $storyLogs = $this->resource->storyLogs()->with('user')->limit(null)->get();
            
            if ($storyLogs->isEmpty()) {
                return '<p style="color: #6c757d; font-style: italic;">' . __('No changes recorded yet.') . '</p>';
            }
            
            // Sort by updated_at from changes JSON (descending), then by id desc as fallback
            $storyLogs = $storyLogs->sort(function ($a, $b) {
                // Get updated_at from changes JSON for both logs
                $changesA = is_array($a->changes) ? $a->changes : (json_decode($a->changes, true) ?: []);
                $changesB = is_array($b->changes) ? $b->changes : (json_decode($b->changes, true) ?: []);
                
                $updatedAtA = $changesA['updated_at'] ?? null;
                $updatedAtB = $changesB['updated_at'] ?? null;
                
                // Parse timestamps
                $timestampA = 0;
                $timestampB = 0;
                
                if ($updatedAtA) {
                    try {
                        $timestampA = Carbon::parse($updatedAtA)->timestamp;
                    } catch (\Exception $e) {
                        $timestampA = $a->viewed_at ? Carbon::parse($a->viewed_at)->timestamp : 0;
                    }
                } else {
                    $timestampA = $a->viewed_at ? Carbon::parse($a->viewed_at)->timestamp : 0;
                }
                
                if ($updatedAtB) {
                    try {
                        $timestampB = Carbon::parse($updatedAtB)->timestamp;
                    } catch (\Exception $e) {
                        $timestampB = $b->viewed_at ? Carbon::parse($b->viewed_at)->timestamp : 0;
                    }
                } else {
                    $timestampB = $b->viewed_at ? Carbon::parse($b->viewed_at)->timestamp : 0;
                }
                
                // Compare timestamps (descending)
                if ($timestampB !== $timestampA) {
                    return $timestampB <=> $timestampA;
                }
                
                // If timestamps are equal, sort by id descending
                return $b->id <=> $a->id;
            })->values();
            
            return $this->formatStoryLogs($storyLogs);
        })
            ->onlyOnDetail()
            ->asHtml()
            ->help(__('This field shows the history of changes made to the ticket, including timestamps, user, and descriptions.'));
    }
    
    /**
     * Format story logs from story_logs table
     */
    private function formatStoryLogs($storyLogs): string
    {
        // Preload users for better performance - collect all user IDs from changes
        $userIds = collect();
        foreach ($storyLogs as $log) {
            $changes = is_array($log->changes) ? $log->changes : (json_decode($log->changes, true) ?: []);
            foreach ($changes as $field => $value) {
                if (in_array($field, ['user_id', 'tester_id', 'creator_id']) && is_numeric($value)) {
                    $userIds->push((int) $value);
                }
            }
        }
        
        $users = $userIds->unique()->isNotEmpty() 
            ? \App\Models\User::whereIn('id', $userIds->unique())->get()->keyBy('id') 
            : collect();
        
        $html = '<div style="max-height: 400px; overflow-y: auto;">';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<thead>';
        $html .= '<tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">';
        $html .= '<th style="padding: 8px; text-align: left; font-weight: bold;">' . __('Date/Time') . '</th>';
        $html .= '<th style="padding: 8px; text-align: left; font-weight: bold;">' . __('User') . '</th>';
        $html .= '<th style="padding: 8px; text-align: left; font-weight: bold;">' . __('Changes') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($storyLogs as $log) {
            // Get timestamp from updated_at in changes JSON, fallback to viewed_at
            $timestamp = null;
            $changes = is_array($log->changes) ? $log->changes : (json_decode($log->changes, true) ?: []);
            if (isset($changes['updated_at'])) {
                try {
                    $timestamp = Carbon::parse($changes['updated_at']);
                } catch (\Exception $e) {
                    // Fallback to viewed_at if parsing fails
                    $timestamp = $log->viewed_at ? Carbon::parse($log->viewed_at) : null;
                }
            } else {
                // Use viewed_at as fallback
                $timestamp = $log->viewed_at ? Carbon::parse($log->viewed_at) : null;
            }
            
            $formattedTimestamp = $timestamp ? $timestamp->format('d/m/Y H:i:s') : __('Unknown');
            
            // Get user name
            $userName = $log->user ? $log->user->name : __('Unknown User');
            
            // Format changes description
            $changesDescription = $this->formatChangesDescription($log->changes, $users);
            
            $html .= '<tr style="border-bottom: 1px solid #dee2e6;">';
            $html .= '<td style="padding: 8px; white-space: nowrap;">' . htmlspecialchars($formattedTimestamp) . '</td>';
            $html .= '<td style="padding: 8px;">' . htmlspecialchars($userName) . '</td>';
            $html .= '<td style="padding: 8px;">' . $changesDescription . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Format changes description from JSON changes
     */
    private function formatChangesDescription($changes, $users = null): string
    {
        if (empty($changes)) {
            return '<span style="color: #6c757d; font-style: italic;">' . __('No changes') . '</span>';
        }
        
        // If changes is already an array, use it directly
        if (is_array($changes)) {
            $changesArray = $changes;
        } else {
            // Try to decode JSON
            $decoded = json_decode($changes, true);
            $changesArray = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }
        
        if (empty($changesArray)) {
            return '<span style="color: #6c757d; font-style: italic;">' . __('No changes') . '</span>';
        }
        
        $descriptions = [];
        foreach ($changesArray as $field => $value) {
            $fieldLabel = $this->getFieldLabel($field);
            
            // Format value based on type and field
            if (is_null($value)) {
                $formattedValue = '<em>null</em>';
            } elseif (is_bool($value)) {
                $formattedValue = $value ? __('Yes') : __('No');
            } elseif (is_array($value)) {
                $formattedValue = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (in_array($field, ['user_id', 'tester_id', 'creator_id']) && is_numeric($value) && $users) {
                // Try to get user name for user-related fields
                $user = $users->get((int) $value);
                $formattedValue = $user ? htmlspecialchars($user->name) . ' (ID: ' . $value . ')' : htmlspecialchars((string) $value);
            } elseif ($field === 'status' && !empty($value)) {
                // Format status with translation
                $formattedValue = '<span style="font-weight: bold;">' . htmlspecialchars(__(ucfirst($value))) . '</span>';
            } else {
                $formattedValue = htmlspecialchars((string) $value);
                // Truncate long values
                if (strlen($formattedValue) > 100) {
                    $formattedValue = substr($formattedValue, 0, 100) . '...';
                }
            }
            
            $descriptions[] = '<strong>' . htmlspecialchars($fieldLabel) . ':</strong> ' . $formattedValue;
        }
        
        return implode('<br>', $descriptions);
    }
    
    /**
     * Get human-readable label for field name
     */
    private function getFieldLabel(string $field): string
    {
        $labels = [
            'status' => __('Status'),
            'user_id' => __('Assigned Developer'),
            'tester_id' => __('Tester'),
            'creator_id' => __('Creator'),
            'name' => __('Title'),
            'description' => __('Description'),
            'type' => __('Type'),
            'priority' => __('Priority'),
            'estimated_hours' => __('Estimated Hours'),
            'waiting_reason' => __('Waiting Reason'),
            'problem_reason' => __('Problem Reason'),
            'parent_id' => __('Parent Story'),
            'customer_request' => __('Customer Request'),
        ];
        
        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    public function ChildField(NovaRequest $request)
    {
        return Text::make(__('Childs'), 'childs', function () use ($request) {
            $childStories = $this->childStories;
            $childStoryLink = '';
            foreach ($childStories as $childStory) {
                $app = $this->getAppLink();
                $url = url("/resources/stories/{$childStory->id}");
                $story = <<<HTML
                <a
                    href="{$url}"
                    style="color: green;">
                    {$childStory->id}
                    </a>
                HTML;
                $childStoryLink .= $story . $app . $this->trimText($childStory->name, 30) . '<br>';
            }
            return $childStoryLink ?? '';
        })
            ->asHtml();
    }

    public function parentField(NovaRequest $request)
    {
        return Text::make(__('Parent'), 'parent', function () use ($request) {
            $parentStory = $this->parentStory;
            $parentStoryLink = '';
            if (is_null(($parentStory))) {
                return $parentStoryLink;
            }
            $app = $this->getAppLink();
            $url = url("/resources/stories/{$parentStory->id}");
            $story = <<<HTML
                <a
                    href="{$url}"
                    style="color: green;">
                    {$parentStory->id}
                    </a>
                HTML;
            $parentStoryLink .= $story . $app . $this->trimText($parentStory->name, 30) . '<br>';
            return $parentStoryLink ?? '';
        })
            ->asHtml();
    }

    public function relationshipField(NovaRequest $request)
    {
        return Text::make(__('Relationship'), 'relationship', function () use ($request) {
            // Controllo per la parent story
            if ($this->parentStory) {
                $parentStory = $this->parentStory;
                $parentStoryLink = '';
                if (is_null(($parentStory))) {
                    return $parentStoryLink;
                }
                $app = $this->getAppLink($parentStory->creator);
                $url = url("/resources/stories/{$parentStory->id}");
                $story = <<<HTML
                    <h3 style="color:yellow; font-weight: bold">PARENT:<h3/>
                    <a
                        href="{$url}"
                        style="color: green;">
                        {$parentStory->id}
                        </a>
                    HTML;
                $parentStoryLink .= $story . $app . $this->trimText($parentStory->name, 30) . '<br>';
                return $parentStoryLink ?? '';
            }

            // Controllo per le child stories
            if ($this->childStories->isNotEmpty()) {
                $childStories = $this->childStories;
                $childStoryLink = '';
                $storyHeader = <<<HTML
                <h3  style="color:yellow; font-weight: bold">CHILDS:<h3/>
                HTML;
                foreach ($childStories as $childStory) {
                    $app = $this->getAppLink($childStory->creator);
                    $url = url("/resources/stories/{$childStory->id}");
                    $story = <<<HTML
                    <a
                        href="{$url}"
                        style="color: green;">
                        {$childStory->id}
                        </a>
                    HTML;
                    $childStoryLink .= $story . $app . $this->trimText($childStory->name, 30) . '<br>';
                }
                return $storyHeader . $childStoryLink ?? '';
            }

            // Nessuna parent o child story
            return ' ';
        })->canSee($this->canSee('relationship'))->asHtml();
    }

    public function deadlineField(NovaRequest $request)
    {
        return Text::make(__('Deadlines'), 'deadlines', function () {

            $deadlines = $this->deadlines;
            foreach ($deadlines as $deadline) {
                $dueDate = Carbon::parse($deadline->due_date)->format('Y-m-d');
                $deadlineTitle = $deadline->title ?? '';
                $customerName = isset($deadline->customer) ? $deadline->customer->name : '';
                $deadlineName = $dueDate . '<br/>' . $deadlineTitle . '<br/>' . $customerName;
                $deadlineLink = '<a href="' . url('/') . '/resources/deadlines/' . $deadline->id . '" style="color: green;">' . $deadlineName . '</a>';
            }
            return $deadlineLink ?? '';
        })
            ->canSee($this->canSee('deadlines'))
            ->asHtml();
    }

    /**
     * Definisci un campo Status comune, con personalizzazioni per la vista.
     *
     * @return \Laravel\Nova\Fields\Field
     */
    public function statusField($request, $fieldName = 'status')
    {
        // Il campo status è sempre solo visualizzazione (non editabile)
        // La modifica dello stato avviene solo tramite l'azione ChangeStatus
        return Text::make(__('Status'), 'status', function () {
            $status = $this->status;
            $color = $this->getStatusColor($status);
            $icon = $this->getStatusIcon($status);
            $label = strtoupper(__(ucfirst($status)));
            
            return <<<HTML
                <span style="
                    background-color: {$color}80;
                    color: #374151;
                    font-weight: bold;
                    padding: 4px 12px;
                    border-radius: 12px;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    border: 1px solid {$color};
                    text-transform: uppercase;
                ">
                    <span>{$icon}</span>
                    <span>{$label}</span>
                </span>
            HTML;
        })
        ->asHtml();
    }

    public function assignedToField()
    {
        return BelongsTo::make(__('assigned to'), 'developer', 'App\Nova\User')
            ->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            })
            ->relatableQueryUsing(function (NovaRequest $request, Builder $query) {
                !$query->whereJsonDoesntContain('roles', UserRole::Customer);
            })
            ->nullable();
    }

    public function testedByField()
    {
        return BelongsTo::make(__('tested by'), 'tester', 'App\Nova\User')
            ->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            })
            ->nullable()
            ->relatableQueryUsing(function (NovaRequest $request, Builder $query) {
                !$query->whereJsonDoesntContain('roles', UserRole::Customer);
            });
    }

    public function tagsField($fieldLabel = 'Tags', $fieldName = 'tags')
    {
        $field = Tag::make($fieldLabel, $fieldName, novaTag::class)
            ->withPreview()
            ->help(__('Tags are used both to categorize a ticket and to display documentation in the "Info" section of the customer ticket view.'))
            ->canSee($this->canSee($fieldName));

        // Abilita la creazione inline solo per admin/manager
        // Il bottone viene mostrato solo se l'utente ha il permesso di creare tag
        $field->showCreateRelationButton(function (NovaRequest $request) {
            if ($request->user() == null) {
                return false;
            }
            return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager);
        });

        return $field;
    }

    public function replicatedTagsField($originalStoryId)
    {
        return Text::make(__('Replicated Tags'), 'replicated_tags', function () use ($originalStoryId) {
            $originalStory = \App\Models\Story::find($originalStoryId);
            if (!$originalStory) {
                return 'Original Story ID: ' . $originalStoryId . ' (not found)';
            }
            
            $tags = $originalStory->tags;
            if ($tags->isEmpty()) {
                return 'No tags in original story';
            }
            
            $tagsList = $tags->map(function ($tag) {
                return $tag->name . ' (' . $tag->id . ')';
            })->implode(', ');
            
            return $tagsList;
        })
            ->default(function () use ($originalStoryId) {
                $originalStory = \App\Models\Story::find($originalStoryId);
                if (!$originalStory) {
                    return 'Original Story ID: ' . $originalStoryId . ' (not found)';
                }
                
                $tags = $originalStory->tags;
                if ($tags->isEmpty()) {
                    return 'No tags in original story';
                }
                
                $tagsList = $tags->map(function ($tag) {
                    return $tag->name . ' (' . $tag->id . ')';
                })->implode(', ');
                
                return $tagsList;
            })
            ->help(__('This ticket is being replicated from Story ID: ') . $originalStoryId)
            ->readonly()
            ->canSee($this->canSee('tags'));
    }

    public function projectField($fieldName = 'project')
    {

        return BelongsTo::make(__('Project'), $fieldName, novaProject::class)
            ->default(function ($request) {
                $fromEpic =
                    $request->input('viaResource') === 'epics' ||
                    $request->input('viaResource') === 'new-epics' ||
                    $request->input('viaResource') === 'project-epics' ||
                    $request->input('viaResource') === 'progress-epics' ||
                    $request->input('viaResource') === 'test-epics' ||
                    $request->input('viaResource') === 'done-epics' ||
                    $request->input('viaResource') === 'rejected-epics';
                if ($fromEpic) {
                    $epic = Epic::find($request->input('viaResourceId'));
                    $project = Project::find($epic->project_id);
                    return $project ? $project->id : null;
                }
            })
            ->searchable()
            ->nullable()
            ->canSee($this->canSee($fieldName));
    }

    public function customerRequestField(NovaRequest $request, $fieldName = 'customer_request')
    {
        $customerRequestFieldEdit = Tiptap::make(__('Request'), $fieldName)
            ->buttons(['heading', 'code', 'codeBlock', 'link', 'image', 'history', 'editHtml'])
            ->required();


        if ($request->isCreateOrAttachRequest()) {
            return $customerRequestFieldEdit;
        } else if ($request->isResourceDetailRequest()) {
            return Text::make(__('Request'), $fieldName, function () use ($fieldName) {
                $content = $this->resource->$fieldName ?? '';
                $styledContent = $this->styleLinksInHtml($content);
                return '<div class="story-request-field">' . $styledContent . '</div>';
            })
                ->asHtml()
                ->canSee(function ($request) use ($fieldName) {
                    $creator = $this->resource->creator;
                    return $this->canSee($fieldName) &&  (isset($creator));
                });
        } else {
            $creator = auth()->user();
            if (isset($creator) && !isset($request->resourceId)) {
                return $customerRequestFieldEdit;
            } else {
                return Trix::make(__('Request'), $fieldName)
                    ->readOnly()
                    ->canSee($this->canSee($fieldName));
            }
        }
    }

    public function userActivityField()
    {
        return Text::make(__('User Activity'), function () {
            $activityLogs = \App\Models\UsersStoriesLog::where('story_id', $this->resource->id)
                ->with('user')
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($activityLogs->isEmpty()) {
                return '<p>No activity logged yet.</p>';
            }

            $html = '<div style="max-height: 400px; overflow-y: auto;">';
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<thead>';
            $html .= '<tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">';
            $html .= '<th style="padding: 8px; text-align: left; font-weight: bold;">Date</th>';
            $html .= '<th style="padding: 8px; text-align: left; font-weight: bold;">User</th>';
            $html .= '<th style="padding: 8px; text-align: left; font-weight: bold;">Time Spent</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            foreach ($activityLogs as $log) {
                $hours = floor($log->elapsed_minutes / 60);
                $minutes = $log->elapsed_minutes % 60;
                $timeDisplay = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                
                $html .= '<tr style="border-bottom: 1px solid #dee2e6;">';
                $html .= '<td style="padding: 8px;">' . $log->date->format('d/m/Y') . '</td>';
                $html .= '<td style="padding: 8px;">' . ($log->user ? $log->user->name : 'N/A') . '</td>';
                $html .= '<td style="padding: 8px;">' . $timeDisplay . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';

            return $html;
        })->onlyOnDetail()->asHtml();
    }

    public function answerToTicketField($fieldName = 'answer_to_ticket')
    {
        //TODO make it readonly when the package will be fixed( opened issue on github: https://github.com/manogi/nova-tiptap/issues/76 )
        return  Tiptap::make(__('Answer to ticket'), $fieldName)
            ->canSee(
                function ($request) use ($fieldName) {
                    return  $this->canSee($fieldName) && $request->resourceId !== null && $this->status != StoryStatus::Done->value;
                }
            )
            ->readonly(function ($request) {
                return $request->resourceId !== null && ($this->status == StoryStatus::Done->value);
            })
            ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                if (empty($request[$requestAttribute])) {
                    return;
                }
                $model->addResponse($request[$requestAttribute]);
            })
            ->buttons($this->tiptapAllButtons);
    }

    public function waitingReasonField()
    {
        // In edit, usa Textarea
        return Textarea::make(__('Waiting Reason'), 'waiting_reason')
            ->rows(3)
            ->alwaysShow()
            ->canSee(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest() || 
                       (($request->isResourceDetailRequest() || $request->isResourceIndexRequest()) && 
                        $this->status === StoryStatus::Waiting->value && !empty($this->waiting_reason));
            })
            ->dependsOn(['status'], function (Textarea $field, NovaRequest $request, $formData) {
                // Solo in edit nascondi se lo status non è waiting
                if ($request->isUpdateOrUpdateAttachedRequest() && $formData->status !== StoryStatus::Waiting->value) {
                    $field->hide();
                }
            })
            ->rules(function (NovaRequest $request) {
                // Rendi obbligatorio se lo status è "waiting"
                if ($request->input('status') === StoryStatus::Waiting->value) {
                    return ['required'];
                }
                return [];
            })
            ->help(__('Specify the reason why this ticket is on hold. For example: waiting for customer response, waiting for external information, waiting for third-party system, etc.'))
            ->nullable();
    }

    public function problemReasonField()
    {
        // In edit, usa Textarea
        return Textarea::make(__('Problem Reason'), 'problem_reason')
            ->rows(3)
            ->alwaysShow()
            ->canSee(function ($request) {
                return $request->isUpdateOrUpdateAttachedRequest() || 
                       (($request->isResourceDetailRequest() || $request->isResourceIndexRequest()) && 
                        $this->status === StoryStatus::Problem->value && !empty($this->problem_reason));
            })
            ->dependsOn(['status'], function (Textarea $field, NovaRequest $request, $formData) {
                // Solo in edit nascondi se lo status non è problem
                if ($request->isUpdateOrUpdateAttachedRequest() && $formData->status !== StoryStatus::Problem->value) {
                    $field->hide();
                }
            })
            ->rules(function (NovaRequest $request) {
                // Rendi obbligatorio se lo status è "problem"
                if ($request->input('status') === StoryStatus::Problem->value) {
                    return ['required'];
                }
                return [];
            })
            ->help(__('Specify the problem encountered while working on this ticket. Describe the technical issue, blocker, or error that prevents progress.'))
            ->nullable();
    }

    public function descriptionField()
    {
        return  Tiptap::make(__('Dev notes'), 'description')
            ->hideFromIndex()
            ->buttons($this->tiptapAllButtons)
            ->canSee($this->canSee('description'))
            ->help(__('Provide all the necessary information. You can add images using the "Add Image" option. If you\'d like to include a video, we recommend uploading it to a service like Google Drive, enabling link sharing, and pasting the link here. The more details you provide, the easier it will be for us to resolve the issue.'))
            ->alwaysShow();
    }

    public function infoField(NovaRequest $request, $fieldName = 'info')
    {
        return Text::make(__('Info'), $fieldName, function () use ($request) {
            if ($request->user()->hasRole(UserRole::Customer)) {
                return $this->getCustomerInfo();
            } else {
                return $this->getNonCustomerInfo();
            }
        })
            ->canSee($this->canSee($fieldName))
            ->asHtml();
    }

    public function estimatedHoursFieldCanSee($fieldName)
    {
        return function ($request) use ($fieldName) {
            return ($request->user()->hasRole(UserRole::Developer) || $request->user()->hasRole(UserRole::Admin));
        };
    }

    public function estimatedHoursField(NovaRequest $request, $fieldName = 'estimated_hours')
    {
        if ($request->isResourceDetailRequest() || $request->isResourceIndexRequest()) {
            return Text::make(__('Estimated Hours'), $fieldName, function () {
                $hours = $this->estimated_hours;
                $html = '<span></span>';
                if (isset($hours)) {
                    $html =
                        <<<HTML
                            <span >Estimed Hours: $hours</span>
                        HTML;
                }
                return $html;
            })->asHtml()->canSee($this->estimatedHoursFieldCanSee($fieldName));
        } else {
            return Number::make(__('Estimated Hours'), $fieldName)
                ->sortable()
                ->rules('nullable', 'numeric', 'min:0')
                ->help(__('Enter the estimated time to resolve the ticket in hours.'))
                ->canSee($this->estimatedHoursFieldCanSee($fieldName));
        }
    }

    public function effectiveHoursField(NovaRequest $request, $fieldName = 'hours')
    {
        if ($request->isResourceDetailRequest() || $request->isResourceIndexRequest()) {
            return Text::make(__('Effective Hours'), $fieldName, function () {
                $hours = $this->hours ?? 0;
                return
                    <<<HTML
                        <span >Effective Hours: $hours</span>
                    HTML;
            })->asHtml()->canSee($this->estimatedHoursFieldCanSee($fieldName));
        } else {
            return Number::make(__('Effective Hours'), $fieldName)
                ->sortable()
                ->rules('nullable', 'numeric', 'min:0')
                ->help(__('Enter the effective time to resolve the ticket in hours.'))
                ->canSee($this->estimatedHoursFieldCanSee($fieldName));
        }
    }

    private function getCustomerInfo()
    {

        $tagLinks = $this->getTagLinks(DocumentationCategory::Customer);
        return <<<HTML
            {$tagLinks}
            HTML;
    }

    private function getNonCustomerInfo()
    {
        $appLink = $this->getAppLink();
        $tagLinks = $this->getTagLinks();
        $creatorLink = $this->getCreatorLink();
        $testerLink = $this->getTesterLink();

        return "{$appLink}{$creatorLink}{$testerLink}{$tagLinks}";
    }

    private function getAppLink($creator = null)
    {
        if (is_null($creator)) {
            $creator = $this->resource->creator;
        }
        $app = isset($creator) && isset($creator->apps) && count($creator->apps) > 0 ? $creator->apps[0] : null;

        if ($app) {
            $url = url("/resources/apps/{$app->id}");
            return <<<HTML
            <a
                href="{$url}"
                target="_blank"
                style="color:red; font-weight:bold;">
                App: {$app->name}
            </a> <br>
            HTML;
        }
        return '';
    }

    private function getTagLinks(DocumentationCategory $category = DocumentationCategory::Internal)
    {
        $tags = $this->resource->tags;
        $tags = $tags->filter(function ($tag) use ($category) {
            if ($tag->taggable_type == "Documentation") {
                // Recupera la documentation associata
                $documentation = Documentation::find($tag->taggable_id);
                if ($documentation) {
                    // Se la categoria è Customer, filtra solo per Customer
                    if ($category == DocumentationCategory::Customer) {
                        return $documentation->category == DocumentationCategory::Customer;
                    }

                    // Se la categoria è Internal, mostra sia Internal che Customer
                    if ($category == DocumentationCategory::Internal) {
                        return in_array($documentation->category, [DocumentationCategory::Internal, DocumentationCategory::Customer]);
                    }
                }
            }
            return  $category == DocumentationCategory::Internal;
        });
        $HTML = '';
        if ($tags) {
            foreach ($tags as $tag) {
                $url = $tag->getResourceUrlAttribute();
                $HTML .=    <<<HTML
            <span style="color:orange; font-weight:bold;">Tag:</span> <a
                href="$url"
                target="_blank"
                style="color:orange; font-weight:bold;">
                {$tag->name}
            </a> <br>
            HTML;
            }
            return $HTML;
        }
        return '';
    }

    private function getCreatorLink()
    {
        $creator = $this->resource->creator;
        if ($creator) {
            $url = url("/resources/users/{$creator->id}");
            return <<<HTML
            <a
                href="{$url}"
                target="_blank"
                style="color:chocolate; font-weight:bold;">
                Creator: {$creator->name}
            </a> <br>
            HTML;
        }
        return '';
    }

    private function getTesterLink()
    {
        $tester = $this->resource->tester;
        if ($tester) {
            $url = url("/resources/users/{$tester->id}");
            return <<<HTML
            <a
                href="{$url}"
                target="_blank"
                style="color:darkgreen; font-weight:bold;">
                Tester: {$tester->name}
            </a> <br>
            HTML;
        }
        return '';
    }

    private function getStatusColor($status)
    {
        try {
            $statusEnum = StoryStatus::from($status);
            return $statusEnum->color();
        } catch (\ValueError $e) {
            return 'black';
        }
    }

    private function getStatusIcon($status)
    {
        try {
            $statusEnum = StoryStatus::from($status);
            return $statusEnum->icon();
        } catch (\ValueError $e) {
            return 'question-mark-circle';
        }
    }

    /**
     * Definisce un campo comune per il 'Creatore' con logiche specifiche.
     *
     * @return \Laravel\Nova\Fields\BelongsTo
     */
    public function creatorField()
    {
        $fieldName = 'creator';
        return BelongsTo::make(__('Creator'), $fieldName, 'App\Nova\User')
            ->nullable()
            ->searchable()
            ->default(function ($request) {
                // Only set default if creator_id is not already set
                if ($request->isCreateOrAttachRequest() && !$request->has('creator_id')) {
                    return auth()->user()->id;
                }
                return null;
            })
            ->canSee($this->canSee($fieldName));
    }

    public function getOptions(): array
    {
        $allStatuses = collect(StoryStatus::cases())->mapWithKeys(fn($status) => [
            $status->value => __(ucfirst($status->value)) // Traduzione degli stati
        ]);
        if (!$this->resource->exists) {
            return $allStatuses->toArray();
        }
        return $allStatuses->toArray();
    }


    public function getStatusLabel($statusValue): array
    {
        $statusOptions = collect(StoryStatus::cases())->mapWithKeys(fn($status) => [
            $status->value => $status
        ])->toArray();

        return isset($statusOptions[$statusValue])
            ? [$statusOptions[$statusValue]->value => $statusOptions[$statusValue]]
            : [];
    }

    /**
     * Add inline styles to links in HTML content
     */
    private function styleLinksInHtml($html)
    {
        if (empty($html)) {
            return $html;
        }
        
        // Pattern per trovare tutti i tag <a> con href
        // Cattura anche i link che hanno già attributi style
        $pattern = '/<a\s+([^>]*href=["\']([^"\']*)["\'][^>]*)>(.*?)<\/a>/is';
        
        return preg_replace_callback($pattern, function ($matches) {
            $fullTag = $matches[0];
            $attributes = $matches[1];
            $href = $matches[2];
            $content = $matches[3];
            
            // Se il link ha già uno style, aggiungiamo i nostri stili
            if (preg_match('/style=["\']([^"\']*)["\']/', $attributes, $styleMatches)) {
                $existingStyle = $styleMatches[1];
                // Rimuoviamo lo style esistente e lo sostituiamo
                $attributes = preg_replace('/style=["\'][^"\']*["\']/', '', $attributes);
                $newStyle = 'color: #4099de; text-decoration: underline; font-weight: 500; ' . $existingStyle;
            } else {
                // Aggiungiamo solo i nostri stili
                $newStyle = 'color: #4099de; text-decoration: underline; font-weight: 500;';
            }
            
            return '<a ' . trim($attributes) . ' style="' . $newStyle . '">' . $content . '</a>';
        }, $html);
    }

    /**
     * Create a clickable ID field that opens the detail view
     */
    public function clickableIdField()
    {
        return Text::make(__('ID'), 'id', function () {
            // All Story resources share the same model, so we use 'stories' as the base URI
            $url = url("/resources/stories/{$this->id}");
            return '<a href="' . $url . '" style="color: #2FBDA5; font-weight: bold;">' . $this->id . '</a>';
        })->asHtml();
    }

    /**
     * Create a text field showing the assigned user
     */
    public function assignedUserTextField()
    {
        return Text::make(__('User'), 'assigned_user', function () {
            $user = $this->developer;
            return $user ? $user->name : '-';
        });
    }
}
