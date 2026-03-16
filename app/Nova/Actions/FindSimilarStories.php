<?php

namespace App\Nova\Actions;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class FindSimilarStories extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Trova Storie Simili';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $story = $models->first();

        if (!$story instanceof Story) {
            return Action::danger('Seleziona una storia valida.');
        }

        // Genera l'embedding se non esiste
        if ($story->embedding === null) {
            if (!$story->generateEmbedding()) {
                return Action::danger('Impossibile generare l\'embedding per questa storia. Verifica che abbia contenuto testuale.');
            }
            $story->refresh();
        }

        $limit = $fields->get('limit') ?? 5;
        $threshold = $fields->get('threshold') ?? 0.7;

        $similarStories = $story->findSimilar($limit, $threshold);

        if ($similarStories->isEmpty()) {
            return Action::message('Nessuna storia simile trovata con la soglia di similarità specificata.');
        }

        // Crea un messaggio con i link alle storie simili
        $message = "Trovate {$similarStories->count()} storie simili:\n\n";
        foreach ($similarStories as $similar) {
            $similarity = round(($similar->similarity ?? 0) * 100, 1);
            $message .= "• [Story #{$similar->id}]({$this->getStoryUrl($similar->id)}): {$similar->name} (Similarità: {$similarity}%)\n";
        }

        return Action::message($message);
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Number::make('Limit', 'limit')
                ->help('Numero massimo di storie simili da trovare')
                ->default(5)
                ->min(1)
                ->max(20)
                ->rules('required', 'integer', 'min:1', 'max:20'),

            Number::make('Threshold', 'threshold')
                ->help('Soglia di similarità (0.0 - 1.0). Valori più alti = maggiore similarità richiesta')
                ->default(0.7)
                ->step(0.1)
                ->min(0)
                ->max(1)
                ->rules('required', 'numeric', 'min:0', 'max:1'),
        ];
    }

    /**
     * Get the URL for a story in Nova
     *
     * @param int $storyId
     * @return string
     */
    private function getStoryUrl(int $storyId): string
    {
        return Nova::url('/resources/stories/' . $storyId);
    }
}
