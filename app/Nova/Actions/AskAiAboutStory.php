<?php

namespace App\Nova\Actions;

use App\Models\Story;
use App\Services\AIStoryQaService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class AskAiAboutStory extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * @var string
     */
    public $name = 'Fai una domanda all’AI';

    public function handle(ActionFields $fields, Collection $models)
    {
        $story = $models->first();

        if (! $story instanceof Story) {
            return Action::danger('Seleziona una storia valida.');
        }

        $question = (string) ($fields->get('question') ?? '');

        try {
            $service = app(AIStoryQaService::class);
            $answer = $service->ask($question, $story);

            return Action::message($answer);
        } catch (\Throwable $e) {
            report($e);
            return Action::danger('Errore durante la richiesta AI: '.$e->getMessage());
        }
    }

    public function fields(NovaRequest $request)
    {
        return [
            Textarea::make('Domanda', 'question')
                ->rules('required', 'string', 'min:3')
                ->help('Domanda libera. L’AI cercherà tra tutte le Stories (pgvector) e la Documentation correlata (per tag / nome).'),
        ];
    }
}

