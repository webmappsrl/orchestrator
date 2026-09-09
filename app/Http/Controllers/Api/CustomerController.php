<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Http\Requests\Api\CustomerApiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    /**
     * Create a customer.
     *
     * @response 201 array{id: int, name: string, company_name: string|null, vat: string|null, address: string|null, contact_emails: array<string>, phone: string|null, status: string|null, owner: array{id: int, name: string}|null, notes: string|null, warnings?: array<array{type: string, customers: array<array{id: int, name: string}>}>}
     */
    public function store(CustomerApiRequest $request): JsonResponse
    {
        $this->authorizeRole($request);

        $validated = $request->validated();

        $customer = new Customer();
        $customer->name = $this->resolveName($validated);
        $this->applyWritableFields($customer, $validated);
        $customer->save();

        $data = $this->formatCustomer($customer->fresh('owner'));

        if ($warnings = $this->duplicateVatWarnings($customer)) {
            $data['warnings'] = $warnings;
        }

        return response()->json($data, 201);
    }

    /**
     * Update a customer.
     *
     * @response array{id: int, name: string, company_name: string|null, vat: string|null, address: string|null, contact_emails: array<string>, phone: string|null, status: string|null, owner: array{id: int, name: string}|null, notes: string|null}
     */
    public function update(CustomerApiRequest $request, Customer $customer): JsonResponse
    {
        $this->authorizeRole($request);

        $validated = $request->validated();

        if (!empty($validated['name'])) {
            $customer->name = $validated['name'];
        }
        $this->applyWritableFields($customer, $validated);
        $customer->save();

        return response()->json($this->formatCustomer($customer->fresh('owner')));
    }

    private function authorizeRole(Request $request): void
    {
        // Matches CustomerPolicy::before() (Admin/Manager only) — Nova denies
        // Developer access to Customer data, so the API must not be more
        // permissive than the UI for the same sensitive fields (vat, address,
        // contact_emails, phone).
        $user = $request->user();
        abort_unless(
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager),
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

    private function resolveName(array $validated): string
    {
        if (!empty($validated['name'])) {
            return $validated['name'];
        }

        $base = Str::slug($validated['company_name'] ?? '', '_');

        return $this->uniqueSlug($base !== '' ? $base : 'customer');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 1;

        while (Customer::where('name', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}_{$suffix}";
        }

        return $slug;
    }

    private function applyWritableFields(Customer $customer, array $validated): void
    {
        if (array_key_exists('company_name', $validated)) {
            $customer->full_name = $validated['company_name'];
        }
        if (array_key_exists('vat', $validated)) {
            $customer->vat = $validated['vat'];
        }
        if (array_key_exists('address', $validated)) {
            $customer->address = $validated['address'];
        }
        if (array_key_exists('phone', $validated)) {
            $customer->phone = $validated['phone'];
        }
        if (array_key_exists('status', $validated)) {
            $customer->status = $validated['status'];
        }
        if (array_key_exists('notes', $validated)) {
            $customer->notes = $validated['notes'];
        }
        $this->applyContactEmails($customer, $validated);
    }

    /**
     * `contact_emails` (replace-all) e `contact_emails_add` (append) sono
     * mutuamente esclusivi — già garantito da CustomerApiRequest::withValidator().
     * La colonna `email` resta testo libero comma-separated, coerente col
     * formato già in produzione (Customer::getContactEmailsAttribute()).
     */
    private function applyContactEmails(Customer $customer, array $validated): void
    {
        if (array_key_exists('contact_emails', $validated)) {
            $customer->email = implode(',', $validated['contact_emails'] ?? []);
            return;
        }

        if (array_key_exists('contact_emails_add', $validated) && $validated['contact_emails_add'] !== null) {
            $toAdd = is_array($validated['contact_emails_add'])
                ? $validated['contact_emails_add']
                : [$validated['contact_emails_add']];

            $merged = array_values(array_unique(array_merge($customer->contact_emails, $toAdd)));
            $customer->email = implode(',', $merged);
        }
    }

    private function duplicateVatWarnings(Customer $customer): array
    {
        if (blank($customer->vat)) {
            return [];
        }

        $duplicates = Customer::where('vat', $customer->vat)
            ->where('id', '!=', $customer->id)
            ->get(['id', 'name']);

        if ($duplicates->isEmpty()) {
            return [];
        }

        return [[
            'type'      => 'duplicate_vat',
            'customers' => $duplicates->map(fn(Customer $c) => ['id' => $c->id, 'name' => $c->name])->all(),
        ]];
    }
}
