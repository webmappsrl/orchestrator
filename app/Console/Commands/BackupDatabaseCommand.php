<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Crea un dump fresco del database e lo carica su AWS S3.';

    public function handle(): int
    {
        if (!app()->environment('production')) {
            $this->error('db:backup può essere eseguito solo in produzione. Usa --force per testare.');
            Log::error('db:backup -> refused to run outside production environment');
            return 1;
        }

        $this->log('db:backup -> is started');

        $db = config('database.connections.pgsql');
        $dumpPath = storage_path('backups/last-dump.sql.gz');

        // Assicura che la directory esista
        if (!is_dir(dirname($dumpPath))) {
            mkdir(dirname($dumpPath), 0755, true);
        }

        $this->log('db:backup -> START pg_dump');

        $process = Process::fromShellCommandline(
            "pg_dump -U {$db['username']} -h {$db['host']} -p {$db['port']} {$db['database']} | gzip > {$dumpPath}"
        );
        $process->setTimeout(600);
        $process->setEnv(['PGPASSWORD' => $db['password']]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->log('db:backup -> pg_dump FAILED: ' . $process->getErrorOutput(), 'error');
            return 1;
        }

        $this->log('db:backup -> DONE pg_dump');

        $this->log('db:backup -> START upload to AWS');
        $uploadResult = Artisan::call('db:upload_db_aws');
        if ($uploadResult !== 0) {
            $this->log('db:backup -> upload FAILED', 'error');
            return 1;
        }
        $this->log('db:backup -> DONE upload to AWS');

        $this->log('db:backup -> finished');

        return 0;
    }

    protected function log(string $message, string $type = 'info'): void
    {
        Log::$type($message);
        $this->$type($message);
    }
}
