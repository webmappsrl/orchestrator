<?php

namespace App\Nova;

use Carbon\Carbon;
use App\Models\Epic;
use App\Enums\UserRole;
use App\Models\Project;
use Laravel\Nova\Panel;
use App\Enums\StoryType;
use Manogi\Tiptap\Tiptap;
use App\Enums\StoryStatus;
use Laravel\Nova\Fields\ID;
use App\Enums\StoryPriority;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\MorphToMany;
use App\Nova\Actions\MoveStoriesFromEpic;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use App\Nova\Actions\moveStoriesFromProjectToEpicAction;
use Illuminate\Http\Request;

class AssignedToMeStory extends Story
{
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->where('user_id', $request->user()->id)->whereIn('status', [StoryStatus::Progress, StoryStatus::New]);
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
}
