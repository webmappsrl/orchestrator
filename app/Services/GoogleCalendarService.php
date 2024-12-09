<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\GoogleCalendar\Event;

class GoogleCalendarService extends AbstractService
{

  protected function getUserWorkingEventsByDate(User $user, CarbonInterface $date): Collection
  {
    $workingDayStart = $date->copy()->setTime(8, 0, 0);
    $workingDayEnd = $date->copy()->setTime(18, 0, 0);
    return Event::get($workingDayStart, $workingDayEnd, [], $this->getUserCalendarId($user));
  }

  /**
   * Return working hours in a day using Google Calendar
   * use all day (busy) events to calculate working hours
   * returns false if no events are detected in the provided day
   *
   * @param User $user
   * @param CarbonInterface $date
   * @param integer $fullWorkingDayHours
   * @return integer|false - Working hours or false if there arent events to use
   */
  public function getUserWorkingHoursByDate(User $user, CarbonInterface $date, $fullWorkingDayHours = 8): int|false
  {
    $hoursOff = 0;
    $events = $this->getUserWorkingEventsByDate($user, $date);
    if ($events->count() > 0) {
      $events = $events->filter(function ($event) {
        return is_null($event->transparency); //Only "Busy" type events
      });
      foreach ($events as $event) {
        $diff = $this->eventDateTimeToCarbon($event->end)
          ->diffInHours(
            $this->eventDateTimeToCarbon($event->start)
          );
        $hoursOff += $diff;
      }
      return $fullWorkingDayHours - $hoursOff;
    }
    return false;
  }

  protected function eventDateTimeToCarbon(EventDateTime $eventDateTime): CarbonInterface
  {
    return Carbon::make($eventDateTime->getDateTime());
  }

  public function getUserCalendarId(User $user): string
  {
    return $user->email;
  }
}
