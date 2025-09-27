<?php

namespace App\Nova\Actions;

use App\Enums\StoryType;
use App\Models\FundraisingOpportunity;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class CreateStoryFromFundraisingOpportunity extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Crea Ticket/Story';

    /**
     * Indicates if this action is only available on the resource index.
     *
     * @var bool
     */
    public $onlyOnIndex = false;

    /**
     * Indicates if this action is available on the resource detail.
     *
     * @var bool
     */
    public $showOnDetail = true;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $opportunity) {
            /** @var FundraisingOpportunity $opportunity */
            
            // Crea la Story
            $story = Story::create([
                'name' => 'Interesse per: ' . $opportunity->name,
                'description' => $fields->description ?? 'Ticket creato per esprimere interesse nell\'opportunità di finanziamento: ' . $opportunity->name,
                'creator_id' => auth()->id(),
                'type' => StoryType::Ticket->value,
                'status' => 'new',
            ]);

            return Action::redirect('/resources/stories/' . $story->id);
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Textarea::make('Descrizione del ticket', 'description')
                ->help('Descrivi il tuo interesse per questa opportunità di finanziamento')
                ->rows(4),
        ];
    }
}
