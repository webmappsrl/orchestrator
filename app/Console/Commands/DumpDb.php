<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Exceptions\CannotStartDump;
use Spatie\DbDumper\Exceptions\DumpFailed;

class DumpDb extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:dump_db';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new sql file exporting all the current database in the local disk under the `database` directory';


    protected $appName;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
        $this->appName = config('app.name');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {
        try {
            $this->log($this->signature . '-> is started');
            $wmdumps = Storage::disk('wmdumps');
            $local = Storage::disk('local');
            $local->makeDirectory('database');
            $dumpName = 'dump_' . date('Y-M-d_h-m-s') . '.sql';
            $dumpFileName = $local->path('database/' . $dumpName);
            PostgreSql::create()
                ->setDbName(config('database.connections.pgsql.database'))
                ->setUserName(config('database.connections.pgsql.username'))
                ->setPassword(config('database.connections.pgsql.password'))
                ->dumpToFile($dumpFileName);
            $this->log($this->signature . '-> Database dump created successfully in ' . $dumpFileName);
            if (!$local->exists('database/' . $dumpName)) {
            }
            exec("gzip $dumpFileName  -f");
            $lastLocalDump = $local->get('database/' . $dumpName . '.gz');
            $local->delete('database/' . $dumpName . '.gz');

            $this->log($this->signature . '-> START upload to aws');
            $wmdumps->put( $this->appName .'/' . $dumpName . '.gz', $lastLocalDump);
            $this->log($this->signature . '-> DONE upload to aws');
            //TODO: CREATE LAST DUMP ON REMOTE
            $this->log($this->signature . '-> START create last-dump to aws');
            $wmdumps->put( $this->appName . '/last-dump.sql.gz', $lastLocalDump);
            $this->log($this->signature . '-> DONE create last-dump to aws');

            $this->log($this->signature . '-> finished');
            return 0;
        } catch (CannotStartDump $e) {
            $this->log($this->signature . '-> The dump process cannot be initialized: ' . $e->getMessage(),'error');
            $this->log($this->signature . '-> Make sure to clear the config cache when changing the environment: `php artisan config:cache`','error');

            return 2;
        } catch (DumpFailed $e) {
            $this->log($this->signature . '-> Error while creating the database dump: ' . $e->getMessage(),'error');

            return 1;
        }
    }

    protected function log( $message , $type = 'info')
    {
        Log::$type($message);
        $this->$type($message);
    }
}
