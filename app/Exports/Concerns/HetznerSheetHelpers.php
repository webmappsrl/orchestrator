<?php

namespace App\Exports\Concerns;

trait HetznerSheetHelpers
{
    private const RESOURCE_TYPES = [
        'servers'        => ['label' => 'Server', 'key' => 'server'],
        'floating_ips'   => ['label' => 'Floating IP', 'key' => 'floating_ip'],
        'volumes'        => ['label' => 'Volume', 'key' => 'volume'],
        'load_balancers' => ['label' => 'Load Balancer', 'key' => 'load_balancer'],
        'snapshots'      => ['label' => 'Snapshot', 'key' => 'snapshot'],
    ];

    protected function priorityActionNoteHeadings(): array
    {
        return ['Priorità', 'Azione consigliata', 'Nota', 'Nota autore', 'Nota aggiornata'];
    }

    protected function priorityActionNoteColumns(array $resource): array
    {
        $note = $resource['note'] ?? null;

        return [
            $resource['action_priority'] ?? '',
            $resource['action'] ?? '',
            $note['text'] ?? '',
            $note['user_name'] ?? '',
            $note['updated_at'] ?? '',
        ];
    }

    /**
     * @return \Generator<int, array{0: string, 1: string, 2: array}>
     */
    protected function iterateResources(array $projects, ?callable $filter = null): \Generator
    {
        foreach ($projects as $project) {
            if (($project['status'] ?? '') !== 'ok') {
                continue;
            }

            $slug = $project['slug'];

            foreach (self::RESOURCE_TYPES as $collectionKey => $meta) {
                foreach ($project[$collectionKey] ?? [] as $resource) {
                    if ($filter !== null && ! $filter($resource)) {
                        continue;
                    }

                    yield [$slug, $meta['label'], $resource];
                }
            }
        }
    }

    protected function prioritySortValue(string $priority): int
    {
        return match ($priority) {
            'high'   => 0,
            'medium' => 1,
            default  => 2,
        };
    }

    protected function hasNote(array $resource): bool
    {
        $note = $resource['note'] ?? null;

        return ! empty($note['text']);
    }

    protected function appearsInActionableSheet(array $resource): bool
    {
        return $this->needsAction($resource) || $this->hasNote($resource);
    }

    protected function needsAction(array $resource): bool
    {
        return ($resource['action_priority'] ?? 'ok') !== 'ok';
    }

    protected function resourceIdentifier(array $resource): string
    {
        return (string) ($resource['name'] ?? $resource['ip'] ?? $resource['id'] ?? '');
    }

    protected function buildResourceDetails(string $typeLabel, array $resource): string
    {
        return match ($typeLabel) {
            'Server' => implode(' | ', array_filter([
                'status: ' . ($resource['status'] ?? ''),
                'tipo: ' . ($resource['type'] ?? ''),
                ($resource['cores'] ?? '') . ' CPU',
                ($resource['memory_gb'] ?? '') . ' GB RAM',
                ($resource['disk_gb'] ?? '') . ' GB disk',
                'DC: ' . ($resource['datacenter'] ?? ''),
                'IPv4: ' . ($resource['ipv4'] ?? ''),
                ($resource['backup_enabled'] ?? false) ? 'backup attivo' : null,
                ($resource['ipv4_assigned'] ?? false) ? 'IPv4 primario' : null,
                isset($resource['age_days']) ? 'età: ' . $resource['age_days'] . ' gg' : null,
            ])),
            'Floating IP' => implode(' | ', array_filter([
                'tipo: ' . ($resource['type'] ?? ''),
                'desc: ' . ($resource['description'] ?? ''),
                'server: ' . ($resource['server_id'] ?? 'NON ASSEGNATO'),
            ])),
            'Volume' => implode(' | ', array_filter([
                ($resource['size_gb'] ?? '') . ' GB',
                'status: ' . ($resource['status'] ?? ''),
                'server: ' . ($resource['server_id'] ?? 'NON MONTATO'),
                'location: ' . ($resource['location'] ?? ''),
                isset($resource['age_days']) ? 'età: ' . $resource['age_days'] . ' gg' : null,
            ])),
            'Load Balancer' => implode(' | ', array_filter([
                'tipo: ' . ($resource['type'] ?? ''),
                'targets: ' . ($resource['targets_count'] ?? 0),
                'location: ' . ($resource['location'] ?? ''),
            ])),
            'Snapshot' => implode(' | ', array_filter([
                ($resource['size_gb'] ?? '') . ' GB',
                isset($resource['age_days']) ? 'età: ' . $resource['age_days'] . ' gg' : null,
            ])),
            default => '',
        };
    }

    protected function buildUnifiedRow(string $projectSlug, string $typeLabel, array $resource): array
    {
        return [
            $projectSlug,
            $typeLabel,
            $resource['id'] ?? '',
            $this->resourceIdentifier($resource),
            ...$this->priorityActionNoteColumns($resource),
            $resource['monthly_price'] ?? '',
            $this->buildResourceDetails($typeLabel, $resource),
        ];
    }

    protected function unifiedHeadings(): array
    {
        return [
            'Progetto',
            'Tipo risorsa',
            'ID',
            'Identificativo',
            ...$this->priorityActionNoteHeadings(),
            '€/mese stimato',
            'Dettagli',
        ];
    }

    /**
     * @return list<array<int|string>>
     */
    protected function collectUnifiedRows(?callable $filter = null): array
    {
        $rows = [];

        foreach ($this->iterateResources($this->projects, $filter) as [$slug, $typeLabel, $resource]) {
            $rows[] = [
                'row'      => $this->buildUnifiedRow($slug, $typeLabel, $resource),
                'priority' => $this->prioritySortValue($resource['action_priority'] ?? 'ok'),
                'slug'     => $slug,
                'type'     => $typeLabel,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a['priority'], $a['slug'], $a['type']]
                <=> [$b['priority'], $b['slug'], $b['type']];
        });

        return array_column($rows, 'row');
    }
}
