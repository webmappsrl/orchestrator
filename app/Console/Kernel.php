<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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
        // call the checkIfExpired() method on all deadlines everyday at 5 past midnight
        $schedule->call(function () {
            \App\Models\Deadline::all()->each(function ($deadline) {
                $deadline->checkIfExpired();
            });
        })->timezone('Europe/Rome')
            ->dailyAt('00:05');


        $schedule->command('queue:work --stop-when-empty')->timezone('Europe/Rome')->hourly()->withoutOverlapping();
        $schedule->job(new \App\Jobs\SendDigestEmail)->timezone('Europe/Rome')->dailyAt('19:00');
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
