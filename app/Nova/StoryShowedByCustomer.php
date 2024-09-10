<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\ID;

class StoryShowedByCustomer extends Story
{

    public $hideFields = ['description', 'deadlines', 'updated_at', 'project', 'creator', 'developer', 'relationship', 'tags'];
    public $infoAlert = <<<HTML
    <div style="padding: 20px; border-radius: 8px; background-color: #f8f9fa; text-align: center; font-family: Arial, sans-serif; color: #333;">
        <h2 style="color: #007bff; font-size: 24px; margin-bottom: 15px;">Informazioni sul Servizio di Ticketing</h2>
        <p style="font-size: 12px; line-height: 1.6;">
            Gentile Clientela,
        </p>
        <p style="font-size: 12px; line-height: 1.6;">
            Vi informiamo che il nostro servizio di ticketing è attivo dal <strong>lunedì al venerdì</strong>, dalle <strong>9:00 alle 15:00</strong>. I ticket inviati al di fuori di questa fascia oraria saranno presi in carico il giorno lavorativo successivo.
        </p>
        <p style="font-size: 12px; line-height: 1.6;">
            Inoltre, desideriamo ricordarvi di <strong>non inviare email direttamente agli sviluppatori</strong>. Le email inviate al di fuori del sistema di ticketing saranno visualizzate con una cadenza bisettimanale e avranno priorità inferiore rispetto ai ticket ufficiali.
        </p>
        <p style="font-size: 12px; line-height: 1.6;">
            Potete visionare la guida all'utilizzo del nostro servizio di ticketing <a href="https://docs.google.com/document/d/13y-FWVPt9jdoNnROdaZ-izNQ_cdrC79q6DQ8vlJex6w/edit?usp=drive_link" target="_blank" style="color: #007bff; text-decoration: underline;">da qui</a>.
        </p>
        <p style="font-size: 12px; line-height: 1.6;">
            Vi ringraziamo per la vostra collaborazione e comprensione.
        </p>
    </div>
    HTML;

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
            ID::make()->sortable(),
            $this->createdAtField(),
            $this->statusField($request),
            $this->assignedToField(),
            $this->typeField($request),
            $this->infoField($request),
            $this->titleField(),
            $this->relationshipField($request),
            $this->estimatedHoursField($request),
            $this->updatedAtField(),
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

        if ($this->resource->status == null) {
            $this->resource->status = StoryStatus::New->value;
        }
        $storyStatusOptions = [
            StoryStatus::Done->value => StoryStatus::Done,
            ...$this->getStatusLabel($this->resource->status)
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
        $parentCards = parent::cards($request);
        $childCards = [(new HtmlCard())->width('full')->withMeta([
            'content' => $this->infoAlert
        ])->center(true)];
        return array_merge($childCards, $parentCards);
    }
}
