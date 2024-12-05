<?php

namespace App\Services;

use App\Models\User;
use App\Models\Story;
use App\Models\StoryLog;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StoryTimeService
{

  static $comparableDateFormat = 'Y-m-d';

  public function getAvailableHoursPerDay(User $user, string $date): int
  {
    //TODO: dynamic by user
    //TODO: dynamic by date
    return 8;
  }

  public function isAnHolidayDay(Carbon $date, User $user)
  {
    //TODO: dynamic by user
    //TODO: dynamic by date/user
    return (int) $date->format('N') > 5; //Exclude saturday and sunday
  }

  //story time
  public function getStoryTime(Story $story): Collection
  {

    $details = collect(['days' => []]);
    $storyTime = 0;
    /**
     * @var \App\Models\User
     */
    $user = $story->user;


    //when it was worked
    $progressDays = $this->getStoryProgressDays($story);
    $details['days'] = $progressDays->toArray();

    //concurrency
    $storiesByDays = $this->getStoryLogGroupedByDays($user, $progressDays, $story);
    foreach ($progressDays as $progressDay) {
      $hoursAvailablePerDay = $this->getAvailableHoursPerDay($user, $progressDay);
      if (isset($storiesByDays[$progressDay])) {
        //that day the user had "$storiesByDays[$progressDay]->count() + 1" stories ( + 1 is the current one)
        $otherProgressStoriesThatDay = $storiesByDays[$progressDay]->count();
        $storyTime +=  $hoursAvailablePerDay / ($otherProgressStoriesThatDay + 1);
      } else {
        $storyTime += $hoursAvailablePerDay;
      }
    }


    $details['hours'] = $storyTime;

    return $details;
  }


  /**
   * Returns a collection of StoryLog models (in progress) with the provided user and days
   *
   * @param User $user
   * @param Collection $progressDays - days with format self::$comparableDateFormat
   * @param Story|false $story - exlude a specific story's StoryLog models
   * @return Collection - of StoryLog models GROUPED BY the day
   */
  public function getStoryLogGroupedByDays(User $user, Collection $progressDays, Story|false $story = false): Collection
  {
    $query = StoryLog::where('changes->status', 'progress')->whereRelation('story', 'user_id', $user->id);

    if ($story !== false) //exclude a specific story if provided
    {
      $query->whereNot('story_id', $story->id);
    }

    return $query->where(function (Builder $query) use ($progressDays) {
      $progressDays->each(
        function (string $dateString) use ($query) {
          $query->orWhereDate('created_at', '=', $dateString);
        }
      );
    })
      ->get()
      ->map([$this, 'getModelDateFormattedForComparisons'])
      ->groupBy('created_at')
      ->mapWithKeys(function ($collection, $date) { //fixes an error on keys format
        return [$this->formatDate(Carbon::parse($date)) => $collection];
      });
  }

  public function getModelDateFormattedForComparisons(Model $model)
  {
    $model->created_at = $this->formatDate($model->created_at);
    return $model;
  }
  protected function formatDate(Carbon $date)
  {
    return $date->format(static::$comparableDateFormat);
  }

  /**
   * Undocumented function
   *
   * @param Story $story - The story model
   * @return Collection - of days (strings) when the story was in a progress status
   */
  public function getStoryProgressDays(Story $story): Collection
  {
    $allStoryLogs = $story->storyLogs; //get all story logs
    $progressLogs = $allStoryLogs->where('changes.status', 'progress'); //get only the progress ones
    $progressDays = collect();

    //iterate over all progress story logs of the story
    foreach ($progressLogs as $progressLog) {
      $progressLogDay = $this->formatDate($progressLog->created_at);

      //get the next storyLog event related to the progress one to understand when the story was "closed"
      $nextStoryLog = $allStoryLogs->where('created_at', '>', $progressLog->created_at)->first();
      $nextStoryLogDay = $nextStoryLog ? $this->formatDate($nextStoryLog->created_at) : $progressLogDay;

      if ($nextStoryLogDay != $progressLogDay) {
        //the story wasn't completed on the same day of the progress status event
        $period = new CarbonPeriod($progressLog->created_at, $nextStoryLog->created_at);
        foreach ($period as $date) {
          //remove holidays: if you start a ticket on friday to end it on monday, you have used 2 days
          if (! $this->isAnHolidayDay($date, $story->user))
            $progressDays[] = $this->formatDate($date);
        }
      } else {
        //the story was completed in a day
        $progressDays[] = $progressLogDay;
      }
    }

    //unique ... if the progress status is triggered multiple times in a day we count them as once only
    return $progressDays
      ->unique();
  }



  static public function getService(): StoryTimeService
  {
    return app()->make(static::class);
  }
}
