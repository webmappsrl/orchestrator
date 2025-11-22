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
        // Story progress to todo
        if (config('orchestrator.tasks.story_progress_to_todo')) {
            $schedule->command('story:progress-to-todo')
                ->timezone('Europe/Rome')
                ->dailyAt('18:00')
                ->before(function () {
                    Log::info('story:progress-to-todo command starting');
                })
                ->after(function () {
                    Log::info('story:progress-to-todo command finished');
                });
        }

        // Story scrum to done
        if (config('orchestrator.tasks.story_scrum_to_done')) {
            $schedule->command('story:scrum-to-done')
                ->timezone('Europe/Rome')
                ->dailyAt('16:00');
        }

        // Sync stories calendar
        if (config('orchestrator.tasks.sync_stories_calendar')) {
            $schedule->command('sync:stories-calendar')
                ->timezone('Europe/Rome')
                ->dailyAt('07:45')
                ->before(function () {
                    Log::info('sync:stories-calendar command starting');
                })
                ->after(function () {
                    Log::info('sync:stories-calendar command finished');
                });
        }

        // Story auto update status
        if (config('orchestrator.tasks.story_auto_update_status')) {
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

        // Process inbound emails
        if (config('orchestrator.tasks.process_inbound_emails')) {
            $schedule
                ->command('orchestrator:process-inbound-emails')
                ->timezone('Europe/Rome')
                ->everyFiveMinutes()
                ->before(function () {
                    Log::info('orchestrator:process-inbound-emails command starting');
                })
                ->after(function () {
                    Log::info('orchestrator:process-inbound-emails command finished');
                });
        }

        // Generate monthly activity reports (runs on the 1st of each month at 12:00 for the previous month)
        if (config('orchestrator.tasks.generate_monthly_activity_reports')) {
            $schedule
                ->command('orchestrator:activity-report-generate')
                ->timezone('Europe/Rome')
                ->monthlyOn(1, '12:00')
                ->before(function () {
                    Log::info('orchestrator:activity-report-generate command starting');
                })
                ->after(function () {
                    Log::info('orchestrator:activity-report-generate command finished');
                });
        }
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
