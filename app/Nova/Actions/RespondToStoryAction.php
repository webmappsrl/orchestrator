<?php

namespace App\Nova\Actions;

use Manogi\Tiptap\Tiptap;
use App\Enums\StoryStatus;
use App\Services\StoryResponseService;
use Closure;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class RespondToStoryAction extends Action
{
    use InteractsWithQueue, Queueable;


    private $responseService;
    private $field;
    public function __construct(string $field, string $name)
    {
        $this->responseService = new StoryResponseService();
        $this->field = $field;
        $this->name = __($name);
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {

        foreach ($models as $model) {
            if ($model->status == StoryStatus::Done->value) {
                return Action::danger('This story is already done!');
            }
            $this->responseService->addResponse($model, $fields->response, $this->field);
        }

        return Action::message('The response has been added successfully!');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $tiptapAllButtons =  [
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
        return [
            Tiptap::make('Response')->rules('required')->buttons($tiptapAllButtons)
        ];
    }
}
