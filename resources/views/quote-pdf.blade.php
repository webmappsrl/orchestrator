@php
$customerName = $quote->customer->full_name ?? $quote->customer->name;
$pdfName = __('Preventivo_WEBMAPP_' . $customerName);
@endphp

<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $pdfName }}</title>
    <style>
        {!! file_get_contents(public_path('app.css')) !!}
    </style>
</head>
<body>
    <header class="webmapp-header">
        @php
        $logoPath = public_path('images/logo.svg');
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/svg+xml;base64,' . base64_encode($logoData);
        } else {
            $logoBase64 = '';
        }
        // Generate the complete quote URL
        $quoteUrl = route('quote', ['id' => $quote->id]);
        if (App::getLocale() !== 'it') {
            $quoteUrl = route('quote', ['id' => $quote->id, 'lang' => App::getLocale()]);
        }
        @endphp
        <div class="quote-url">
            {{ $quoteUrl }}
        </div>
        @if($logoBase64)
        <div class="logo">
            <img src="{{ $logoBase64 }}" alt="webmapp logo">
        </div>
        @endif
        <div class="webmapp-details">
            <p>{{ $config['company']['name'] }} - {{ $config['company']['address'] }} <br>
                {{ $config['company']['vat'] }} - {{ $config['company']['phone'] }} <br>
                {{ $config['company']['website'] }} | {{ $config['company']['email'] }}</p>
        </div>
    </header>

    <footer class="pdf-footer">
        @php
        $pdfCreationDate = now()->format('d/m/Y H:i:s');
        @endphp
        <div>{{ $pdfCreationDate }}</div>
    </footer>

    {{-- MAIN CONTENT - without table wrapper to allow DomPDF pagination --}}
    <div class="customer-details-page">
        <div class="customer-details">
            <h3>{{ __('Dear') }}</h3>
            <p><strong>{{ $quote->customer->full_name ?? $quote->customer->name }}</strong></p>
            <p class="indent-heading-customer">{!! nl2br($quote->customer->heading) !!}</p>
        </div>
        <div class="subject-container">
            <p>{{ __('Subject') }}: <br><br>{{ $quote->getTranslation('title', App::getLocale()) }}</p>
        </div>
    </div>

    <h4>{{ __('The service includes') }}:</h4>

    <div class="service-description">
        @php
        $additionalServicesForCount = $quote->additional_services;
        if (is_string($additionalServicesForCount)) {
            $additionalServicesForCount = json_decode($additionalServicesForCount, true) ?? [];
        }
        if (!is_array($additionalServicesForCount)) {
            $additionalServicesForCount = [];
        }
        @endphp
        @if (count($quote->products) < 1 && count($quote->recurringProducts) < 1 && count($additionalServicesForCount) < 1)
        <h2 style="color:red;">{{ __('No items available') }}</h2>
        @else
        <h2 class="description">{{ __('Service features') }}</h2>
        <p>{{ __('The service includes') }}:</p>
        <div class="service-details">
            @if (count($quote->products) > 0)
            <h3 class="description">{{ __('Activation services') }}:</h3>
            <ul>
                @foreach ($quote->products->sortByDesc('id') as $product)
                <li>
                    <span class="product-title">{{ $product->getTranslation('name', App::getLocale()) }}</span>
                    - {{ $product->getTranslation('description', App::getLocale()) }}
                </li>
                @endforeach
            </ul>
            @endif

            @if (count($quote->recurringProducts) > 0)
            <h3 class="description">{{ __('Maintenance services') }}:</h3>
            <ul>
                @foreach ($quote->recurringProducts->sortByDesc('id') as $recurringProduct)
                <li>
                    <span class="product-title">{{ $recurringProduct->getTranslation('name', App::getLocale()) }}</span>
                    - {{ $recurringProduct->getTranslation('description', App::getLocale()) }}
                </li>
                @endforeach
            </ul>
            @endif

            @php
            $additionalServices = $quote->additional_services;
            @endphp
            @if (!is_string($additionalServices) && count($additionalServices) > 0)
            <h3 class="description">{{ __('Additional services') }}:</h3>
            <ul>
                @foreach ($additionalServices as $description => $price)
                <li>{{ $description }}</li>
                @endforeach
            </ul>
            @endif
        </div>
        @endif

        @if ($quote->additional_info)
        <h2 class="description">{{ __('Additional information') }}</h2>
        <p class="additional-info indent-paragraph">{!! $quote->getTranslation('additional_info', App::getLocale()) !!}</p>
        @endif

        @if ($quote->delivery_time)
        <div class="delivery-time">
            <h2 class="description">{{ __('Delivery time') }}</h2>
            <p>{!! $quote->getTranslation('delivery_time', App::getLocale()) !!}</p>
        </div>
        @endif
    </div>

    <h2 style="color: #005485; margin-top: 30px;">{{ __('Costs') }}</h2>
    <p>{{ __('Below we indicate the costs of the service divided into activation costs and annual maintenance costs') }}</p>

    @if ($quote->products->count() > 0)
    {{-- Start Products and services table --}}
    <div class="products-and-services-page">
        <table>
            <thead>
                <tr class="table-header-style">
                    <td class="td" colspan="5">{{ __('Products and services (activation costs)') }}</td>
                </tr>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('SKU') }}</th>
                    <th>{{ __('Unit price') }}</th>
                    <th>{{ __('Quantity') }}</th>
                    <th class="aligned-right">{{ __('Total price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quote->products->sortByDesc('id') as $product)
                <tr>
                    <td class="td">{{ __($product->name) }}</td>
                    <td class="td">{{ __($product->sku) }}</td>
                    <td class="td">{{ number_format($product->price, 2, ',', '.') }} €</td>
                    <td class="td">{{ $product->pivot->quantity }}</td>
                    <td class="aligned-right td">
                        {{ number_format($product->price * $product->pivot->quantity, 2, ',', '.') }} €
                    </td>
                </tr>
                @endforeach
                <tr style="color: #005485;">
                    <td class="td">{{ __('Subtotal') }}:</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">{{ number_format($quote->getTotalPrice(), 2, ',', '.') }} €</td>
                </tr>
                <tr style="color: #005485;">
                    <td class="td">{{ __('VAT') }}:</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">{{ number_format($quote->getTotalPrice() * 0.22, 2, ',', '.') }} €</td>
                </tr>
                <tr class="table-header-style">
                    <td class="td">{{ __('Total activation costs') }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">{{ number_format($quote->getTotalPrice() * 1.22, 2, ',', '.') }} €</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    @if ($quote->recurringProducts->count() > 0)
    {{-- Start maintenance services table --}}
    <div class="recurring-products-page">
        <table>
            <thead>
                <tr class="table-header-style">
                    <td class="td" colspan="5">{{ __('Maintenance services') }} <br>({{ __('Annual maintenance costs') }})</td>
                </tr>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th>{{ __('SKU') }}</th>
                    <th>{{ __('Unit price') }}</th>
                    <th>{{ __('Quantity') }}</th>
                    <th class="aligned-right">{{ __('Total price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quote->recurringProducts->sortByDesc('id') as $recurringProduct)
                <tr>
                    <td class="td">{{ __($recurringProduct->name) }}</td>
                    <td class="td">{{ __($recurringProduct->sku) }}</td>
                    <td class="td">{{ number_format($recurringProduct->price, 2, ',', '.') }} €</td>
                    <td class="td">{{ $recurringProduct->pivot->quantity }}</td>
                    <td class="aligned-right td">
                        {{ number_format($recurringProduct->price * $recurringProduct->pivot->quantity, 2, ',', '.') }} €
                    </td>
                </tr>
                @endforeach
                <tr style="color: #005485;">
                    <td class="td">{{ __('Annual total') }}:</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">{{ number_format($quote->getTotalRecurringPrice(), 2, ',', '.') }} €</td>
                </tr>
                <tr style="color: #005485;">
                    <td class="td">{{ __('Annual VAT') }}:</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">{{ number_format($quote->getTotalRecurringPrice() * 0.22, 2, ',', '.') }} €</td>
                </tr>
                <tr class="table-header-style">
                    <td class="td">{{ __('Annual total') }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">{{ number_format($quote->getTotalRecurringPrice() * 1.22, 2, ',', '.') }} €</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    {{-- Start additional services table --}}
    @php
    $additionalServices = $quote->getTranslation('additional_services', App::getLocale());
    @endphp
    @if (!is_string($additionalServices) && count($additionalServices) > 0)
    <div class="additional-services">
        <table>
            <thead>
                <tr class="table-header-style">
                    <td class="td" colspan="5">{{ __('Additional services') }}</td>
                </tr>
                <tr>
                    <th>{{ __('Element') }}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th class="aligned-right">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($additionalServices as $description => $price)
                <tr>
                    <td class="td">{{ $description }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">{{ number_format(str_replace(',', '.', $price), 2, ',', '.') }} €</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Start summary table --}}
    <table>
        <thead>
            <tr class="table-header-style">
                <td colspan="5">{{ __('Summary') }}</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="td">{{ __('Activation costs') }}</td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="aligned-right td">{{ number_format($quote->getTotalPrice(), 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td class="td">{{ __('Annual maintenance costs') }}</td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="aligned-right td">{{ number_format($quote->getTotalRecurringPrice(), 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td class="td">{{ __('Additional services costs') }}</td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="aligned-right td">{{ number_format($quote->getTotalAdditionalServicesPrice(), 2, ',', '.') }} €</td>
            </tr>
            @if ($quote->discount > 0)
            <tr>
                <td class="td">{{ __('Discount') }}</td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="aligned-right td">{{ number_format($quote->discount, 2, ',', '.') }} €</td>
            </tr>
            @endif
            <tr>
                <td class="td" style="color: #005485;">{{ __('Final price (without VAT)') }}</td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="td"></td>
                <td style="color: #005485;" class="aligned-right td">{{ number_format($quote->getQuoteNetPrice(), 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td class="td">{{ __('VAT (22%)') }}</td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="aligned-right td">{{ number_format($quote->getQuoteNetPrice() * 0.22, 2, ',', '.') }} €</td>
            </tr>
            <tr class="table-header-style">
                <td class="td">{{ __('Final price (with VAT)') }}</td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="td"></td>
                <td class="aligned-right td">{{ number_format($quote->getQuoteNetPrice() + $quote->getQuoteNetPrice() * 0.22, 2, ',', '.') }} €</td>
            </tr>
        </tbody>
    </table>

    @if ($quote->payment_plan)
    <div class="payment-plan">
        <h2 class="description">{{ __('Payment plan') }}</h2>
        <p>{!! $quote->getTranslation('payment_plan', App::getLocale()) !!}</p>
    </div>
    @endif

    <div class="message">
        <p>{{ __('At your disposal for any clarification, we send you cordial greetings.') }}</p>
        <br>
        <p>{{ __('Pisa,') }} {{ date('d-m-Y') }}</p>
        <p>{{ __('Alessio Piccioli,') }}</p>
        <p>{{ __('Administrator of Webmapp.') }}</p>
    </div>


</body>
</html>
