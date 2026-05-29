<?php

namespace App\Exports;

use App\Exports\Concerns\HetznerSheetHelpers;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class HetznerExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private readonly array $projects) {}

    public function sheets(): array
    {
        return [
            new HetznerSummarySheet($this->projects),
            new HetznerAllResourcesSheet($this->projects),
            new HetznerActionableSheet($this->projects),
            new HetznerServersSheet($this->projects),
            new HetznerFloatingIpsSheet($this->projects),
            new HetznerVolumesSheet($this->projects),
            new HetznerLoadBalancersSheet($this->projects),
            new HetznerSnapshotsSheet($this->projects),
        ];
    }
}

abstract class AbstractHetznerSheet implements FromCollection, WithHeadings, WithTitle
{
    use HetznerSheetHelpers;

    public function __construct(protected readonly array $projects) {}
}

class HetznerSummarySheet extends AbstractHetznerSheet
{
    public function title(): string
    {
        return 'Riepilogo';
    }

    public function headings(): array
    {
        return ['Progetto', 'Status', 'Costo Stimato €/mese', 'Risparmio Potenziale €/mese', 'Risorse critiche', 'Risorse da valutare', 'Risorse OK'];
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

            $high   = count(array_filter($all, fn ($r) => ($r['action_priority'] ?? '') === 'high'));
            $medium = count(array_filter($all, fn ($r) => ($r['action_priority'] ?? '') === 'medium'));
            $ok     = count(array_filter($all, fn ($r) => ($r['action_priority'] ?? '') === 'ok'));

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

        $rows[] = [
            'TOTALE',
            '',
            round(array_sum(array_column(array_filter($this->projects, fn ($p) => $p['status'] === 'ok'), 'monthly_cost_estimate')), 2),
            round(array_sum(array_column(array_filter($this->projects, fn ($p) => $p['status'] === 'ok'), 'potential_savings')), 2),
            '',
            '',
            '',
        ];

        return collect($rows);
    }
}

class HetznerAllResourcesSheet extends AbstractHetznerSheet
{
    public function title(): string
    {
        return 'Tutto';
    }

    public function headings(): array
    {
        return $this->unifiedHeadings();
    }

    public function collection(): Collection
    {
        return collect($this->collectUnifiedRows());
    }
}

class HetznerActionableSheet extends AbstractHetznerSheet
{
    public function title(): string
    {
        return 'Azioni da fare';
    }

    public function headings(): array
    {
        return $this->unifiedHeadings();
    }

    public function collection(): Collection
    {
        return collect($this->collectUnifiedRows(fn (array $r) => $this->appearsInActionableSheet($r)));
    }
}

class HetznerServersSheet extends AbstractHetznerSheet
{
    public function title(): string
    {
        return 'Servers';
    }

    public function headings(): array
    {
        return [
            'Progetto',
            ...$this->priorityActionNoteHeadings(),
            'Nome',
            'Status',
            'Tipo',
            'CPU',
            'RAM (GB)',
            'Disk (GB)',
            'Datacenter',
            'IPv4',
            'IPv4 primario (+€0.50)',
            'Backup attivi (+20%)',
            'Creato',
            'Età (giorni)',
            '€/mese (listino)',
            '€/mese (stimato reale)',
        ];
    }

    public function collection(): Collection
    {
        $rows = [];

        foreach ($this->projects as $project) {
            foreach ($project['servers'] ?? [] as $s) {
                $rows[] = [
                    $project['slug'],
                    ...$this->priorityActionNoteColumns($s),
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
                ];
            }
        }

        return collect($rows);
    }
}

class HetznerFloatingIpsSheet extends AbstractHetznerSheet
{
    public function title(): string
    {
        return 'Floating IPs';
    }

    public function headings(): array
    {
        return [
            'Progetto',
            ...$this->priorityActionNoteHeadings(),
            'IP',
            'Tipo',
            'Descrizione',
            'Server ID (null = non assegnato)',
            '€/mese (listino)',
        ];
    }

    public function collection(): Collection
    {
        $rows = [];

        foreach ($this->projects as $project) {
            foreach ($project['floating_ips'] ?? [] as $ip) {
                $rows[] = [
                    $project['slug'],
                    ...$this->priorityActionNoteColumns($ip),
                    $ip['ip'],
                    $ip['type'],
                    $ip['description'],
                    $ip['server_id'] ?? 'NON ASSEGNATO',
                    $ip['monthly_price'],
                ];
            }
        }

        return collect($rows);
    }
}

class HetznerVolumesSheet extends AbstractHetznerSheet
{
    public function title(): string
    {
        return 'Volumes';
    }

    public function headings(): array
    {
        return [
            'Progetto',
            ...$this->priorityActionNoteHeadings(),
            'Nome',
            'Size (GB)',
            'Status',
            'Server ID (null = non montato)',
            'Location',
            'Creato',
            'Età (giorni)',
            '€/mese (listino)',
        ];
    }

    public function collection(): Collection
    {
        $rows = [];

        foreach ($this->projects as $project) {
            foreach ($project['volumes'] ?? [] as $v) {
                $rows[] = [
                    $project['slug'],
                    ...$this->priorityActionNoteColumns($v),
                    $v['name'],
                    $v['size_gb'],
                    $v['status'],
                    $v['server_id'] ?? 'NON MONTATO',
                    $v['location'],
                    $v['created_at'],
                    $v['age_days'] ?? '',
                    $v['monthly_price'],
                ];
            }
        }

        return collect($rows);
    }
}

class HetznerLoadBalancersSheet extends AbstractHetznerSheet
{
    public function title(): string
    {
        return 'Load Balancers';
    }

    public function headings(): array
    {
        return [
            'Progetto',
            ...$this->priorityActionNoteHeadings(),
            'Nome',
            'Tipo',
            'N° Targets',
            'Location',
            '€/mese (listino)',
        ];
    }

    public function collection(): Collection
    {
        $rows = [];

        foreach ($this->projects as $project) {
            foreach ($project['load_balancers'] ?? [] as $lb) {
                $rows[] = [
                    $project['slug'],
                    ...$this->priorityActionNoteColumns($lb),
                    $lb['name'],
                    $lb['type'],
                    $lb['targets_count'],
                    $lb['location'],
                    $lb['monthly_price'],
                ];
            }
        }

        return collect($rows);
    }
}

class HetznerSnapshotsSheet extends AbstractHetznerSheet
{
    public function title(): string
    {
        return 'Snapshots';
    }

    public function headings(): array
    {
        return [
            'Progetto',
            ...$this->priorityActionNoteHeadings(),
            'Nome',
            'Size (GB)',
            'Creato',
            'Età (giorni)',
            '€/mese (listino)',
        ];
    }

    public function collection(): Collection
    {
        $rows = [];

        foreach ($this->projects as $project) {
            foreach ($project['snapshots'] ?? [] as $snap) {
                $rows[] = [
                    $project['slug'],
                    ...$this->priorityActionNoteColumns($snap),
                    $snap['name'],
                    $snap['size_gb'],
                    $snap['created_at'],
                    $snap['age_days'] ?? '',
                    $snap['monthly_price'],
                ];
            }
        }

        return collect($rows);
    }
}
