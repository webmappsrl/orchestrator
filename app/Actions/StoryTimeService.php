<?php

namespace App\Actions;

use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Story;
use App\Models\StoryLog;
use Carbon\CarbonPeriod;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use App\Services\GoogleCalendarService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Contracts\Database\Eloquent\Builder;

class StoryTimeService
{
  use AsAction;
  static $comparableDateFormat = 'Y-m-d';

  public string $commandSignature = 'service:story-time {story_id?}';
  public string $commandDescription = 'Updates the story time of all stories or the one provided as argument.';

  public function asCommand(Command $command)
  {
    $storyId = $command->argument('story_id');

    if ($storyId) {
      $story = Story::findOrFail();
      $success = $this->handle($story);
      if ($success)
        $command->line(sprintf('Story time updated for %s.', $story->name));
      else
        $command->error(sprintf('An error occurred updating time for %s.', $story->name));
    } else {
      $command->withProgressBar(Story::all(), function (Story $story) {
        $this->handle($story);
      });
    }
  }

  public function handle(Story $story): bool
  {
    $storyTime = $this->getStoryTime($story);
    if ($storyTime === false)
      return false;
    $story->hours = $storyTime['hours'];
    if ($story->hours) {
      $story->saveQuietly();
      return true;
    }
    return false;
  }



  public function getAvailableHoursPerDay(User $user, CarbonInterface $date): int
  {

    if ($this->isAnHolidayDay($user, $date))
      return 0;

    $estimatedWorkHours = GoogleCalendarService::getService()->getUserWorkingHoursByDate($user, $date);

    if ($estimatedWorkHours > 4)
      return 8; //all day

    return 4; //half day
  }

  public function isAnHolidayDay(User $user, CarbonInterface $date)
  {
    if ($this->isSundayOrSaturday($date))
      return true;

    return $this->getUserStoryLogsHours($user, $date) === 0;
  }

  protected function isSundayOrSaturday(CarbonInterface $date)
  {
    return (int) $date->format('N') > 5; //Exclude saturday and sunday
  }

  public function getUserStoryLogsHours(User $user, Carbon $date): float
  {
    $events = StoryLog::where('user_id', $user->id)->whereDate('created_at', $date)->orderBy('created_at')->get();

    if ($events->count() === 0)
      return 0;

    $period = CarbonPeriod::create(
      $events->first()->created_at,
      '1 minute', //with this interval i see also periods under 1h and have as result something that is greater than 0
      $events->last()->created_at
    );

    return count($period) / 60;
  }

  /**
   * Returns working hours and days of a story
   *
   * @param Story $story
   * @return Collection|false - with working hours and days of the story provided or false if the story hasn't the user
   */
  public function getStoryTime(Story $story): Collection|false
  {
    /**
     * @var \App\Models\User
     */
    $user = $story->user;
    if (! $user)
      return false;

    $response = collect([]);
    $storyTime = 0;

    //when it was worked
    $progressDays = $this->getStoryProgressDays($story);
    $response['days'] = $progressDays->toArray();

    //other stories concurrency
    $storiesByDays = $this->getStoryLogGroupedByDays($user, $progressDays, $story);
    foreach ($progressDays as $progressDay) {
      $hoursAvailablePerDay = $this->getAvailableHoursPerDay($user, $this->stringToDate($progressDay));
      if (isset($storiesByDays[$progressDay])) {
        //that day the user had "$storiesByDays[$progressDay]->count() + 1" stories ( + 1 is the current one)
        $otherProgressStoriesThatDay = $storiesByDays[$progressDay]->count();
        $storyTime +=  $hoursAvailablePerDay / ($otherProgressStoriesThatDay + 1);
      } else {
        $storyTime += $hoursAvailablePerDay;
      }
    }


    $response['hours'] = round($storyTime, 2);

    return $response;
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
        return [$this->dateToString(Carbon::parse($date)) => $collection];
      });
  }

  protected function stringToDate(string $dateString): Carbon
  {
    return Carbon::createFromFormat(static::$comparableDateFormat, $dateString);
  }

  /**
   * Transform the created_at attribute from a DateTime to a Date
   *
   * @param Model $model
   * @return Model
   */
  public function getModelDateFormattedForComparisons(Model $model): Model
  {
    $model->created_at = $this->dateToString($model->created_at);
    return $model;
  }

  /**
   * Date formatter
   *
   * @param Carbon $date
   * @return string
   */
  protected function dateToString(Carbon $date): string
  {
    return $date->format(static::$comparableDateFormat);
  }

  /**
   * Computes and returns working days of a provided Story
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
      $progressLogDay = $this->dateToString($progressLog->created_at);

      //get the next storyLog event related to the progress one to understand when the story was "closed"
      $nextStoryLog = $allStoryLogs->where('created_at', '>', $progressLog->created_at)->first();
      $nextStoryLogDay = $nextStoryLog ? $this->dateToString($nextStoryLog->created_at) : $progressLogDay;

      if ($nextStoryLogDay != $progressLogDay) {
        //the story wasn't completed on the same day of the progress status event
        $period = new CarbonPeriod($progressLog->created_at, $nextStoryLog->created_at);
        foreach ($period as $date) {
          //remove holidays: if you start a ticket on friday to end it on monday, you have used 2 days
          if (! $this->isAnHolidayDay($story->user, $date))
            $progressDays[] = $this->dateToString($date);
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
}
