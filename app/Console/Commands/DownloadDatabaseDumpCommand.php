<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadDatabaseDumpCommand extends Command
{
    protected $signature = 'db:download
                            {--object= : Chiave S3 completa sul disco wmdumps (es. maphub/orchestrator/last-dump.sql.gz)}
                            {--s3 : Usa AWS S3 reale (rimuove endpoint MinIO dal disco wmdumps)}';

    protected $description = 'Scarica last-dump.sql.gz da wmdumps (maphub/{app}/) in storage/backups/last_dump.sql.gz';

    public function handle(): int
    {
        if ($this->option('s3')) {
            $wmdumpsConfig = config('filesystems.disks.wmdumps', []);
            $wmdumpsConfig['endpoint'] = null;
            $wmdumpsConfig['region'] = env('AWS_DUMPS_DEFAULT_REGION', 'eu-central-1');
            Config::set('filesystems.disks.wmdumps', $wmdumpsConfig);
            app()->forgetInstance('filesystem.disk.wmdumps');
            $this->comment('Endpoint MinIO disattivato, regione: '.$wmdumpsConfig['region']);
        }

        $disk = Storage::disk('wmdumps');

        $remoteKey = $this->option('object');
        if (! $remoteKey) {
            $prefix = env('WMDUMPS_DUMP_PREFIX', 'maphub');
            $appSegment = Str::slug((string) config('app.name'));
            $remoteKey = rtrim($prefix, '/').'/'.$appSegment.'/last-dump.sql.gz';
        }

        $this->info("Download da wmdumps: {$remoteKey}");

        if (! $disk->exists($remoteKey)) {
            $this->error("Oggetto non trovato sul bucket: {$remoteKey}");
            Log::error('db:download -> remote object missing', ['key' => $remoteKey]);

            return Command::FAILURE;
        }

        $contents = $disk->get($remoteKey);
        if ($contents === null || $contents === '') {
            $this->error('Lettura oggetto S3 fallita o file vuoto.');
            Log::error('db:download -> empty or null body', ['key' => $remoteKey]);

            return Command::FAILURE;
        }

        $localDir = storage_path('backups');
        if (! is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        $dest = $localDir.'/last_dump.sql.gz';
        file_put_contents($dest, $contents);

        $this->info('Salvato in '.$dest);
        Log::info('db:download -> done', ['bytes' => strlen($contents), 'dest' => $dest]);

        return Command::SUCCESS;
    }
}
