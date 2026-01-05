<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use App\Models\StoryLog;
use App\Nova\Actions\UpdateWaitingStoriesAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Khalin\Nova4SearchableBelongsToFilter\NovaSearchableBelongsToFilter;

class WaitingStory extends CustomerStory
{
    public static function label()
    {
        return __('In Attesa');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereNotIn = [StoryStatus::Done->value, StoryStatus::Backlog->value, StoryStatus::Rejected->value];
        $baseQuery = $query->whereNotNull('creator_id')
            ->whereNotIn('status', $whereNotIn)
            ->where('status', StoryStatus::Waiting->value);

        // Order by days in waiting DESC
        // Calculate days in waiting using a subquery based on story_logs
        $waitingStatusValue = StoryStatus::Waiting->value;
        
        // Remove any existing orderBy clauses and apply our custom ordering
        // Only apply ordering if no custom orderBy is requested by Nova
        if (!$request->get('orderBy')) {
            $baseQuery->reorder()
                ->selectRaw('stories.*, COALESCE(
                    EXTRACT(EPOCH FROM (NOW() - (
                        SELECT viewed_at FROM story_logs 
                        WHERE story_id = stories.id 
                        AND changes->>\'status\' = ?
                        ORDER BY viewed_at DESC, id DESC 
                        LIMIT 1
                    ))) / 86400,
                    EXTRACT(EPOCH FROM (NOW() - stories.updated_at)) / 86400
                ) as days_in_waiting', [$waitingStatusValue])
                ->orderByRaw('days_in_waiting DESC NULLS LAST');
        } else {
            // If user has selected a custom sort, still add the column for potential use
            $baseQuery->selectRaw('stories.*, COALESCE(
                EXTRACT(EPOCH FROM (NOW() - (
                    SELECT viewed_at FROM story_logs 
                    WHERE story_id = stories.id 
                    AND changes->>\'status\' = ?
                    ORDER BY viewed_at DESC, id DESC 
                    LIMIT 1
                ))) / 86400,
                EXTRACT(EPOCH FROM (NOW() - stories.updated_at)) / 86400
            ) as days_in_waiting', [$waitingStatusValue]);
        }

        return $baseQuery;
    }

    /**
     * Get the fields displayed on the index listing.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fieldsInIndex(NovaRequest $request)
    {
        $fields = [
            Stack::make(__('MAIN INFO'), [
                $this->clickableIdField(),
                $this->statusField($request),
                $this->assignedUserTextField(),
            ]),
            Stack::make(__('Title'), [
                $this->typeField($request),
                $this->titleField(),
                $this->relationshipField($request),
            ]),
            $this->infoField($request),
            Text::make(__('Ragione dell\'attesa'), 'waiting_reason', function () {
                // Access the underlying model resource
                $story = $this->resource ?? $this;
                
                // Calculate days in Waiting status
                $daysInWaiting = $this->getDaysInWaiting();
                $daysText = $daysInWaiting !== null ? "Giorni di attesa: {$daysInWaiting}<br/>" : '';
                
                $reason = $story->waiting_reason ?? '-';
                if ($reason === '-') {
                    return $daysText ? $daysText . $reason : $reason;
                }
                // Limita a 40 caratteri per riga e inserisci a capo
                $wrappedText = wordwrap($reason, 40, "\n", true);
                $formattedReason = str_replace("\n", '<br>', $wrappedText);
                return $daysText . $formattedReason;
            })->asHtml()->onlyOnIndex(),
            $this->historyField(),
            $this->deadlineField($request),
        ];

        return array_map(function ($field) {
            return $field->onlyOnIndex();
        }, $fields);
    }

    /**
     * Get the number of days the story has been in Waiting status
     *
     * @return int|null
     */
    private function getDaysInWaiting(): ?int
    {
        // Access the underlying model resource
        $story = $this->resource ?? $this;
        $storyId = $story->id ?? null;
        
        if (!$storyId) {
            return null;
        }

        // Find the most recent log entry where status changed to Waiting
        $waitingLog = StoryLog::where('story_id', $storyId)
            ->where('changes->status', StoryStatus::Waiting->value)
            ->orderBy('viewed_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($waitingLog && $waitingLog->viewed_at) {
            return Carbon::parse($waitingLog->viewed_at)->diffInDays(now());
        }

        // Fallback: if no log found, use updated_at (less reliable)
        $updatedAt = $story->updated_at ?? null;
        if ($updatedAt) {
            return Carbon::parse($updatedAt)->diffInDays(now());
        }

        return null;
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        $actions = parent::actions($request);
        
        // Add our custom action for updating waiting stories
        $actions[] = new UpdateWaitingStoriesAction();
        
        return $actions;
    }

    /**
     * Get the actions available for the index.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actionsForIndex(NovaRequest $request)
    {
        return [
            new UpdateWaitingStoriesAction(),
        ];
    }
}

