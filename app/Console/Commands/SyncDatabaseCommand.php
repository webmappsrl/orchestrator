<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SyncDatabaseCommand extends Command
{
    protected $signature = 'db:sync
                            {--s3 : Scarica da AWS S3 reale (ignora endpoint MinIO, come db:download --s3)}';

    protected $description = 'Scarica il dump di produzione da AWS S3 e lo ripristina nel database locale.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('db:sync non può essere eseguito in produzione.');
            Log::error('db:sync -> refused to run in production environment');
            return 1;
        }

        $this->log('db:sync -> is started');

        $this->log('db:sync -> START download (db:download)');
        $downloadOptions = [];
        if ($this->option('s3')) {
            $downloadOptions['--s3'] = true;
        }
        $downloadResult = Artisan::call('db:download', $downloadOptions);
        if ($downloadResult !== 0) {
            $this->log('db:sync -> download FAILED', 'error');
            return 1;
        }
        $this->log('db:sync -> DONE download');

        $dumpPath = storage_path('backups/last_dump.sql.gz');
        if (! file_exists($dumpPath)) {
            $this->log('db:sync -> dump file not found at ' . $dumpPath, 'error');
            return 1;
        }

        $this->log('db:sync -> START restore database');

        $db = config('database.connections.pgsql');
        $env = ['PGPASSWORD' => $db['password']];
        $host = $db['host'];
        $port = $db['port'];
        $user = $db['username'];
        $dbname = $db['database'];

        // Drop e ricrea il database per un restore pulito
        $drop = Process::fromShellCommandline(
            "psql -U {$user} -h {$host} -p {$port} -d postgres -c \"DROP DATABASE IF EXISTS {$dbname};\""
        );
        $drop->setEnv($env);
        $drop->run();
        if (!$drop->isSuccessful()) {
            $this->log('db:sync -> drop FAILED: ' . $drop->getErrorOutput(), 'error');
            return 1;
        }

        $create = Process::fromShellCommandline(
            "psql -U {$user} -h {$host} -p {$port} -d postgres -c \"CREATE DATABASE {$dbname} OWNER {$user};\""
        );
        $create->setEnv($env);
        $create->run();
        if (!$create->isSuccessful()) {
            $this->log('db:sync -> create FAILED: ' . $create->getErrorOutput(), 'error');
            return 1;
        }

        $process = Process::fromShellCommandline(
            'gunzip -c '.escapeshellarg($dumpPath).' | psql -U '.$user.' -h '.$host.' -p '.$port.' -d '.$dbname
        );
        $process->setTimeout(600);
        $process->setEnv($env);
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
