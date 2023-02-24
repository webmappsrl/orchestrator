<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Models\Customer;
use App\Models\Milestone;
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

        $usersData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/users'), true);
        $this->importUsers($usersData);

        $customersData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/customers'), true);
        $this->importCustomers($customersData);

        $projectsData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/projects'), true);
        $this->importProjects($projectsData);

        // $epicsData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/epics'), true);
        // $this->importEpics($epicsData);

        $this->info('Everything imported correctly');
    }

    private function importUsers($data)
    {
        $this->info('Importing User');
        foreach ($data as $element) {
            User::updateOrCreate(
                [
                    'email' => $element['email'],
                ],
                [
                    'name' => $element['name'],
                    'password' => bcrypt('webmapp'),
                    'roles' => UserRole::Admin
                ]
            );
        }
    }

    private function importCustomers($data)
    {
        $this->info('Importing Customers');
        foreach ($data as $element) {
            Customer::updateOrCreate([
                'wmpm_id' => $element['id']
            ], [
                'name' => $element['name'],
                'notes' => $element['notes'],
                'hs_id' => $element['hs_id'],
                'domain_name' => $element['domain_name'],
                'full_name' => $element['full_name'],
                'has_subscription' => $element['has_subscription'],
                'subscription_amount' => $element['subscription_amount'],
                'subscription_last_payment' => $element['subscription_last_payment'],
                'subscription_last_covered_year' => $element['subscription_last_covered_year'],
                'subscription_last_invoice' => $element['subscription_last_invoice'],
            ]);
        }
    }

    private function importProjects($data)
    {
        $this->info('Importing Projects');
        foreach ($data as $element) {
            $orchestrator_customer_id=Customer::where('wmpm_id', $element['customer_id'])->first()->id();
            Project::updateOrCreate([
                'wmpm_id' => $element['id'],

            ], [
                'name' => $element['name'],
                'description' => $element['description'],
                'customer_id' => $orchestrator_customer_id

            ]);
        }
    }

    private function importEpics($data)
    {
        $this->info('Importing Epics');

        $user_team = User::updateOrCreate(['email'=>'team@webmapp.it'],
                            [
                                'name' => 'Admin Webmapp',
                                'password' => bcrypt('webmapp'),
                                'roles' => [UserRole::Admin],
                            ]);

        $milestone_2022 = Milestone::updateOrCreate(['name'=>'2022']);

        $tot_epics = count($data);
        $counter = 1;

        foreach ($data as $element) {
            $this->info("Importing epic $counter / $tot_epics");
            $counter++;

            $epicProps = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/epic/' . $element), true);
            Epic::updateOrCreate(
                [
                    'wmpm_id' => $epicProps['id'],

                ],
                [
                    'name' => $epicProps['name'],
                    'description' => $epicProps['description'],
                    'title' => $epicProps['title'],
                    'text2stories' => $epicProps['text2stories'],
                    'notes' => $epicProps['notes'],
                    'project_id' => $epicProps['project_id'],
                    'user_id' => $user_team->id,
                    'milestone_id' => $milestone_2022->id,
                ]
            );
        }
    }
}
