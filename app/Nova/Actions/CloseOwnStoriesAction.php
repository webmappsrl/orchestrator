<?php

namespace App\Nova\Actions;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Notifications\NovaNotification;

/**
 * Il cliente porta a done le proprie Story, una o più alla volta (oc:9032).
 *
 * Dall'interfaccia il cliente vede solo le proprie Story, ma con un id solo
 * Nova esegue l'Action senza applicare l'indexQuery, e StoryPolicy::update
 * restituisce sempre true: il controllo su creator_id in handle() è l'unico
 * che impedisce di chiudere una Story altrui con una richiesta costruita a mano.
 */
class CloseOwnStoriesAction extends Action
{
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $userId = auth()->id();
        $closed = 0;

        foreach ($models as $story) {
            if ($story->creator_id !== $userId) {
                continue;
            }

            if ($story->status === StoryStatus::Done->value) {
                continue;
            }

            // Lo StoryLog del cambio di stato lo crea l'override di Story::save().
            $story->status = StoryStatus::Done->value;
            $story->save();
            $closed++;

            $this->notifyAssignees($story);
        }

        return Action::message(__(':closed request(s) marked as done.', ['closed' => $closed]));
    }

    /**
     * Avvisa dev e tester assegnati che il cliente ha chiuso la Story.
     */
    protected function notifyAssignees(Story $story): void
    {
        $message = __('The story :id has been closed by :name', [
            'id' => $story->id,
            'name' => auth()->user()->name,
        ]);

        // Dev e tester possono coincidere: una sola notifica per persona.
        collect([$story->developer, $story->tester])
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify(NovaNotification::make()
                ->type('info')
                ->message($message)
                ->action(__('View story'), url('/nova/resources/stories/' . $story->id))
                ->icon('check-circle')));
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }

    public function name()
    {
        return __('Mark as done');
    }

    public function uriKey()
    {
        return 'close-own-stories';
    }
}
