<?php

namespace App\Models;

use App\Models\Epic;
use App\Models\Tag;
use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Mail\CustomerNewStoryCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Laravel\Nova\Notifications\NovaNotification;

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
            // Controlla se lo stato è 'New' e se è stato assegnato un developer.
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
                if ($story->isDirty('user_id')) {
                    $story->sendStatusUpdatedEmail($story, $story->user_id);
                }
                if ($story->isDirty('tester_id')) {
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
                if ($story->isDirty('parent_id')) {
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
                $testerHasUpdatedStatus = $testerIsLoggedIn && $story->isDirty('status') && $status == 'progress' || $status == 'done' || $status == 'rejected';

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
    public function sendStatusUpdatedEmail(Story $story, $userId)
    {
        $user = User::find($userId);
        try {
            Mail::to($user->email)->send(new \App\Mail\StoryStatusUpdated($story, $user));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception($e->getMessage());
        }
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

        $this->addMediaCollection('documents')->acceptsMimeTypes(config('services.media-library.allowed_document_formats'));

        $this->addMediaCollection('images')->acceptsMimeTypes(config('services.media-library.allowed_image_formats'));
    }

    /**
     * Add a response to the story customer_request field
     * @return void
     */
    public function addResponse($response)
    {
        $sender = auth()->user();
        $senderRoles = $sender->roles->toArray();
        $style = '';
        $divider = "<div style='height: 2px; background-color: #e2e8f0; margin: 20px 0;'></div>";

        if (array_search(UserRole::Developer, $senderRoles) !== false) {
            $senderType = 'developer';
            $style = "style='background-color: #f8f9fa; border-left: 4px solid #6c757d; padding: 10px 20px;'";
        } else if ($sender->id == $this->tester_id) {
            $senderType = 'tester';
            $style = "style='background-color: #e6f7ff; border-left: 4px solid #1890ff; padding: 10px 20px;'";
        } else if (array_search(UserRole::Customer, $senderRoles) !== false) {
            $senderType = 'customer';
            $style = "style='background-color: #fff7e6; border-left: 4px solid #ffa940; padding: 10px 20px;'";
        } else {
            $senderType = 'other';
            $style = "style='background-color: #d7f7de; border-left: 4px solid #6c757d; padding: 10px 20px;'";
            $string = '';
            foreach ($senderRoles as $role) {
                $string .= $role->value . ', ';
            }
            Log::info('Sender answering to story with id: ' . $this->id .  ' has roles: ' . $string);
        }

        $formattedResponse = $sender->name . " ha risposto il: " . now()->format('d-m-Y H:i') . "\n <div $style> <p>" . $response . " </p> </div>" . $divider;
        $this->customer_request = $formattedResponse . $this->customer_request;
        $this->save();
        try {
            if ($this->creator_id && $senderType != 'customer') {
                Mail::to($this->creator->email)->send(new \App\Mail\StoryResponse($this, $this->creator, $sender, $response));
            }
            switch ($senderType) {
                case 'developer':
                    if ($this->tester_id) {
                        if ($this->tester_id != $this->user_id) {
                            Mail::to($this->tester->email)->send(new \App\Mail\StoryResponse($this, $this->tester, $sender, $response));
                        }
                    }
                    break;
                case 'tester':
                    if ($this->user_id) {
                        if ($this->tester_id != $this->user_id) {
                            Mail::to($this->developer->email)->send(new \App\Mail\StoryResponse($this, $this->developer, $sender, $response));
                        }
                    }
                    break;
                case 'customer':
                    if ($this->user_id) {
                        Mail::to($this->developer->email)->send(new \App\Mail\StoryResponse($this, $this->developer, $sender, $response));
                    }
                    //if the customer reply to a released ticket, change the ticket status to assigned
                    if ($this->status == StoryStatus::Released->value) {
                        $this->status = StoryStatus::Assigned->value;
                        $this->save();
                    }
                    if ($this->tester_id) {
                        Mail::to($this->tester->email)->send(new \App\Mail\StoryResponse($this, $this->tester, $sender, $response));
                    }
                    break;
                default:
                    if ($this->user_id) {
                        Mail::to($this->developer->email)->send(new \App\Mail\StoryResponse($this, $this->developer, $sender, $response));
                    }
                    if ($this->tester_id) {
                        Mail::to($this->tester->email)->send(new \App\Mail\StoryResponse($this, $this->tester, $sender, $response));
                    }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
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
}
