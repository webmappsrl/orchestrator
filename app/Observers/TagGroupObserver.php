<?php

namespace App\Observers;

use App\Models\TagGroup;

class TagGroupObserver
{
    public function saved(TagGroup $tagGroup): void
    {
        $tagGroup->syncConditionsFromSlots();
    }
}
