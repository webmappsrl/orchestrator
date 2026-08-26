<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Models\Task as TaskModel;
use App\Nova\Actions\ToggleTaskCompleted;
use App\Nova\Filters\TaskAssigneeFilter;
use App\Nova\Filters\TaskDueDateFilter;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Marshmallow\Tiptap\Tiptap;

class Task extends Resource
{
    public static $model = TaskModel::class;

    public static $title = 'title';

    public static $search = [
        'id',
        'title',
    ];

    public function fields(NovaRequest $request)
    {
        if ($request->viaResource === 'quotes') {
            return [
                ID::make()->sortable(),

                Text::make(__('Task'), 'title')
                    ->sortable()
                    ->rules('required', 'max:255'),

                DateTime::make(__('Due date'), 'due_date')
                    ->sortable()
                    ->rules('required'),

                $this->statusBadgeField(),

                $this->completedField(),

                $this->notesField(),
            ];
        }

        return [
            ID::make()->sortable(),

            Text::make(__('Customer'), function () {
                $customer = $this->quote?->customer;
                if (! $customer) {
                    return '—';
                }
                $url = url("/resources/customers/{$customer->id}");
                return "<a href='{$url}' class='no-underline dim text-primary font-bold'>" . e($customer->full_name) . '</a>';
            })->asHtml()->exceptOnForms(),

            BelongsTo::make(__('Quote'), 'quote', Quote::class)
                ->searchable()
                ->rules('required'),

            Text::make(__('Task'), 'title')
                ->sortable()
                ->rules('required', 'max:255'),

            Date::make(__('Due date'), 'due_date')
                ->sortable()
                ->rules('required'),

            $this->statusBadgeField(),

            $this->completedField(),

            Text::make(__('Assignee'), function () {
                return $this->assignee?->name ?? '—';
            })->exceptOnForms(),

            $this->notesField(),
        ];
    }

    private function statusBadgeField(): Badge
    {
        return Badge::make(__('Status'), function () {
            return $this->urgencyBadgeKey();
        })->map([
            'overdue' => 'danger',
            'due_today' => 'warning',
            'upcoming' => 'success',
            'completed' => 'info',
            'completed_late' => 'warning',
        ])->label(function ($value) {
            return $this->urgencyBadgeLabel();
        })->onlyOnIndex();
    }

    private function completedField(): Boolean
    {
        return Boolean::make(__('Completed'), 'completed')
            ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                $model->status = $request->boolean($requestAttribute)
                    ? TaskModel::STATUS_COMPLETED
                    : TaskModel::STATUS_TODO;
            })
            ->resolveUsing(fn () => $this->status === TaskModel::STATUS_COMPLETED)
            ->hideWhenCreating();
    }

    private function notesField(): Tiptap
    {
        return Tiptap::make(__('Notes'), 'notes')
            ->hideFromIndex()
            ->buttons([
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
            ]);
    }

    public function urgencyBadgeKey(): string
    {
        if ($this->status === TaskModel::STATUS_COMPLETED) {
            return $this->completed_at && $this->completed_at->gt($this->due_date)
                ? 'completed_late'
                : 'completed';
        }

        $today = now()->startOfDay();

        if ($this->due_date->lt($today)) {
            return 'overdue';
        }

        if ($this->due_date->isSameDay($today)) {
            return 'due_today';
        }

        return 'upcoming';
    }

    public function urgencyBadgeLabel(): string
    {
        $today = now()->startOfDay();
        $dueDate = $this->due_date->copy()->startOfDay();

        return match ($this->urgencyBadgeKey()) {
            'overdue' => __('Overdue by :days days', ['days' => (int) $dueDate->diffInDays($today)]),
            'due_today' => __('Due today'),
            'upcoming' => __('Due in :days days', ['days' => (int) $today->diffInDays($dueDate)]),
            'completed_late' => __('Completed late'),
            default => __('Completed'),
        };
    }

    public function filters(NovaRequest $request)
    {
        if ($request->viaResource === 'quotes') {
            return [
                new TaskDueDateFilter(),
            ];
        }

        return [
            new TaskDueDateFilter(),
            new TaskAssigneeFilter(),
        ];
    }

    public function actions(NovaRequest $request)
    {
        return [
            (new ToggleTaskCompleted())->showInline(),
        ];
    }

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->viaResource === 'quotes') {
            return $query;
        }

        $query->with(['quote.user', 'quote.customer']);

        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager)) {
            return $query->reorder('due_date', 'asc');
        }

        return $query->forUser($user)->reorder('due_date', 'asc');
    }
}
