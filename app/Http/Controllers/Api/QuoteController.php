<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuoteApiRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuoteController extends Controller
{
    private const TRANSLATABLE_FIELDS = ['additional_services', 'notes'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        $query = Quote::query()->with(['products', 'recurringProducts']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $quotes = $query->get();

        return response()->json($quotes->map(fn(Quote $q) => $this->formatQuote($q)));
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $quote->load(['products', 'recurringProducts']);

        return response()->json($this->formatQuote($quote));
    }

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

    public function attachProduct(Request $request, Quote $quote, Product $product): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $quote->products()->syncWithoutDetaching([$product->id => ['quantity' => $validated['quantity']]]);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    public function detachProduct(Request $request, Quote $quote, Product $product): JsonResponse
    {
        $this->authorize('update', $quote);

        abort_unless($quote->products()->where('product_id', $product->id)->exists(), 404);

        $quote->products()->detach($product->id);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    public function attachRecurringProduct(Request $request, Quote $quote, RecurringProduct $recurringProduct): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $quote->recurringProducts()->syncWithoutDetaching([$recurringProduct->id => ['quantity' => $validated['quantity']]]);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

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

    private function formatQuote(Quote $quote): array
    {
        return [
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
            'net_total'            => $quote->getQuoteNetPrice(),
        ];
    }
}
