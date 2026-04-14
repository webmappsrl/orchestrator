<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SyncDatabaseCommand extends Command
{
    protected $signature = 'db:sync';

    protected $description = 'Scarica il dump di produzione da AWS S3 e lo ripristina nel database locale.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('db:sync non può essere eseguito in produzione.');
            Log::error('db:sync -> refused to run in production environment');
            return 1;
        }

        $this->log('db:sync -> is started');

        $this->log('db:sync -> START download from AWS');
        $downloadResult = Artisan::call('db:download');
        if ($downloadResult !== 0) {
            $this->log('db:sync -> download FAILED', 'error');
            return 1;
        }
        $this->log('db:sync -> DONE download from AWS');

        $dumpPath = storage_path('app/database/last-dump.sql.gz');
        if (!file_exists($dumpPath)) {
            $this->log('db:sync -> dump file not found at ' . $dumpPath, 'error');
            return 1;
        }

        $this->log('db:sync -> START restore database');

        $db = config('database.connections.pgsql');

        $process = Process::fromShellCommandline(
            "gunzip -c {$dumpPath} | psql -U {$db['username']} -h {$db['host']} -p {$db['port']} -d {$db['database']}"
        );
        $process->setTimeout(600);
        $process->setEnv(['PGPASSWORD' => $db['password']]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->log('db:sync -> restore FAILED: ' . $process->getErrorOutput(), 'error');
            return 1;
        }

        $this->log('db:sync -> DONE restore database');
        $this->log('db:sync -> finished');

        return 0;
    }

    protected function log(string $message, string $type = 'info'): void
    {
        Log::$type($message);
        $this->$type($message);
    }
}
