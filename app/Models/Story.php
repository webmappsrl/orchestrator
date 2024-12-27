<?php

namespace App\Models;

use App\Models\Tag;
use App\Models\Epic;
use App\Enums\UserRole;
use App\Enums\StoryType;
use App\Enums\StoryStatus;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendStatusUpdateMailJob;
use App\Mail\CustomerNewStoryCreated;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Laravel\Nova\Notifications\NovaNotification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;
use App\Services\StoryResponseService;

class Story extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'status',
        'creator_id',
        'parent_id'
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function ($story) {
            // Check if the status is 'New' and if a developer has been assigned.
            if ($story->status == StoryStatus::New->value && $story->user_id && $story->isDirty('user_id')) {
                $story->status = StoryStatus::Assigned->value;
            }
            if ($story->status == StoryStatus::New->value && $story->isDirty('status')) {
                $story->user_id = null;
            }
        });
    }

    protected static function booted()
    {
        $customerRole = UserRole::Customer;
        $releasedStatus = StoryStatus::Released->value;
        //update epic status whenever a story is created or updated
        static::saved(function (Story $story) use ($customerRole, $releasedStatus) {

            if (isset($story->creator_id) && $story->creator->hasRole($customerRole) && $story->status === $releasedStatus) {
                $story->sendStatusUpdatedEmail($story, $story->creator_id);
            }
            if (!empty($story->epic)) {
                $epic = $story->epic;
                $epic->status = $epic->getStatusFromStories()->value;
                $epic->save();
            }

            if ($story->user_id != $story->tester_id) {
                // Check if user_id or tester_id has changed
                if ($story->wasChanged('user_id') && $story->user_id && $story->user_id != Auth::user()->id) {
                    //send email to the new developer
                    $story->sendStatusUpdatedEmail($story, $story->user_id);
                }
                if ($story->wasChanged('tester_id') && $story->tester_id && $story->tester_id != Auth::user()->id) {
                    //send email to the new tester
                    $story->sendStatusUpdatedEmail($story, $story->tester_id);
                }
            }
        });

        static::created(function (Story $story) {
            $user = auth()->user();
            if ($user) {
                $story->creator_id = $user->id;
                if (!isset($story->type)) {
                    $story->type = StoryType::Helpdesk->value;
                }

                $story->save();
                if ($user->hasRole(UserRole::Customer)) {
                    $developers = User::whereJsonContains('roles', UserRole::Developer)->get();
                    foreach ($developers as $developer) {
                        try {
                            Mail::to($developer->email)->send(new CustomerNewStoryCreated($story));
                        } catch (\Exception $e) {
                            Log::error($e->getMessage());
                        }
                    }
                }
            }
        });

        static::updated(function (Story $story) {
            if (auth()->user()) {
                $tablePivot = DB::table('story_story');
                if ($story->isDirty('status')) {
                    foreach ($story->childStories as $child) {
                        $child->status = $story->status;
                        $child->save();
                    }
                }
                if ($story->wasChanged('parent_id')) {
                    try {

                        $originalStory = $story->getOriginal();
                        $originalParentStoryId = $originalStory['parent_id'];
                        if (isset($story->parent_id)) {
                            $exists = DB::table('story_story')
                                ->where('parent_id', $story->parent_id)
                                ->where('child_id',  $story->id)
                                ->exists();
                            if (is_null($originalParentStoryId)) {
                                if ($exists === false) {
                                    $tablePivot
                                        ->insert([
                                            'parent_id' => $story->parent_id,
                                            'child_id' => $story->id
                                        ]);
                                }
                            } else if ($story->parent_id != $originalParentStoryId) {
                                $tablePivot
                                    ->where('parent_id', $originalParentStoryId)
                                    ->where('child_id', $story->id)
                                    ->delete();
                                $tablePivot
                                    ->insert([
                                        'parent_id' => $story->parent_id,
                                        'child_id' => $story->id
                                    ]);
                            }
                        } else {
                            if ($originalParentStoryId) {
                                $tablePivot
                                    ->where('parent_id', $originalParentStoryId)
                                    ->where('child_id', $originalStory['id'])
                                    ->delete();
                            }
                        }
                    } catch (\Exception $e) {
                        $e;
                    }
                }
                $storyHasDeveloper = isset($story->user_id);
                $storyHasTester = isset($story->tester_id);
                $devIsLoggedIn = $storyHasDeveloper ? auth()->user()->id == $story->user_id : false;
                $testerIsLoggedIn = $storyHasTester ? auth()->user()->id == $story->tester_id : false;

                $status = is_object($story->status) ? $story->status->value : $story->status;

                $devHasUpdatedStatus = $devIsLoggedIn && $story->isDirty('status') && $status == 'progress' || $status == 'testing';
                $testerHasUpdatedStatus = $testerIsLoggedIn && $story->isDirty('status') && $status == 'progress' || $status == 'tested' || $status == 'done' || $status == 'rejected';

                $devAndTesterAreTheSamePerson = $story->tester_id == $story->user_id;

                if ($devAndTesterAreTheSamePerson) {
                    return;
                }

                if ($devHasUpdatedStatus && $storyHasTester) {
                    $story->sendStatusUpdatedEmail($story, $story->tester_id);

                    $story->tester->notify(NovaNotification::make()
                        ->type('info')
                        ->message('The status of the story ' . $story->id . ' has been updated to ' . $status . ' by ' . auth()->user()->name)
                        ->action('View story', url('/nova/resources/stories/' . $story->id))
                        ->icon('star'));
                }

                if ($testerHasUpdatedStatus && $storyHasDeveloper) {
                    $story->sendStatusUpdatedEmail($story, $story->user_id);

                    $story->developer->notify(NovaNotification::make()
                        ->type('info')
                        ->message('The status of the story ' . $story->id . ' has been updated to ' . $status . ' by ' . auth()->user()->name)
                        ->action('View story', url('/nova/resources/stories/' . $story->id))
                        ->icon('star'));
                }
            }
        });
        static::saving(function ($story) {
            if ($story->parent_id && $story->childStories()->exists()) {
                // Lancia un'eccezione o rifiuta il salvataggio
                throw new \Exception('Una storia che è figlia non può avere figli.');
            }
        });
    }
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'story_participants');
    }

    public function storyLogs()
    {
        return $this->hasMany(StoryLog::class);
    }


    public function sendStatusUpdatedEmail(Story $story, $userId)
    {
        $user = User::find($userId);
        SendStatusUpdateMailJob::dispatch($story, $user);
    }
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'story_project');
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    public function tester()
    {
        return $this->belongsTo(User::class, 'tester_id');
    }


    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * It returns the corresponding EPIC
     *
     * @return BelongsTo
     */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function deadlines(): MorphToMany
    {
        return $this->morphToMany(Deadline::class, 'deadlineable');
    }

    // Relazione per ottenere la storia genitore
    public function parentStory()
    {
        return $this->belongsTo(Story::class, 'parent_id');
    }

    // Relazione per ottenere le storie figlie
    public function childStories()
    {
        return $this->belongsToMany(Story::class, 'story_story', 'parent_id', 'child_id')->using(StoryPivot::class);
    }


    /**
     * Register a spatie media collection
     * @return void
     * @link https://spatie.be/docs/laravel-medialibrary/v9/working-with-media-collections/defining-media-collections
     */
    public function registerMediaCollections(): void
    {

        $this->addMediaCollection('documents')->acceptsMimeTypes(array_merge(
            config('services.media-library.allowed_document_formats'),
            config('services.media-library.allowed_image_formats')
        ));
    }

    public function views()
    {
        return $this->hasMany(StoryLog::class);
    }

    // Metodo generico per filtrare per anno e trimestre
    public static function filterByYearAndQuarter($year, $quarter)
    {
        return self::whereRaw('EXTRACT(YEAR FROM created_at) = ?', [$year])
            ->whereRaw('EXTRACT(QUARTER FROM created_at) = ?', [$quarter]);
    }

    // Metodo per il totale filtrato per tipo
    public static function getTotalByType($year, $quarter, $type = null)
    {
        $query = self::filterByYearAndQuarter($year, $quarter);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->get();
    }

    // Metodo per ottenere dati aggregati con una percentuale rispetto al totale
    public static function getAggregatedData($year, $quarter)
    {
        $totalStoriesSubquery = '(SELECT COUNT(*) FROM stories WHERE EXTRACT(YEAR FROM created_at) = ? AND EXTRACT(QUARTER FROM created_at) = ?)';

        return self::filterByYearAndQuarter($year, $quarter)
            ->select(
                'type',
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(*) / $totalStoriesSubquery * 100 as percentage")
            )
            ->setBindings([$year, $quarter, $year, $quarter])
            ->groupBy('type')
            ->get();
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Metodo per aggiungere un partecipante
    protected function addParticipant($userId)
    {
        $participants = $this->participants ?? [];
        if (!in_array($userId, $participants)) {
            $participants[] = $userId;
            $this->participants = $participants;
            $this->save();
        }
    }

    // Metodo per notificare tutti i partecipanti
    protected function notifyParticipants($response, $sender)
    {
        $participants = $this->participants;
        foreach ($participants as $participant) {
            if ($participant->id !== $sender->id) {
                try {
                    Mail::to($participant->email)->send(new \App\Mail\StoryResponse($this, $participant, $sender, $response));
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                }
            }
        }
    }
}
