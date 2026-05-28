<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exports\HetznerExport;
use App\Models\HetznerMonitoring;
use App\Services\HetznerApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HetznerMonitoringController extends Controller
{
    public function __construct(private readonly HetznerApiService $service) {}

    public function data(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $projects = $this->service->getAllProjectsData();

        return response()->json($this->mergeNotes($projects));
    }

    public function refresh(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $projects = $this->service->refreshAll();

        return response()->json($this->mergeNotes($projects));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorizeAccess($request);

        $projects = $this->mergeNotes($this->service->getAllProjectsData());

        return Excel::download(new HetznerExport($projects), 'hetzner-monitoring.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function saveNote(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'project_slug'  => 'required|string',
            'resource_type' => 'required|string|in:server,floating_ip,volume,load_balancer,snapshot',
            'resource_id'   => 'required|integer',
            'text'          => 'required|string|max:500',
        ]);

        $user = $request->user();
        $record = HetznerMonitoring::findOrCreateResource(
            $validated['project_slug'],
            $validated['resource_type'],
            $validated['resource_id']
        );

        $record->setNote($validated['text'], $user->id, $user->name);
        $record->refresh();

        return response()->json(['ok' => true, 'note' => $record->getNote()]);
    }

    public function deleteNote(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'project_slug'  => 'required|string',
            'resource_type' => 'required|string',
            'resource_id'   => 'required|integer',
        ]);

        $record = HetznerMonitoring::findResource(
            $validated['project_slug'],
            $validated['resource_type'],
            $validated['resource_id']
        );

        if ($record) {
            $record->deleteNote();
        }

        return response()->json(['ok' => true]);
    }

    private function mergeNotes(array $projects): array
    {
        $projectSlugs = array_column($projects, 'slug');

        // Bulk load all notes for all projects in one query
        $records = HetznerMonitoring::whereIn('properties->project_slug', $projectSlugs)
            ->get()
            ->keyBy(function ($r) {
                $p = $r->properties ?? [];

                return "{$p['project_slug']}::{$p['resource_type']}::{$p['resource_id']}";
            });

        foreach ($projects as $pIndex => $project) {
            $slug = $project['slug'];

            foreach (['servers', 'floating_ips', 'volumes', 'load_balancers', 'snapshots'] as $resourceType) {
                $type = match ($resourceType) {
                    'floating_ips'   => 'floating_ip',
                    'load_balancers' => 'load_balancer',
                    default          => rtrim($resourceType, 's'),
                };

                foreach ($projects[$pIndex][$resourceType] ?? [] as $rIndex => $resource) {
                    $key = "{$slug}::{$type}::{$resource['id']}";
                    $projects[$pIndex][$resourceType][$rIndex]['note']
                        = $records->has($key) ? $records->get($key)->getNote() : null;
                }
            }
        }

        return $projects;
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();

        $allowed = $user && (
            $user->hasRole(UserRole::Admin) ||
            $user->hasRole(UserRole::Manager) ||
            $user->hasRole(UserRole::Developer)
        );

        abort_if(! $allowed, 403);
    }
}
