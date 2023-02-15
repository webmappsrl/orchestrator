<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
class DownloadDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:download';

    protected $appName;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'download a dump.sql from server';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->appName = config('app.name');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('db:download -> is started');
        $fileName = "last-dump.sql.gz";
        $lastDumpRemotePath = $this->signature . '/$fileName';
        $localDirectory = "database";
        $localRootPath = "storage/app";
        $lastDumpLocalPath = "$localDirectory/$fileName";

        $wmdumps = Storage::disk('wmdumps');
        $local = Storage::disk('local');

        if (!$wmdumps->exists($lastDumpRemotePath)) {
            Log::error("db:download -> $lastDumpRemotePath does not exist");
            throw new Exception("db:download -> $lastDumpRemotePath does not exist");
        }

        Log::info('db:download -> START last-dump');
        $lastDump = $wmdumps->get($lastDumpRemotePath);

        if (!$lastDump) {
            Log::error("db:download -> $lastDumpRemotePath download error");
            throw new Exception("db:download -> $lastDumpRemotePath download error");
        }
        Log::info('db:download -> DONE last-dump');

        $local->makeDirectory($localDirectory);
        $local->put($lastDumpLocalPath, $lastDump);
        Log::info('db:download -> START unzip last-dump');
        $GzAbsolutePath = base_path() . "/$localRootPath/$lastDumpLocalPath";
        if (!file_exists($GzAbsolutePath)) {
            Log::error('db:download -> download last-dump.sql.gz FAILED');
            throw new Exception('db:download -> download last-dump.sql.gz FAILED');
        }

        exec("gunzip $GzAbsolutePath  -f");

        $AbsolutePath = base_path() . "/$localRootPath/$localDirectory/last-dump.sql";
        if (!file_exists($AbsolutePath)) {
            Log::error('db:download -> download dump.sql FAILED');
            throw new Exception('db:download -> download dump.sql FAILED');
        }
        Log::info('db:download -> DONE unzip last-dump');
        Log::info('db:download -> finished');
        return 0;
    }
}
