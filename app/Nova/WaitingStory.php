<?php

namespace App\Nova;

use App\Enums\StoryStatus;
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
        return $query->whereNotNull('creator_id')
            ->whereNotIn('status', $whereNotIn)
            ->where('status', StoryStatus::Waiting->value);
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
                $reason = $this->waiting_reason ?? '-';
                if ($reason === '-') {
                    return $reason;
                }
                // Limita a 40 caratteri per riga e inserisci a capo
                $wrappedText = wordwrap($reason, 40, "\n", true);
                return str_replace("\n", '<br>', $wrappedText);
            })->asHtml()->onlyOnIndex(),
            $this->historyField(),
            $this->deadlineField($request),
        ];

        return array_map(function ($field) {
            return $field->onlyOnIndex();
        }, $fields);
    }
}

