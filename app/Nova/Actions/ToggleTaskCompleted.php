<?php

namespace App\Nova\Actions;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class ToggleTaskCompleted extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Segna come completato / Riapri';

    public function authorizedToRun(Request $request, $model)
    {
        return $model->creator_id !== null && $model->creator_id === $request->user()?->id;
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $task) {
            $task->status = $task->status === Task::STATUS_COMPLETED
                ? Task::STATUS_TODO
                : Task::STATUS_COMPLETED;
            $task->save();
        }
    }
}
