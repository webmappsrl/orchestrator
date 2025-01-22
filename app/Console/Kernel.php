<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('story:progress-to-todo')
            ->timezone('Europe/Rome')
            ->dailyAt('18:00')
            ->before(function () {
                Log::info('story:progress-to-todo command starting');
            })
            ->after(function () {
                Log::info('story:progress-to-todo command finished');
            });
        $schedule->command('sync:stories-calendar')
            ->timezone('Europe/Rome')
            ->dailyAt('07:45')
            ->before(function () {
                Log::info('sync:stories-calendar command starting');
            })
            ->after(function () {
                Log::info('sync:stories-calendar command finished');
            });
        $schedule
            ->command('story:auto-update-status')
            ->timezone('Europe/Rome')
            ->dailyAt('07:45')
            ->before(function () {
                Log::info('story:auto-update-status command starting');
            })
            ->after(function () {
                Log::info('story:auto-update-status command finished');
            });
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        $this->load(__DIR__ . 'Commands/OrchestratorImport');
        $this->load(__DIR__ . 'Commands/ImportProducts');

        require base_path('routes/console.php');
    }
}
