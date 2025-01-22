@php
    $customerName = $quote->customer->full_name ?? $quote->customer->name;
    $pdfName = __('Preventivo_WEBMAPP_' . $customerName);
@endphp

<!DOCTYPE html>
<html lang="{{ App::getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="/app.css">
    <title>{{ $pdfName }}</title>
</head>

<body>
    <header class="webmapp-header">
        <div class="logo">
            <img src="/images/logo.svg" alt="webmapp logo">
        </div>
        <div class="webmapp-details">
            <p>Webmapp S.r.l. - Via Antonio Cei - 56123 Pisa <br>
                CF/P.iva 02266770508 - Tel +39 3285360803 <br>
                ww.webmapp.it | info@webmapp.it</p>
        </div>

    </header>
    <table>
        <thead>
            <tr>
                <td class="td-placeholder">
                    <div class="header-space"></div>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5">
                    <div class="customer-details-page">
                        <div class="customer-details">
                            <h3>{{ __('Dear') }}</h3>
                            <p><strong>{{ $quote->customer->full_name ?? $quote->customer->name }}</strong></p>
                            {{-- additional customers info --}}
                            <p class="indent-heading-customer">{!! nl2br($quote->customer->heading) !!}</p>
                        </div>
                        <div class="subject-container">
                            <p>{{ __('Subject') }}: <br><br>{{ __($quote->title) }}</p>
                        </div>
                    </div>

                    <h4>{{ __('The service includes') }}:</h4>

                    <div class="service-description">
                        @if (count($quote->products) < 1 && count($quote->recurringProducts) < 1 && count($quote->additional_services) < 1)
                            <h2 style="color:red;">{{ __('No elements available') }}</h2>
                        @else
                            <h2 class="description">{{ __('Service Features') }}</h2>
                            <p>{{ __('The service includes') }}:</p>
                            <div class="service-details">
                                <ul>
                                    @if (count($quote->products) > 0)
                                        <h3 class="description">{{ __('Activation Services') }}:</h3>
                                        @foreach ($quote->products as $product)
                                            <li> <span class="product-title">{{ __($product->name) }}</span> -
                                                {{ __($product->description) }} </li>
                                        @endforeach
                                    @endif
                                    @if (count($quote->recurringProducts) > 0)
                                        <h3 class="description">{{ __('Maintenance Services') }}:</h3>
                                        @foreach ($quote->recurringProducts as $recurringProduct)
                                            <li><span class="product-title">{{ __($recurringProduct->name) }}</span> -
                                                {{ __('portapporta_maintenance_description') }}
                                            </li>
                                        @endforeach
                                    @endif
                                    @if (count($quote->additional_services) > 0)
                                        <h3 class="description">{{ __('Additional Services') }}:</h3>
                                        @foreach ($quote->additional_services as $description => $price)
                                            <li>{{ __($description) }}</li>
                                        @endforeach
                                    @endif
                                </ul>
                            </div>
                        @endif

                        @if ($quote->additional_info)
                            <h2 class="description">{{ __('Additional Information') }}</h2>
                            <p class="additional-info indent-paragraph">{!! __($quote->additional_info) !!}</p>
                        @endif
                        @if ($quote->payment_plan)
                            <div class="payment-plan">
                                <h2 class="description">{{ __('Payment Plan') }}</h2>
                                <p> {!! __($quote->payment_plan) !!}</p>
                            </div>
                        @endif
                        @if ($quote->delivery_time)
                            <div class="delivery-time">
                                <h2 class="description">{{ __('Delivery Time') }}</h2>
                                <p> {!! __($quote->delivery_time) !!}</p>
                            </div>
                        @endif
                    </div>
                    <h2 style="color: #005485; page-break-before:always;">{{ __('Costs') }}</h2>
                    <p>{{ __('Below we indicate the costs of the service divided into activation costs and annual subscription costs') }}
                    </p>
                    </p>
                    @if ($quote->products->count() > 0)
                        {{-- Start Prodotti e servizi table --}}
                        <div class="products-and-services-page">

                            <thead>
                                <tr class="table-header-style">
                                    <td class="td">
                                        {{ __('Products and services (activation costs)') }}
                                    </td>
                                    <td class="td" style="display: none"></td>

                                </tr>
                            </thead>

                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('SKU') }}</th>
                                    <th>{{ __('Unit price') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th class="aligned-right">{{ __('Total price') }}</th>
                                </tr>
                            </thead>
                            @foreach ($quote->products as $product)
                                <thead>
                                    <tr>
                                        <td class="td">{{ __($product->name) }}</td>
                                        <td class="td">{{ __($product->sku) }}</td>
                                        <td class="td">{{ number_format($product->price, 2, ',', '.') }} €</td>
                                        <td class="td">{{ $product->pivot->quantity }}</td>
                                        <td class="aligned-right td">
                                            {{ number_format($product->price * $product->pivot->quantity, 2, ',', '.') }}
                                            €
                                        </td>
                                    </tr>
                                </thead>
                            @endforeach
                            <thead>
                                <tr style="color: #005485;">
                                    <td class="td">{{ __('Subtotal') }}:</td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="aligned-right td">
                                        {{ number_format($quote->getTotalPrice(), 2, ',', '.') }} €</td>
                                </tr>
                            </thead>
                            <thead>
                                <tr style="color: #005485;">
                                    <td class="td">{{ __('VAT') }}:</td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="aligned-right td">
                                        {{ number_format($quote->getTotalPrice() * 0.22, 2, ',', '.') }}
                                        €</td>
                                </tr>
                            </thead>
                            <thead style="page-break-after:always;">
                                <tr class="table-header-style">
                                    <td class="td">{{ __('Total activation costs') }}</td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="aligned-right td">
                                        {{ number_format($quote->getTotalPrice() * 1.22, 2, ',', '.') }}
                                        €</td>
                                </tr>
                            </thead>
                        </div>
                        {{-- end prodotti e servizi table --}}
                    @endif
                    @if ($quote->recurringProducts->count() > 0)
                        {{-- start servizi di manutenzione table --}}
                        <div class="recurring-products-page">
                            <thead>
                                <tr class="table-header-style">
                                    <td class="td">{{ __('Maintenance Services') }} <br>
                                        ({{ __('Annual subscription costs') }})
                                    </td>
                                </tr>
                            </thead>

                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th>{{ __('SKU') }}</th>
                                    <th>{{ __('Unit price') }}</th>
                                    <th>{{ __('Quantity') }}</th>
                                    <th class="aligned-right">{{ __('Total price') }}</th>
                                </tr>
                            </thead>
                            @foreach ($quote->recurringProducts as $recurringProduct)
                                <thead>
                                    <tr>
                                        <td class="td">{{ __($recurringProduct->name) }}</td>
                                        <td class="td">{{ __($recurringProduct->sku) }}</td>
                                        <td class="td">
                                            {{ number_format($recurringProduct->price, 2, ',', '.') }} €</td>
                                        <td class="td" class="td">{{ $recurringProduct->pivot->quantity }}
                                        </td>
                                        <td class="aligned-right td">
                                            {{ number_format($recurringProduct->price * $recurringProduct->pivot->quantity, 2, ',', '.') }}
                                            €
                                        </td>
                                    </tr>
                                </thead>
                            @endforeach
                            <thead>
                                <tr style="color: #005485;">
                                    <td class="td">{{ __('Subtotal annual') }}:</td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="aligned-right td">
                                        {{ number_format($quote->getTotalRecurringPrice() / count($quote->recurringProducts), 2, ',', '.') }}
                                        €
                                    </td>

                                </tr>
                            </thead>
                            <thead>
                                <tr style="color: #005485;">
                                    <td class="td">{{ __('Total annual subtotal') }}:</td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="aligned-right td">
                                        {{ number_format($quote->getTotalRecurringPrice(), 2, ',', '.') }} €
                                    </td>

                                </tr>
                            </thead>
                            <thead>
                                <tr style="color: #005485;">
                                    <td class="td">{{ __('Total annual VAT') }}:</td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="aligned-right td">
                                        {{ number_format($quote->getTotalRecurringPrice() * 0.22, 2, ',', '.') }}
                                        €
                                    </td>
                                </tr>
                            </thead>
                            <thead>
                                <tr style="page-break-after:always;" class="table-header-style">
                                    <td class="td">{{ __('Total annual subscription costs') }}</td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="td"></td>
                                    <td class="aligned-right td">
                                        {{ number_format($quote->getTotalRecurringPrice() * 1.22, 2, ',', '.') }} €
                                    </td>
                                </tr>
                            </thead>
                        </div>
                        {{-- end servizi di manutenzione table --}}
                    @endif
                    {{-- start servizi aggiuntivi table --}}
                    @if (count($quote->additional_services) > 0)
                        <div class="additional-services page">
                            <thead>
                                <tr class="table-header-style">
                                    <td class="td">{{ __('Additional Services') }}</td>
                                </tr>
                            </thead>

                            <thead>
                                <tr>
                                    <th>{{ __('Item') }}</th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th class="aligned-right">{{ __('Total price') }}</th>
                                </tr>
                            </thead>
                            @foreach ($quote->additional_services as $description => $price)
                                <thead>
                                    <tr>
                                        <td class="td">{{ $description }}</td>
                                        <td class="td"></td>
                                        <td class="td"></td>
                                        <td class="td"></td>
                                        <td class="aligned-right td">{{ number_format($price, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                </thead>
                            @endforeach
                        </div>
                        {{-- end servizi aggiuntivi table --}}
                    @endif
                    {{-- start riepilogo table --}}
                    <thead style="page-break-before:always;" <tr class="table-header-style">
                        <td>{{ __('Summary') }}</td>
            </tr>
            </thead>
            <thead>
                <tr>
                    <td class="td">{{ __('Activation costs') }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">{{ number_format($quote->getTotalPrice(), 2, ',', '.') }}
                        €
                    </td>
                </tr>
            </thead>
            <thead>
                <tr>
                    <td class="td">{{ __('Annual subscription costs') }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">
                        {{ number_format($quote->getTotalRecurringPrice(), 2, ',', '.') }} €
                    </td>
                </tr>
            </thead>
            <thead>
                <tr>
                    <td class="td">{{ __('Additional services costs') }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">
                        {{ number_format($quote->getTotalAdditionalServicesPrice(), 2, ',', '.') }} €
                    </td>
                </tr>
            </thead>
            <thead>
                @if ($quote->discount > 0)
                    <tr>
                        <td class="td">{{ __('Discount') }}</td>
                        <td class="td"></td>
                        <td class="td"></td>
                        <td class="td"></td>
                        <td class="aligned-right td">{{ number_format($quote->discount, 2, ',', '.') }} €
                        </td>
                    </tr>
                @endif
            </thead>
            <thead>
                <tr>
                    <td class="td" style="color: #005485;">{{ __('Final price (without VAT)') }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td style="color: #005485;" class="aligned-right td ">
                        {{ number_format($quote->getQuoteNetPrice(), 2, ',', '.') }} €</td>
                </tr>
            </thead>
            <thead>
                <tr>
                    <td class="td">{{ __('VAT (22%)') }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">
                        {{ number_format($quote->getQuoteNetPrice() * 0.22, 2, ',', '.') }} €
                    </td>
                </tr>
            </thead>
            <thead>
                <tr class="table-header-style">
                    <td class="td">{{ __('Final price (with VAT)') }}</td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="td"></td>
                    <td class="aligned-right td">
                        {{ number_format($quote->getQuoteNetPrice() + $quote->getQuoteNetPrice() * 0.22, 2, ',', '.') }}
                        €
                    </td>
                </tr>
            </thead>
            {{-- end riepilogo table --}}
            </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td class="td-placeholder">
                    <!--place holder for the fixed-position header-->
                    <div class="footer-space"></div>

                </td>
            </tr>

        </tfoot>

    </table>

    <div class="message">
        <p>{{ __('At your disposal for any clarification, we send you cordial greetings.') }}</p>
        <br>
        <p>{{ __('Pisa,') }} {{ date('d-m-Y') }}</p>
        <p>{{ __('Alessio Piccioli,') }}</p>
        <p>{{ __('Administrator of Webmapp.') }}</p>
    </div>





</body>

</html>
