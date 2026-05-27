<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HetznerApiService
{
    private const BASE_URL = 'https://api.hetzner.cloud/v1/';

    // Server types considered "large" — candidates for downgrade review
    private const LARGE_SERVER_PRICE_THRESHOLD = 30.0;
    private const PRIMARY_IPV4_COST = 0.50;

    // Snapshots older than this are flagged for review
    private const SNAPSHOT_AGE_DAYS_THRESHOLD = 180;

    public function getAllProjectsData(): array
    {
        $projects = config('hetzner.projects', []);

        return collect($projects)
            ->map(fn($token, $slug) => $this->getProjectData($slug, $token))
            ->values()
            ->toArray();
    }

    public function getProjectData(string $slug, string $token): array
    {
        $cacheKey = "hetzner_project_{$slug}";

        return Cache::remember($cacheKey, config('hetzner.cache_ttl'), function () use ($slug, $token) {
            return $this->fetchProjectData($slug, $token);
        });
    }

    public function refreshAll(): array
    {
        $projects = config('hetzner.projects', []);

        foreach (array_keys($projects) as $slug) {
            Cache::forget("hetzner_project_{$slug}");
        }

        return $this->getAllProjectsData();
    }

    private function fetchProjectData(string $slug, string $token): array
    {
        try {
            $client = $this->makeClient($token);

            $servers      = $this->fetchServers($client);
            $floatingIps  = $this->fetchFloatingIps($client);
            $volumes      = $this->fetchVolumes($client);
            $loadBalancers = $this->fetchLoadBalancers($client);
            $snapshots    = $this->fetchSnapshots($client);

            $monthlyCost     = $this->sumMonthlyCosts($servers, $floatingIps, $volumes, $loadBalancers, $snapshots);
            $potentialSavings = $this->calculatePotentialSavings($servers, $floatingIps, $volumes, $loadBalancers);

            return [
                'slug'                  => $slug,
                'status'                => 'ok',
                'error'                 => null,
                'servers'               => $servers,
                'floating_ips'          => $floatingIps,
                'volumes'               => $volumes,
                'load_balancers'        => $loadBalancers,
                'snapshots'             => $snapshots,
                'monthly_cost_estimate' => round($monthlyCost, 2),
                'potential_savings'     => round($potentialSavings, 2),
            ];
        } catch (GuzzleException $e) {
            Log::warning("HetznerApiService: failed to fetch project [{$slug}]", [
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
            ]);

            return [
                'slug'                  => $slug,
                'status'                => 'error',
                'error'                 => $this->humanizeError($e),
                'servers'               => [],
                'floating_ips'          => [],
                'volumes'               => [],
                'load_balancers'        => [],
                'snapshots'             => [],
                'monthly_cost_estimate' => 0.0,
                'potential_savings'     => 0.0,
            ];
        }
    }

    private function makeClient(string $token): Client
    {
        return new Client([
            'base_uri' => self::BASE_URL,
            'headers'  => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 10,
        ]);
    }

    private function fetchServers(Client $client): array
    {
        $response = $client->get('servers');
        $data = json_decode($response->getBody()->getContents(), true);

        return collect($data['servers'] ?? [])
            ->map(function ($s) {
                $basePrice      = $this->extractMonthlyPrice($s['server_type']['prices'] ?? [], $s['datacenter']['location']['name'] ?? null);
                $backupEnabled  = ! empty($s['backup_window']);
                $ipv4Assigned   = ! empty($s['public_net']['ipv4']['ip']);
                $priceWithBackup = $backupEnabled ? round($basePrice * 1.20, 2) : $basePrice;
                $realPrice      = round($priceWithBackup + ($ipv4Assigned ? self::PRIMARY_IPV4_COST : 0), 2);
                $ageDays        = $this->ageDays($s['created'] ?? null);
                $status         = $s['status'];
                $action         = $this->serverAction($status, $realPrice);

                return [
                    'id'                   => $s['id'],
                    'name'                 => $s['name'],
                    'status'               => $status,
                    'type'                 => $s['server_type']['name'] ?? null,
                    'cores'                => $s['server_type']['cores'] ?? null,
                    'memory_gb'            => $s['server_type']['memory'] ?? null,
                    'disk_gb'              => $s['server_type']['disk'] ?? null,
                    'datacenter'           => $s['datacenter']['location']['name'] ?? null,
                    'ipv4'                 => $s['public_net']['ipv4']['ip'] ?? null,
                    'created_at'           => $s['created'] ?? null,
                    'age_days'             => $ageDays,
                    'backup_enabled'       => $backupEnabled,
                    'ipv4_assigned'        => $ipv4Assigned,
                    'monthly_price_base'   => $basePrice,
                    'monthly_price'        => $realPrice,
                    'action'               => $action['label'],
                    'action_priority'      => $action['priority'],
                ];
            })
            ->sortBy(fn($s) => $this->priorityOrder($s['action_priority']))
            ->values()
            ->toArray();
    }

    private function fetchFloatingIps(Client $client): array
    {
        $response = $client->get('floating_ips');
        $data = json_decode($response->getBody()->getContents(), true);

        return collect($data['floating_ips'] ?? [])
            ->map(function ($ip) {
                $price   = $this->extractMonthlyPrice($ip['prices'] ?? []);
                $action  = $ip['server'] === null
                    ? ['label' => 'Elimina: non assegnato', 'priority' => 'high']
                    : ['label' => 'OK', 'priority' => 'ok'];

                return [
                    'id'              => $ip['id'],
                    'ip'              => $ip['ip'],
                    'type'            => $ip['type'],
                    'description'     => $ip['description'] ?? null,
                    'server_id'       => $ip['server'],
                    'monthly_price'   => $price,
                    'action'          => $action['label'],
                    'action_priority' => $action['priority'],
                ];
            })
            ->sortBy(fn($ip) => $this->priorityOrder($ip['action_priority']))
            ->values()
            ->toArray();
    }

    private function fetchVolumes(Client $client): array
    {
        $response = $client->get('volumes');
        $data = json_decode($response->getBody()->getContents(), true);

        return collect($data['volumes'] ?? [])
            ->map(function ($v) {
                $price   = round(($v['size'] ?? 0) * 0.0476, 2);
                $ageDays = $this->ageDays($v['created'] ?? null);
                $action  = $v['server'] === null
                    ? ['label' => 'Elimina: non montato', 'priority' => 'high']
                    : ['label' => 'OK', 'priority' => 'ok'];

                return [
                    'id'              => $v['id'],
                    'name'            => $v['name'],
                    'size_gb'         => $v['size'],
                    'status'          => $v['status'],
                    'server_id'       => $v['server'],
                    'location'        => $v['location']['name'] ?? null,
                    'created_at'      => $v['created'] ?? null,
                    'age_days'        => $ageDays,
                    'monthly_price'   => $price,
                    'action'          => $action['label'],
                    'action_priority' => $action['priority'],
                ];
            })
            ->sortBy(fn($v) => $this->priorityOrder($v['action_priority']))
            ->values()
            ->toArray();
    }

    private function fetchLoadBalancers(Client $client): array
    {
        $response = $client->get('load_balancers');
        $data = json_decode($response->getBody()->getContents(), true);

        return collect($data['load_balancers'] ?? [])
            ->map(function ($lb) {
                $price         = $this->extractMonthlyPrice($lb['load_balancer_type']['prices'] ?? [], $lb['location']['name'] ?? null);
                $targetsCount  = count($lb['targets'] ?? []);
                $action        = $targetsCount === 0
                    ? ['label' => 'Verifica: nessun target', 'priority' => 'high']
                    : ['label' => 'OK', 'priority' => 'ok'];

                return [
                    'id'              => $lb['id'],
                    'name'            => $lb['name'],
                    'type'            => $lb['load_balancer_type']['name'] ?? null,
                    'targets_count'   => $targetsCount,
                    'location'        => $lb['location']['name'] ?? null,
                    'monthly_price'   => $price,
                    'action'          => $action['label'],
                    'action_priority' => $action['priority'],
                ];
            })
            ->sortBy(fn($lb) => $this->priorityOrder($lb['action_priority']))
            ->values()
            ->toArray();
    }

    private function fetchSnapshots(Client $client): array
    {
        $response = $client->get('images?type=snapshot');
        $data = json_decode($response->getBody()->getContents(), true);

        return collect($data['images'] ?? [])
            ->map(function ($img) {
                $price   = round(($img['image_size'] ?? 0) * 0.0119, 4);
                $ageDays = $this->ageDays($img['created'] ?? null);
                $action  = $ageDays > self::SNAPSHOT_AGE_DAYS_THRESHOLD
                    ? ['label' => 'Verifica: snapshot vecchio (>6 mesi)', 'priority' => 'medium']
                    : ['label' => 'OK', 'priority' => 'ok'];

                return [
                    'id'              => $img['id'],
                    'name'            => $img['name'] ?? $img['description'] ?? null,
                    'size_gb'         => round($img['image_size'] ?? 0, 2),
                    'created_at'      => $img['created'] ?? null,
                    'age_days'        => $ageDays,
                    'monthly_price'   => $price,
                    'action'          => $action['label'],
                    'action_priority' => $action['priority'],
                ];
            })
            ->sortBy(fn($s) => $this->priorityOrder($s['action_priority']))
            ->values()
            ->toArray();
    }

    private function serverAction(string $status, float $price): array
    {
        if ($status !== 'running') {
            return ['label' => 'Verifica: spento ma pagato', 'priority' => 'high'];
        }

        if ($price >= self::LARGE_SERVER_PRICE_THRESHOLD) {
            return ['label' => 'Valuta downgrade', 'priority' => 'medium'];
        }

        return ['label' => 'OK', 'priority' => 'ok'];
    }

    private function priorityOrder(string $priority): int
    {
        return match ($priority) {
            'high'   => 0,
            'medium' => 1,
            default  => 2,
        };
    }

    private function ageDays(?string $dateStr): int
    {
        if (! $dateStr) {
            return 0;
        }

        try {
            return (int) now()->diffInDays(new \DateTime($dateStr));
        } catch (\Exception) {
            return 0;
        }
    }

    private function calculatePotentialSavings(array $servers, array $floatingIps, array $volumes, array $loadBalancers): float
    {
        $savings = 0.0;

        foreach (array_merge($servers, $floatingIps, $volumes, $loadBalancers) as $resource) {
            if (($resource['action_priority'] ?? '') === 'high') {
                $savings += (float) ($resource['monthly_price'] ?? 0);
            }
        }

        return $savings;
    }

    private function extractMonthlyPrice(array $prices, ?string $location = null): float
    {
        if (empty($prices)) {
            return 0.0;
        }

        if ($location) {
            foreach ($prices as $price) {
                if (isset($price['location']) && $price['location'] === $location) {
                    return (float) ($price['price_monthly']['gross'] ?? $price['price_monthly']['net'] ?? 0);
                }
            }
        }

        $first = $prices[0];

        return (float) ($first['price_monthly']['gross'] ?? $first['price_monthly']['net'] ?? 0);
    }

    private function sumMonthlyCosts(array ...$resourceGroups): float
    {
        $total = 0.0;

        foreach ($resourceGroups as $resources) {
            foreach ($resources as $resource) {
                $total += (float) ($resource['monthly_price'] ?? 0);
            }
        }

        return $total;
    }

    private function humanizeError(GuzzleException $e): string
    {
        $code = $e->getCode();

        return match (true) {
            $code === 401 => 'Token non valido o scaduto (401)',
            $code === 403 => 'Permessi insufficienti (403)',
            $code === 429 => 'Rate limit superato (429) — riprova tra qualche secondo',
            $code >= 500  => "Errore API Hetzner ({$code})",
            default       => "Errore di connessione ({$code})",
        };
    }
}
