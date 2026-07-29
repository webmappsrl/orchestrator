<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuoteApiRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringProduct;
use App\Services\QuotePdfService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class QuoteController extends Controller
{
    private const TRANSLATABLE_FIELDS = ['additional_services', 'notes'];
    private const ALLOWED_INCLUDES = ['customer', 'products', 'recurringProducts'];
    private const DEFAULT_PER_PAGE = 20;
    private const DEFAULT_LANG = 'it';

    /**
     * List quotes, optionally filtered by customer or status (single or
     * multiple), sorted by `created_at` via `?sort=created_at`/`-created_at`,
     * and optionally paginated. Without an explicit `sort`, results default
     * to descending `id` order so paginated requests stay deterministic.
     *
     * Pagination is opt-in: without `per_page`/`page` the response stays a
     * plain array (unchanged from before this feature) to avoid breaking
     * existing consumers.
     *
     * @response array<array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}>|array{data: array<array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    #[QueryParameter('customer_id', description: 'Filter quotes belonging to a specific customer.', type: 'int')]
    #[QueryParameter('status', description: 'Filter by status. Accepts a single value (?status=new) or multiple via array syntax (?status[]=new&status[]=presented).', type: 'string|array<string>')]
    #[QueryParameter('sort', description: 'Sort by created_at: "created_at" for ascending, "-created_at" for descending. Any other value (including omitting this parameter) silently falls back to descending id order (needed for deterministic pagination).', type: 'string')]
    #[QueryParameter('per_page', description: 'Enables opt-in pagination together with page. Without per_page/page the response is a plain array; with either, it becomes {data, meta}.', type: 'int', default: self::DEFAULT_PER_PAGE)]
    #[QueryParameter('page', description: 'Page number for opt-in pagination, used together with per_page.', type: 'int')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        $query = Quote::query()->with(['products', 'recurringProducts']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->get('sort') === '-created_at') {
            $query->orderByDesc('created_at');
        } elseif ($request->get('sort') === 'created_at') {
            $query->orderBy('created_at');
        } else {
            // No explicit sort requested: without a deterministic ORDER BY,
            // PostgreSQL row order is not stable across paginated requests
            // (page=1 / page=2 can overlap or skip rows). `id` is the
            // primary key, always indexed and monotonic.
            $query->orderByDesc('id');
        }

        if ($request->filled('per_page') || $request->filled('page')) {
            $paginated = $query->paginate($request->integer('per_page', self::DEFAULT_PER_PAGE));

            return response()->json([
                'data' => collect($paginated->items())->map(fn(Quote $q) => $this->formatQuote($q)),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ]);
        }

        $quotes = $query->get();

        return response()->json($quotes->map(fn(Quote $q) => $this->formatQuote($q)));
    }

    /**
     * Retrieve a quote, optionally expanding relations via `?include=`.
     *
     * `?include=` accepts a comma-separated list among `customer`, `products`,
     * `recurringProducts`; each name adds the corresponding key below to the
     * response. Omitted names are simply absent from the response (not null).
     *
     * @response array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null, customer?: array{id: int, name: string, company_name: string|null}, products?: array<array{id: int, name: string, price: float, quantity: int}>, recurringProducts?: array<array{id: int, name: string, price: float, quantity: int}>}
     */
    #[QueryParameter('include', description: 'Comma-separated list of relations to expand: customer, products, recurringProducts (e.g. ?include=customer,products). Unlisted names are silently ignored; omitted relations are simply absent from the response.', type: 'string')]
    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $include = array_values(array_intersect(
            array_filter(explode(',', (string) $request->get('include', ''))),
            self::ALLOWED_INCLUDES
        ));

        $relationsToLoad = array_unique(array_merge(['products', 'recurringProducts'], $include));
        $quote->load($relationsToLoad);

        return response()->json($this->formatQuote($quote, $include));
    }

    /**
     * Stream the quote PDF (bearer auth).
     *
     * Returns the rendered PDF as a binary `application/pdf` stream, not
     * JSON. Accepts an optional `lang` query param (defaults to `it`) to
     * select the PDF locale.
     *
     * `persist: false` — this endpoint is built for repeated/automated
     * calls, unlike the legacy web route. Persisting here would call
     * `Quote::save()`, and since a `template=true` quote's `saving` hook
     * mass-demotes every other template quote for the same customer, a
     * routine PDF download would silently cascade `template=false` onto
     * sibling quotes.
     */
    public function pdf(Request $request, Quote $quote, QuotePdfService $pdfService): Response
    {
        $this->authorize('view', $quote);

        $lang = $request->get('lang', self::DEFAULT_LANG);

        return $pdfService->stream($quote, $lang, persist: false);
    }

    /**
     * Generate a temporary signed public URL for the quote PDF (no auth
     * required to open it — meant to be embedded in an email to the
     * customer). expires_in_days is capped at 90 to avoid a de-facto
     * permanent link; the signature cannot be revoked before expiry short
     * of rotating APP_KEY (which invalidates every signed link project-wide).
     *
     * @response 201 array{url: string, expires_at: string}
     */
    public function pdfLink(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        // Unlike a bearer-authenticated PDF download, this issues an
        // unauthenticated, externally-shareable link valid up to 90 days —
        // QuotePolicy::view() has no per-quote ownership check, so it alone
        // isn't a tight enough gate for an action with this blast radius.
        // Restrict it to Admin/Manager, same set as CustomerPolicy.
        abort_unless(
            $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager),
            403
        );

        $validated = $request->validate([
            'lang'             => ['sometimes', 'string', 'max:5'],
            'expires_in_days'  => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        $lang = $validated['lang'] ?? self::DEFAULT_LANG;
        $expiresAt = now()->addDays($validated['expires_in_days'] ?? 30);

        $url = URL::temporarySignedRoute('quotes.pdf.public', $expiresAt, [
            'quote' => $quote->id,
            'lang'  => $lang,
        ]);

        return response()->json([
            'url'        => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ], 201);
    }

    /**
     * Create a new quote.
     *
     * @response 201 array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function store(QuoteApiRequest $request): JsonResponse
    {
        $this->authorize('create', Quote::class);

        $validated = $request->validated();
        $translatable = $this->extractTranslatable($validated);

        $quote = new Quote();
        $quote->fill($validated);
        $this->applyTranslatable($quote, $translatable);
        $quote->save();

        return response()->json($this->formatQuote($quote), 201);
    }

    /**
     * Update an existing quote.
     *
     * @response array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function update(QuoteApiRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validated();
        $translatable = $this->extractTranslatable($validated);

        $quote->fill($validated);
        $this->applyTranslatable($quote, $translatable);
        $quote->save();

        return response()->json($this->formatQuote($quote));
    }

    /**
     * Delete a quote.
     *
     * @response array{message: string}
     */
    public function destroy(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);

        Log::info('Quote deleted via API', [
            'user_id'  => $request->user()->id,
            'quote_id' => $quote->id,
        ]);

        $quote->delete();

        return response()->json(['message' => 'Quote deleted.']);
    }

    /**
     * Attach a product to a quote with a quantity.
     *
     * @response array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function attachProduct(Request $request, Quote $quote, Product $product): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $quote->products()->syncWithoutDetaching([$product->id => ['quantity' => $validated['quantity']]]);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    /**
     * Detach a product from a quote.
     *
     * @response array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function detachProduct(Request $request, Quote $quote, Product $product): JsonResponse
    {
        $this->authorize('update', $quote);

        abort_unless($quote->products()->where('product_id', $product->id)->exists(), 404);

        $quote->products()->detach($product->id);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    /**
     * Attach a recurring product to a quote with a quantity.
     *
     * @response array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function attachRecurringProduct(Request $request, Quote $quote, RecurringProduct $recurringProduct): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $quote->recurringProducts()->syncWithoutDetaching([$recurringProduct->id => ['quantity' => $validated['quantity']]]);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    /**
     * Detach a recurring product from a quote.
     *
     * @response array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function detachRecurringProduct(Request $request, Quote $quote, RecurringProduct $recurringProduct): JsonResponse
    {
        $this->authorize('update', $quote);

        abort_unless($quote->recurringProducts()->where('recurring_product_id', $recurringProduct->id)->exists(), 404);

        $quote->recurringProducts()->detach($recurringProduct->id);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    private function extractTranslatable(array &$validated): array
    {
        $translatable = [];
        foreach (self::TRANSLATABLE_FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $translatable[$field] = $validated[$field];
                unset($validated[$field]);
            }
        }
        return $translatable;
    }

    private function applyTranslatable(Quote $quote, array $translatable): void
    {
        foreach ($translatable as $field => $value) {
            $quote->setTranslation($field, config('app.locale'), $value);
        }
    }

    private function formatQuote(Quote $quote, array $include = []): array
    {
        $netTotal = $quote->getQuoteNetPrice();
        $iva = $netTotal * Quote::VAT_RATE;

        $data = [
            'id'                   => $quote->id,
            'title'                => $quote->title,
            'status'               => $quote->status,
            'priority'             => $quote->priority,
            'customer_id'          => $quote->customer_id,
            'google_drive_url'     => $quote->google_drive_url,
            'discount'             => $quote->discount,
            'notes'                => $quote->notes,
            'additional_services'  => $quote->additional_services,
            'template'             => $quote->template,
            'total'                => $quote->getTotalPrice() + $quote->getTotalRecurringPrice() + $quote->getTotalAdditionalServicesPrice(),
            'net_total'            => $netTotal,
            'iva'                  => $iva,
            'final_price'          => $netTotal + $iva,
            'created_at'           => optional($quote->created_at)->toIso8601String(),
            'updated_at'           => optional($quote->updated_at)->toIso8601String(),
        ];

        if (in_array('customer', $include, true) && $quote->relationLoaded('customer') && $quote->customer) {
            $data['customer'] = [
                'id'           => $quote->customer->id,
                'name'         => $quote->customer->name,
                'company_name' => $quote->customer->full_name,
            ];
        }

        if (in_array('products', $include, true)) {
            $data['products'] = $quote->products->map(fn($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'price'    => $p->price,
                'quantity' => $p->pivot->quantity,
            ])->all();
        }

        if (in_array('recurringProducts', $include, true)) {
            $data['recurringProducts'] = $quote->recurringProducts->map(fn($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'price'    => $p->price,
                'quantity' => $p->pivot->quantity,
            ])->all();
        }

        return $data;
    }
}
