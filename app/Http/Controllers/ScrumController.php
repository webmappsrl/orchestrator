<?php

namespace App\Http\Controllers;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Models\Story;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScrumController extends Controller
{
    public function createOrUpdateScrumStory(Request $request, $meetCode = 'qcz-incv-dem')
    {
        $user = auth()->user();
        $today = Carbon::now();
        $title = "{$today->format('d-m-y')}";

        // Cerca se esiste già una storia con questo titolo
        $scrumTicket = Story::where('name', $title)->where('creator_id', $user->id)->first();

        if (!$scrumTicket) {
            // Crea nuova storia
            $scrumTicket = Story::create([
                'name' => $title,
                'status' => StoryStatus::Progress->value,
                'type' => StoryType::Scrum->value,
                'user_id' => $user->id
            ]);
        } else {
            $scrumTicket->status = StoryStatus::Progress->value;
            $scrumTicket->save();
        }

        return redirect()->away('https://meet.google.com/' . $meetCode);
    }
}
