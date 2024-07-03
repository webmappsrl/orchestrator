<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StoryLog;
use Carbon\Carbon;

class LogStory
{
    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();
        // Controlla se il percorso contiene "stories" e se ha un ID risorsa
        if (preg_match('/resources\/.*stories.*\/(\d+)/', $path, $matches)) {
            $storyId = $matches[1];
            $userId = Auth::id();

            if ($userId && $storyId) {

                // Controlla se esiste una visualizzazione per oggi per la stessa storia e utente
                $today = Carbon::today();
                $view = StoryLog::where('story_id', $storyId)
                    ->where('user_id', $userId)
                    ->whereDate('viewed_at', $today)
                    ->first();

                if ($view) {
                    // Incrementa il contatore solo se è passata almeno mezz'ora dall'ultima visualizzazione
                    if ($view->updated_at->diffInMinutes(now()) >= 30) {
                        $view->touch(); // Aggiorna il timestamp updated_at
                    }
                } else {
                    $changes['watch'] = now()->format('Y-m-d H:i:s');
                    // Crea una nuova entry se non esiste
                    StoryLog::create([
                        'story_id' => $storyId,
                        'user_id' => $userId,
                        'viewed_at' => now(),
                        'changes' => $changes,
                    ]);
                }
            }
        }

        return $next($request);
    }
}
