<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
class RestoreDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:restore';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore a last-dump.sql file (must be in root dir)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info("db:restore -> is started");
        $localDirectory = "database";
        $localRootPath = "storage/app";
        $AbsolutePath = base_path() . "/$localRootPath/$localDirectory/last-dump.sql";

        if (!file_exists($AbsolutePath)) {
            try {
                Artisan::call('db:download');
            } catch (Exception $e) {
                echo $e;
                return 0;
            }
        }

        $db_name = config('database.connections.pgsql.database');

        // psql -c "DROP DATABASE geohub"
        $drop_cmd = 'psql -c "DROP DATABASE '.$db_name.'"';
        Log::info("db:restore -> $drop_cmd");
        exec($drop_cmd);
        
        // psql -c "CREATE DATABASE geohub"
        $create_cmd = 'psql -c "CREATE DATABASE '.$db_name.'"';
        Log::info("db:restore -> $create_cmd");
        exec($create_cmd);

        // psql -d geohub -c "create extension postgis"
        $postgis_cmd = 'psql -d '.$db_name.' -c "create extension postgis";';
        Log::info("db:restore -> $postgis_cmd");
        exec($postgis_cmd);

        // psql geohub < last-dump.sql
        $restore_cmd = "psql $db_name < $AbsolutePath";
        Log::info("db:restore -> $restore_cmd");
        exec($restore_cmd);

        Log::info("db:restore -> finished");
        return 0;
    }
}