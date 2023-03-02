<?php

namespace App\Console\Commands;

use App\Models\App;
use App\Models\Epic;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Customer;
use App\Models\Milestone;
use App\Models\Layer;
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
        //IMPORT USERS
        $usersData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/users'), true);
        $this->importUsers($usersData);

        //IMPORT CUSTOMERS
        $customersData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/customers'), true);
        $this->importCustomers($customersData);

        //IMPORT PROJECTS
        $projectsData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/projects'), true);
        $this->importProjects($projectsData);

        //IMPORT APPS
        $appsData = json_decode(file_get_contents('https://geohub.webmapp.it/api/v1/app/all'), true);
        $this->importApps($appsData);

        //IMPORT EPICS
        $epicsData = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/epics'), true);
        $this->importEpics($epicsData);

        //IMPORT LAYERS
        $layersData = json_decode(file_get_contents('https://geohub.webmapp.it/api/export/layers'), true);
        $this->importLayers($layersData);

        $this->info('Everything imported correctly');
    }

    private function importUsers($data)
    {
        $this->info('Importing User');
        foreach ($data as $element) {
            $user = User::where('email', $element['email'])->first();
            if (is_null($user)) {
                $this->info("Creating user with email {$element['email']}");
                User::create([
                    'email' => $element['email'],
                    'name' => $element['name'],
                    'password' => bcrypt('webmapp'),
                    'roles' => UserRole::Admin
                ]);
            } else {
                $this->info("User with email {$element['email']} already exist: skipping.");
            }
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
            $orchestrator_customer_id = Customer::where('wmpm_id', $element['customer_id'])->first()->id;
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

        $user_team = User::updateOrCreate(
            ['email' => 'team@webmapp.it'],
            [
                'name' => 'Admin Webmapp',
                'password' => bcrypt('webmapp'),
                'roles' => [UserRole::Admin],
            ]
        );

        $milestone_2022 = Milestone::updateOrCreate(['name' => '2022']);

        $customer_unknown = Customer::updateOrCreate(['name' => 'Unknown']);
        $project_unknown = Project::updateOrCreate(['name' => 'Unknown'], ['customer_id' => $customer_unknown->id]);

        $tot_epics = count($data);
        $counter = 1;

        foreach ($data as $element) {
            $this->info("Importing epic $counter / $tot_epics");
            $counter++;

            $epicProps = json_decode(file_get_contents('https://wmpm.webmapp.it/api/export/epic/' . $element), true);

            $orchestrator_project = Project::where('wmpm_id', $epicProps['project_id'])->first();
            $orchestrator_project_id = is_null($orchestrator_project) ?  $project_unknown->id : $orchestrator_project->id;
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
                    'project_id' => $orchestrator_project_id,
                    'user_id' => $user_team->id,
                    'milestone_id' => $milestone_2022->id,
                ]
            );
        }
    }

    private function importApps($data)
    {
        $this->info('Importing Apps');
        $tot_apps = count($data);
        $counter = 1;

        foreach ($data as $element) {
            unset($element['user_id']);
            $element['map_bbox'] = implode(',', json_decode($element['map_bbox'], true));
            $element['tiles'] = json_encode($element['tiles'], true);
            $element['name'] = json_encode($element['name']);
            $this->info("Importing app $counter / $tot_apps");
            $counter++;

            $app = App::where('app_id', $element['app_id'])->first();
            if (is_null($app)) {
                App::create($element);
            } else {
                $app->update($element);
            }
        }
    }

    private function importLayers($data)
    {
        $this->info('Importing Layers');
        foreach ($data as $element) {
            Layer::updateOrCreate([
                'geohub_id' => $element['id'],
            ], [
                'name' => $element['name'],
                'title' => $element['title'],
                'description' => $element['description'],
                'color' => $element['color'],
                'query_string' => $element['query_string'],
                'app_id' => $element['app_id']
            ]);
        }
    }
}