<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exports\HetznerExport;
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

        return response()->json($this->service->getAllProjectsData());
    }

    public function refresh(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        return response()->json($this->service->refreshAll());
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorizeAccess($request);

        $projects = $this->service->getAllProjectsData();

        return Excel::download(new HetznerExport($projects), 'hetzner-monitoring.csv', \Maatwebsite\Excel\Excel::CSV);
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
