<?php

namespace App\Models;

use App\Enums\EpicStatus;
use App\Enums\StoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

class Epic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'milestone_id',
        'user_id',
        'wmpm_id',
        'text2stories',
        'notes',
    ];

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * Check epic status based on stories status
     * Se la epica non ha storie -> status=new,
     * quando la epica ha tutte le storie in status new ha status new, 
     * quando la epica ha tutte le storie in status test è in status test, 
     * quando la epica ha tutte le storie in status done ha status done.
     * quando la epica ha almeno una storia con status diverso da new ha stato in progress, 
     *
     * @return EpicStatus
     */
    public function getStatusFromStories(): EpicStatus
    {
        $totalStories = $this->stories();
        $newStories = $this->stories()->where('status', StoryStatus::New)->get();
        $TestStories = $this->stories()->where('status', StoryStatus::Test)->get();
        $doneStories = $this->stories()->where('status', StoryStatus::Done)->get();

        if ($totalStories->count() == 0) {
            return EpicStatus::New;
        }

        if ($newStories->count() == $totalStories->count()) {
            return EpicStatus::New;
        }

        if ($TestStories->count() == $totalStories->count()) {
            return EpicStatus::Test;
        }

        if ($doneStories->count() == $totalStories->count()) {
            return EpicStatus::Done;
        }

        return EpicStatus::Progress;
    }
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * It returns a string with WIP (Work in Progress)
     *
     * @return string
     */
    public function wip(): string {
        if (count($this->stories)==0) {
            return 'ND';
        }
        return $this->stories()->whereIn('status',[StoryStatus::Test,StoryStatus::Done])->count().' / '.$this->stories()->count();
    }
}
