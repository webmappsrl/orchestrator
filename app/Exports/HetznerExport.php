<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HetznerExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private readonly array $projects) {}

    public function sheets(): array
    {
        return [
            new HetznerSummarySheet($this->projects),
            new HetznerServersSheet($this->projects),
            new HetznerFloatingIpsSheet($this->projects),
            new HetznerVolumesSheet($this->projects),
            new HetznerLoadBalancersSheet($this->projects),
            new HetznerSnapshotsSheet($this->projects),
        ];
    }
}

// ─── Sheets ───────────────────────────────────────────────────────────────────

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class HetznerSummarySheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly array $projects) {}

    public function title(): string { return 'Riepilogo'; }

    public function headings(): array
    {
        return ['Progetto', 'Status', 'Costo Stimato €/mese', 'Risparmio Potenziale €/mese', '🔴 Risorse critiche', '🟡 Risorse da valutare', '✅ Risorse OK'];
    }

    public function collection(): Collection
    {
        $rows = [];

        foreach ($this->projects as $project) {
            if ($project['status'] === 'error') {
                $rows[] = [$project['slug'], 'ERRORE: ' . $project['error'], 0, 0, 0, 0, 0];
                continue;
            }

            $all = array_merge(
                $project['servers'] ?? [],
                $project['floating_ips'] ?? [],
                $project['volumes'] ?? [],
                $project['load_balancers'] ?? [],
                $project['snapshots'] ?? [],
            );

            $high   = count(array_filter($all, fn($r) => ($r['action_priority'] ?? '') === 'high'));
            $medium = count(array_filter($all, fn($r) => ($r['action_priority'] ?? '') === 'medium'));
            $ok     = count(array_filter($all, fn($r) => ($r['action_priority'] ?? '') === 'ok'));

            $rows[] = [
                $project['slug'],
                'OK',
                $project['monthly_cost_estimate'],
                $project['potential_savings'],
                $high,
                $medium,
                $ok,
            ];
        }

        // Totals row
        $rows[] = [
            'TOTALE',
            '',
            round(array_sum(array_column(array_filter($this->projects, fn($p) => $p['status'] === 'ok'), 'monthly_cost_estimate')), 2),
            round(array_sum(array_column(array_filter($this->projects, fn($p) => $p['status'] === 'ok'), 'potential_savings')), 2),
            '',
            '',
            '',
        ];

        return collect($rows);
    }
}

class HetznerServersSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly array $projects) {}

    public function title(): string { return 'Servers'; }

    public function headings(): array
    {
        return ['Progetto', 'Nome', 'Status', 'Tipo', 'CPU', 'RAM (GB)', 'Disk (GB)', 'Datacenter', 'IPv4', 'IPv4 primario (+€0.50)', 'Backup attivi (+20%)', 'Creato', 'Età (giorni)', '€/mese (listino)', '€/mese (stimato reale)', 'Azione consigliata'];
    }

    public function collection(): Collection
    {
        $rows = [];
        foreach ($this->projects as $project) {
            foreach ($project['servers'] ?? [] as $s) {
                $rows[] = [
                    $project['slug'],
                    $s['name'],
                    $s['status'],
                    $s['type'],
                    $s['cores'],
                    $s['memory_gb'],
                    $s['disk_gb'],
                    $s['datacenter'],
                    $s['ipv4'],
                    ($s['ipv4_assigned'] ?? false) ? 'Sì (+€0.50)' : 'No',
                    ($s['backup_enabled'] ?? false) ? 'Sì (+20%)' : 'No',
                    $s['created_at'],
                    $s['age_days'] ?? '',
                    $s['monthly_price_base'] ?? $s['monthly_price'],
                    $s['monthly_price'],
                    $s['action'] ?? '',
                ];
            }
        }

        return collect($rows);
    }
}

class HetznerFloatingIpsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly array $projects) {}

    public function title(): string { return 'Floating IPs'; }

    public function headings(): array
    {
        return ['Progetto', 'IP', 'Tipo', 'Descrizione', 'Server ID (null = non assegnato)', '€/mese (listino)', 'Azione consigliata'];
    }

    public function collection(): Collection
    {
        $rows = [];
        foreach ($this->projects as $project) {
            foreach ($project['floating_ips'] ?? [] as $ip) {
                $rows[] = [
                    $project['slug'],
                    $ip['ip'],
                    $ip['type'],
                    $ip['description'],
                    $ip['server_id'] ?? 'NON ASSEGNATO',
                    $ip['monthly_price'],
                    $ip['action'] ?? '',
                ];
            }
        }

        return collect($rows);
    }
}

class HetznerVolumesSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly array $projects) {}

    public function title(): string { return 'Volumes'; }

    public function headings(): array
    {
        return ['Progetto', 'Nome', 'Size (GB)', 'Status', 'Server ID (null = non montato)', 'Location', 'Creato', 'Età (giorni)', '€/mese (listino)', 'Azione consigliata'];
    }

    public function collection(): Collection
    {
        $rows = [];
        foreach ($this->projects as $project) {
            foreach ($project['volumes'] ?? [] as $v) {
                $rows[] = [
                    $project['slug'],
                    $v['name'],
                    $v['size_gb'],
                    $v['status'],
                    $v['server_id'] ?? 'NON MONTATO',
                    $v['location'],
                    $v['created_at'],
                    $v['age_days'] ?? '',
                    $v['monthly_price'],
                    $v['action'] ?? '',
                ];
            }
        }

        return collect($rows);
    }
}

class HetznerLoadBalancersSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly array $projects) {}

    public function title(): string { return 'Load Balancers'; }

    public function headings(): array
    {
        return ['Progetto', 'Nome', 'Tipo', 'N° Targets', 'Location', '€/mese (listino)', 'Azione consigliata'];
    }

    public function collection(): Collection
    {
        $rows = [];
        foreach ($this->projects as $project) {
            foreach ($project['load_balancers'] ?? [] as $lb) {
                $rows[] = [
                    $project['slug'],
                    $lb['name'],
                    $lb['type'],
                    $lb['targets_count'],
                    $lb['location'],
                    $lb['monthly_price'],
                    $lb['action'] ?? '',
                ];
            }
        }

        return collect($rows);
    }
}

class HetznerSnapshotsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private readonly array $projects) {}

    public function title(): string { return 'Snapshots'; }

    public function headings(): array
    {
        return ['Progetto', 'Nome', 'Size (GB)', 'Creato', 'Età (giorni)', '€/mese (listino)', 'Azione consigliata'];
    }

    public function collection(): Collection
    {
        $rows = [];
        foreach ($this->projects as $project) {
            foreach ($project['snapshots'] ?? [] as $snap) {
                $rows[] = [
                    $project['slug'],
                    $snap['name'],
                    $snap['size_gb'],
                    $snap['created_at'],
                    $snap['age_days'] ?? '',
                    $snap['monthly_price'],
                    $snap['action'] ?? '',
                ];
            }
        }

        return collect($rows);
    }
}
