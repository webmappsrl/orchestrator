<?php

namespace App\Actions;

use Illuminate\Support\Carbon;
use App\Models\Story;
use Carbon\CarbonPeriod;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

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
      $story = Story::findOrFail($storyId);
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


  protected function isSundayOrSaturday(CarbonInterface $date): bool
  {
    //N	ISO 8601 numeric representation of the day of the week	1 (for Monday) through 7 (for Sunday)
    return (int) $date->format('N') > 5; //Exclude saturday and sunday
  }

  protected function isAWorkingDate(CarbonInterface $date): bool
  {
    if ($this->isSundayOrSaturday($date))
      return false;
    //G	24-hour format of an hour without leading zeros	0 through 23
    $hour = (int) $date->format('G');
    return $hour > 8 && $hour < 18; //Only hours between 8am and 6pm are considered as working hours
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

    $response = [];

    //when it was worked
    $progressDays = $this->getStoryProgressDaysMinutes($story);

    $response['days'] = $progressDays;
    $response['hours'] = round($progressDays->sum() / 60, 2); //returns hours rounded

    return collect($response);
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
  public function getStoryProgressDaysMinutes(Story $story): Collection
  {
    $allStoryLogs = $story->storyLogs; //get all story logs
    $progressLogs = $allStoryLogs->where('changes.status', 'progress'); //get only the progress ones
    $progressDays = collect();

    //iterate over all progress story logs of the story
    foreach ($progressLogs as $progressLog) {
      $progressLogDay = $this->dateToString($progressLog->created_at);

      if (! $progressDays->has($progressLogDay))
        $progressDays[$progressLogDay] = 0;

      //get the next storyLog event related to the progress one to understand when the story was "closed"
      $nextStoryLog = $allStoryLogs
        ->where('created_at', '>', $progressLog->created_at)
        ->filter(function ($storyLog) {
          return key_exists('status', $storyLog->changes); //exclude some items, evaluates only status change
        })
        ->first();

      //use now() if the ticket is still in progress
      $closeStoryLogDate = $nextStoryLog ? $nextStoryLog->created_at : Carbon::now();

      $period = new CarbonPeriod($progressLog->created_at, '1 minute', $closeStoryLogDate);
      $time = count($period);


      //remove with intervals of ten minutes all dates out of working time
      //this is a quick way to calculate progress time before the new TODO status feature
      $periodHours = new CarbonPeriod($progressLog->created_at, 'PT10M', $closeStoryLogDate);
      foreach ($periodHours as $hourDate) {
        if (! $this->isAWorkingDate($hourDate))
          $time -= 10;
      }

      $progressDays[$progressLogDay] += $time;
    }


    return $progressDays;
  }
}
