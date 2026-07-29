<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillCustomerVatFromHeading extends Command
{
    protected $signature = 'customers:backfill-vat
                            {--apply : Esegue realmente l\'update (default: dry-run, nessuna scrittura)}';

    protected $description = 'Estrae la Partita IVA dal campo heading (testo libero) dei customer con vat vuoto.
                              Dry-run di default: salva sempre un report su storage/app prima di ogni --apply,
                              nessun rollback automatico oltre a quel report.';

    public function handle(): int
    {
        $customers = Customer::whereNotNull('heading')
            ->where('heading', '!=', '')
            ->whereNull('vat')
            ->get(['id', 'name', 'heading']);

        $report = $customers->map(fn(Customer $customer) => [
            'id'            => $customer->id,
            'name'          => $customer->name,
            'vat_extracted' => $this->extractVat($customer->heading),
        ]);

        $matched = $report->filter(fn($row) => $row['vat_extracted'] !== null)->values();

        $this->table(
            ['ID', 'Name', 'VAT estratto'],
            $matched->map(fn($row) => [$row['id'], substr($row['name'], 0, 40), $row['vat_extracted']])
        );
        $this->info("Match trovati: {$matched->count()} / {$customers->count()} customer con heading e vat vuoto");

        $reportPath = 'customer-vat-backfill/' . now()->format('Y-m-d_His') . '.json';
        $saved = Storage::disk('local')->put($reportPath, json_encode($report->values()->all(), JSON_PRETTY_PRINT));

        if ($saved === false) {
            $this->error("Impossibile salvare il report in storage/app/{$reportPath}. Interrotto senza eseguire alcuna scrittura sul DB.");
            return self::FAILURE;
        }

        $this->info("Report salvato in storage/app/{$reportPath}");

        if (!$this->option('apply')) {
            $this->comment('Dry-run: nessuna scrittura eseguita. Rilancia con --apply per applicare.');
            return self::SUCCESS;
        }

        foreach ($matched as $row) {
            Customer::whereKey($row['id'])->update(['vat' => $row['vat_extracted']]);
        }

        $this->info("Aggiornati {$matched->count()} customer.");

        return self::SUCCESS;
    }

    /**
     * Match "Partita Iva"/"P.IVA" first (11-digit VAT number). Fall back to
     * "C.F." only when it is exactly 11 numeric digits (a company VAT often
     * doubles as C.F.) — an alphanumeric 16-char C.F. is a private
     * individual's fiscal code, not a VAT number, and must NOT be used.
     */
    private function extractVat(string $heading): ?string
    {
        if (preg_match('/(?:Partita\s*Iva|P\.?\s*IVA|Numero\s*partita\s*IVA)[:\s\/]*([0-9]{11})\b/iu', $heading, $matches)) {
            return $matches[1];
        }

        if (preg_match('/C\.?F\.?[:\s]*([0-9]{11})\b/iu', $heading, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
