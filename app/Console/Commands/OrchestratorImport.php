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
use Illuminate\Support\Facades\DB;

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
        // IMPORT APPS
        $this->importApps();

        // IMPORT LAYERS
        // $layersData = json_decode(file_get_contents('https://geohub.webmapp.it/api/export/layers'), true);
        // $this->importLayers($layersData);

        $this->info('Everything imported correctly');
    }

    private function importApps()
    {
        $this->info('Importing Apps');
        $data = json_decode(file_get_contents('https://geohub.webmapp.it/api/v1/app/all'), true);

        // Backup dei dati da user_app
        $userAppBackup = DB::table('user_app')->get();

        // Cancella i record nella tabella user_app
        DB::table('user_app')->delete();

        // Cancella tutte le app esistenti
        DB::transaction(function () use ($data) {
            App::truncate();

            $tot_apps = count($data);
            $counter = 1;

            foreach ($data as $element) {
                // Converti tutti gli array in JSON
                foreach ($element as $key => $value) {
                    if (is_array($value)) {
                        $element[$key] = json_encode($value);
                    }
                }

                // Controlla la presenza delle chiavi richieste e imposta valori di default se mancano
                $element['fill_color'] = $element['fill_color'] ?? '#000000';
                unset($element['user_id']);

                try {
                    $appID = $element['app_id'];
                    App::updateOrCreate(['app_id' =>  $appID], $element);
                    $this->info("Importing app  $appID ($counter / $tot_apps)");
                } catch (\Exception $e) {
                    $this->error("Error importing app $counter / $tot_apps: " . $e->getMessage());
                }
                $counter++;
            }
        });
        // Ripristino dei dati nella tabella user_app
        foreach ($userAppBackup as $record) {
            $data  = (array)$record;
            try {

                DB::table('user_app')->insert([
                    'user_id' => $data['user_id'],
                    'app_id' => $data['app_id'],
                ]);
            } catch (\Exception $e) {
                $this->error("Error importing user_app: " . $e->getMessage());
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
                'color' => $element['color'],
                'query_string' => $element['query_string'],
                'app_id' => $element['app_id']
            ]);
        }
    }
}
