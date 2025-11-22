<?php

namespace App\Console\Commands;

use App\Enums\OwnerType;
use App\Enums\UserRole;
use App\Jobs\GenerateActivityReportPdfJob;
use App\Models\Organization;
use App\Models\Story;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyActivityReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchestrator:activity-report-generate 
                            {--year= : The year for the report (defaults to previous month)}
                            {--month= : The month for the report (defaults to previous month)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly activity reports for all customers and organizations with at least one ticket in the specified month';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Determine year and month (default to previous month)
        $year = $this->option('year');
        $month = $this->option('month');

        if (!$year || !$month) {
            $previousMonth = Carbon::now()->subMonth();
            $year = $year ?? $previousMonth->year;
            $month = $month ?? $previousMonth->month;
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $this->info(sprintf('Generating monthly activity reports for %s/%d...', $month, $year));

        $jobsDispatched = 0;

        // Process customers
        $this->info('Processing customers...');
        $customers = User::whereJsonContains('roles', UserRole::Customer->value)->get();
        
        foreach ($customers as $customer) {
            // Check if customer has at least one ticket with done_at in the period
            $hasTickets = Story::where('creator_id', $customer->id)
                ->whereNotNull('done_at')
                ->whereBetween('done_at', [$startDate, $endDate])
                ->exists();

            if ($hasTickets) {
                GenerateActivityReportPdfJob::dispatch(
                    OwnerType::Customer,
                    $customer->id,
                    null,
                    $year,
                    $month
                );
                $jobsDispatched++;
                $this->line(sprintf('  - Dispatched job for customer: %s (ID: %d)', $customer->name, $customer->id));
            }
        }

        // Process organizations
        $this->info('Processing organizations...');
        $organizations = Organization::all();
        
        foreach ($organizations as $organization) {
            // Check if organization has at least one ticket with done_at in the period
            // (tickets created by users belonging to the organization)
            $hasTickets = Story::whereNotNull('done_at')
                ->whereBetween('done_at', [$startDate, $endDate])
                ->whereHas('creator.organizations', function ($q) use ($organization) {
                    $q->where('organizations.id', $organization->id);
                })
                ->exists();

            if ($hasTickets) {
                GenerateActivityReportPdfJob::dispatch(
                    OwnerType::Organization,
                    null,
                    $organization->id,
                    $year,
                    $month
                );
                $jobsDispatched++;
                $this->line(sprintf('  - Dispatched job for organization: %s (ID: %d)', $organization->name, $organization->id));
            }
        }

        $this->info(sprintf('Dispatched %d jobs for activity report generation.', $jobsDispatched));
        $this->info('Reports will be generated in the background queue.');
    }
}

