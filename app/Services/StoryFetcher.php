<?php

namespace App\Services;

use App\Models\Story;
use App\Enums\StoryType;
use App\Enums\StoryStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class StoryFetcher
{
    public static function byStatusAndUser(string $status, Authenticatable $user)
    {
        return Story::query()
            ->where('status', $status)
            ->whereNotNull('user_id')
            ->whereNotNull('type')
            ->where('type', '!=', StoryType::Scrum->value)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('tester_id', $user->id);
            })
            ->get();
    }
}
