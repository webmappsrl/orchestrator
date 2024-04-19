<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Models\story;
use Illuminate\Support\Facades\DB;

class StoryPivot extends Pivot
{
    protected $table = 'story_story';

    public static function boot()
    {
        parent::boot();
        static::deleting(function ($pivot) {
            if (isset($pivot) && isset($pivot->child_id)) {
                $childStory = Story::find($pivot->child_id);
                $childStory->parent_id = null;
                $childStory->save();
            }
        });
        static::saving(function ($pivot) {
            if (isset($pivot) && isset($pivot->child_id)) {
                DB::table('stories')
                    ->where('id', $pivot->child_id)
                    ->update(['parent_id' => $pivot->parent_id]);
            }
            // Qui puoi aggiungere la logica per gestire i cambiamenti
            // Ad esempio, potresti voler aggiornare il parent_id su uno dei modelli Story associati
        });
    }
}
