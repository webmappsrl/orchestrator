<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * List customers, optionally filtered by status or name search.
     *
     * @response array<array{id: int, name: string, company_name: string|null, vat: string|null, address: string|null, contact_emails: array<string>, phone: string|null, status: string|null, owner: array{id: int, name: string}|null, notes: string|null}>
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $query = Customer::query()->with('owner');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        $customers = $query->get();

        return response()->json($customers->map(fn(Customer $c) => $this->formatCustomer($c)));
    }

    /**
     * Retrieve a customer.
     *
     * @response array{id: int, name: string, company_name: string|null, vat: string|null, address: string|null, contact_emails: array<string>, phone: string|null, status: string|null, owner: array{id: int, name: string}|null, notes: string|null}
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeRole($request);

        $customer->load('owner');

        return response()->json($this->formatCustomer($customer));
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer),
            403
        );
    }

    private function formatCustomer(Customer $customer): array
    {
        return [
            'id'             => $customer->id,
            'name'           => $customer->name,
            'company_name'   => $customer->full_name,
            'vat'            => $customer->vat,
            'address'        => $customer->address,
            'contact_emails' => $customer->contact_emails,
            'phone'          => $customer->phone,
            'status'         => $customer->status,
            'owner'          => $customer->owner ? [
                'id'   => $customer->owner->id,
                'name' => $customer->owner->name,
            ] : null,
            'notes'          => $customer->notes,
        ];
    }
}
