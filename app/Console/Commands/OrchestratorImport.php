<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use Illuminate\Console\Command;

class OrchestratorImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchestrator:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from WMPM.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {

        // $usersData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/users'), true);
        // $this->importUsers($usersData);

        // $customersData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/customers'), true);
        // $this->importCustomers($customersData);

        // $projectsData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/projects'), true);
        // $this->importProjects($projectsData);

        // $epicsData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/epics'), true);
        // $this->importEpics($epicsData);

        $this->info('Everything imported correctly');
    }

    // private function importUsers($data)
    // {

    //     foreach ($data as $element) {
    //         User::updateOrCreate([
    //             'id' => $element['id']
    //         ], [
    //             'name' => $element['name'],
    //             'email' => $element['email'],
    //             'password' => 'webmapp',
    //             'roles' => UserRole::Admin->value
    //         ]);
    //     }
    // }

    // private function importEpics($data)
    // {

    //     foreach ($data as $element) {
    //         $epicProps = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/epic/' . $element), true);
    //         Epic::updateOrCreate([
    //             'id' => $epicProps['id'],
    //         ], [
    //             'name' => $epicProps['name'],
    //             'description' => $epicProps['description'],
    //             'title' => $epicProps['title'],
    //             'text2stories' => $epicProps['text2stories'],
    //             'notes' => $epicProps['notes'],
    //             'project_id' => $epicProps['project_id'],

    //         ]);
    //     }
    // }

    // private function importProjects($data)
    // {

    //     foreach ($data as $element) {
    //         if (Customer::where('id', $element['customer_id'])->exists()) {
    //             Project::updateOrCreate([
    //                 'id' => $element['id'],

    //             ], [
    //                 'name' => $element['name'],
    //                 'description' => $element['description'],
    //                 'customer_id' => $element['customer_id']

    //             ]);
    //         }
    //     }
    // }

    // private function importCustomers($data)
    // {

    //     foreach ($data as $element) {
    //         Customer::updateOrCreate([
    //             'id' => $element['id']
    //         ], [
    //             'name' => $element['name'],
    //             'notes' => $element['notes'],
    //             'hs_id' => $element['hs_id'],
    //             'domain_name' => $element['domain_name'],
    //             'full_name' => $element['full_name'],
    //             'has_subscription' => $element['has_subscription'],
    //             'subscription_amount' => $element['subscription_amount'],
    //             'subscription_last_payment' => $element['subscription_last_payment'],
    //             'subscription_last_covered_year' => $element['subscription_last_covered_year'],
    //             'subscription_last_invoice' => $element['subscription_last_invoice'],
    //         ]);
    //     }
    // }
}
