@php
    $customerName = $quote->customer->full_name ?? $quote->customer->name;
    $pdfName = 'Preventivo_WEBMAPP_' . $customerName;
@endphp

<!DOCTYPE html>
<html lang="en">

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
    </header>
    <table>
        <thead>
            <tr>
                <td class="td-placeholder">
                    <!--place holder for the fixed-position header-->
                    <div class="header-space"></div>
                </td>
            </tr>

        </thead>
        <tbody>
            <div class="customer-details">
                <h3>Spett.le</h3>
                <p><strong>{{ $quote->customer->full_name ?? $quote->customer->name }}</strong></p>
                {{-- additional customers info --}}
            </div>
            <div class="subject-container">
                <p>Oggetto: <br><br>{{ $quote->title }}</p>
                {{-- <p>Emesso il: {{ date('d-m-Y', strtotime($quote->created_at)) }}</p>
                <p>Scadenza: {{ date('d-m-Y', strtotime($quote->created_at->addDays(30))) }} </p> --}}
            </div>
            <h4>Con la presente inviamo il preventivo per i servizi qui sotto descritti:</h4>

            <main class="service-description">
                @if (count($quote->products) < 1)
                    <h2 style="color:red;">Nessun elemento disponibile</h2>
                @else
                    <h2 class="description">Caratteristiche del servizio</h2>
                    <p>Il servizio prevede:</p>
                    <div class="service-details">
                        <ul>
                            @foreach ($quote->products as $product)
                                <li>{{ $product->description }} </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($quote->additional_info)
                    <h2 class="description">Informazioni aggiuntive</h2>
                    <p class="additional-info">{{ $quote->additional_info }}</p>
                @endif
                @if ($quote->payment_plan)
                    <div class="payment-plan">
                        <h2 class="description">Modalità di pagamento</h2>
                        <p> {{ $quote->payment_plan }}</p>
                    </div>
                @endif
                @if ($quote->delivery_time)
                    <div class="delivery-time">
                        <h2 class="description">Tempi di consegna</h2>
                        <p> {{ $quote->delivery_time }}</p>
                    </div>
                @endif

                @if ($quote->products->count() > 0)
                    <h2 class="costs">Costi</h2>
                    <p>Di seguito indichiamo i costi del servizio suddivisi in costi di attivazione e costi di
                        abbonamento
                        annuale
                    </p>

                    {{-- Start Prodotti e servizi table --}}
                    <thead>
                        <tr class="table-header-style">
                            <td>
                                Prodotti e servizi (costo di attivazione)
                            </td>
                            <td style="display: none"></td>

                        </tr>
                    </thead>
        <tbody>
            <thead>
                <tr>
                    <th>Oggetto</th>
                    <th>SKU</th>
                    <th>Prezzo unitario</th>
                    <th>Quantita'</th>
                    <th class="aligned-right">Prezzo totale</th>
                </tr>
            </thead>
            @foreach ($quote->products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->sku }}</td>
                    <td>{{ number_format($product->price, 2, ',', '.') }} €</td>
                    <td>{{ $product->pivot->quantity }}</td>
                    <td class="aligned-right">
                        {{ number_format($product->price * $product->pivot->quantity, 2, ',', '.') }} €</td>
                </tr>
            @endforeach
            <tr style="color: #005485;">
                <td>Subtotale:</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ number_format($quote->getTotalPrice(), 2, ',', '.') }} €</td>
            </tr>
            <tr style="color: #005485;">
                <td>IVA:</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ number_format($quote->getTotalPrice() * 0.22, 2, ',', '.') }} €</td>
            </tr>
            <tr class="table-header-style">
                <td>Totale costi attivazione</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ number_format($quote->getTotalPrice() * 1.22, 2, ',', '.') }} €</td>
            </tr>
        </tbody>
        {{-- end prodotti e servizi table --}}
        @endif
        @if ($quote->recurringProducts->count() > 0)
            {{-- start servizi di manutenzione table --}}
            <thead>
                <tr class="table-header-style">
                    <td>Servizi di manutenzione <br> (costi di abbonamento annuale)</td>
                </tr>
            </thead>
            <tbody>
                <thead>
                    <tr>
                        <th>Oggetto</th>
                        <th>SKU</th>
                        <th>Prezzo unitario</th>
                        <th>Quantita'</th>
                        <th class="aligned-right">Prezzo totale</th>
                    </tr>
                </thead>
                @foreach ($quote->recurringProducts as $recurringProduct)
                    <tr>
                        <td>{{ $recurringProduct->name }}</td>
                        <td>{{ $recurringProduct->sku }}</td>
                        <td>{{ number_format($recurringProduct->price, 2, ',', '.') }} €</td>
                        <td>{{ $recurringProduct->pivot->quantity }}</td>
                        <td class="aligned-right">
                            {{ number_format($recurringProduct->price * $recurringProduct->pivot->quantity, 2, ',', '.') }}
                            €
                        </td>
                    </tr>
                @endforeach
                <tr style="color: #005485;">
                    <td>Subtotale annuo:</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right">
                        {{ number_format($quote->getTotalRecurringPrice() / count($quote->recurringProducts), 2, ',', '.') }}
                        €
                    </td>

                </tr>
                <tr style="color: #005485;">
                    <td>Subtotale complessivo:</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right"> {{ number_format($quote->getTotalRecurringPrice(), 2, ',', '.') }} €
                    </td>

                </tr>
                <tr style="color: #005485;">
                    <td>IVA annua:</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right">
                        {{ number_format(($quote->getTotalRecurringPrice() / count($quote->recurringProducts)) * 0.22, 2, ',', '.') }}
                        €</td>
                </tr>
                <tr style="color: #005485;">
                    <td>IVA complessiva:</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right">{{ number_format($quote->getTotalRecurringPrice() * 0.22, 2, ',', '.') }}
                        €
                    </td>
                </tr>
                <tr class="table-header-style">
                    <td>Totale costi abbonamento annuo</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right">
                        {{ number_format(($quote->getTotalRecurringPrice() / count($quote->recurringProducts)) * 1.22, 2, ',', '.') }}
                        €
                    </td>
                </tr>
                <tr class="table-header-style">
                    <td>Totale costi abbonamento complessivo</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right">
                        {{ number_format($quote->getTotalRecurringPrice() * 1.22, 2, ',', '.') }} €</td>
                </tr>
            </tbody>
            {{-- end servizi di manutenzione table --}}
        @endif
        {{-- start servizi aggiuntivi table --}}
        @if (count($quote->additional_services) > 0)
            <thead>
                <tr class="table-header-style">
                    <td>Servizi Aggiuntivi</td>
                </tr>
            </thead>
            <tbody>
                <thead>
                    <tr>
                        <th>Oggetto</th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th class="aligned-right">Prezzo totale</th>
                    </tr>
                </thead>
                @foreach ($quote->additional_services as $description => $price)
                    <tr>
                        <td>{{ $description }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="aligned-right">{{ number_format($price, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            </tbody>
            {{-- end servizi aggiuntivi table --}}
        @endif
        {{-- start riepilogo table --}}
        <thead>
            <tr class="table-header-style">
                <td>Riepilogo</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Ammontare dei costi di attivazione </td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ number_format($quote->getTotalPrice(), 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td>Ammontare dei costi di abbonamento annuale</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ number_format($quote->getTotalRecurringPrice(), 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td>Ammontare dei servizi aggiuntivi</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ number_format($quote->getTotalAdditionalServicesPrice(), 2, ',', '.') }} €
                </td>
            </tr>
            <tr>
                <td>Sconto</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ number_format($quote->discount ?? 0, 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td style="color: #005485;">Prezzo finale (senza IVA)</td>
                <td></td>
                <td></td>
                <td></td>
                <td style="color: #005485;" class="aligned-right ">
                    {{ number_format($quote->getQuoteNetPrice(), 2, ',', '.') }}€</td>
            </tr>
            <tr>
                <td>IVA (22%)</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ number_format($quote->getQuoteNetPrice() * 0.22, 2) }}€</td>
            </tr>
            <tr class="table-header-style">
                <td>Prezzo finale (con IVA)</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">
                    {{ number_format($quote->getQuoteNetPrice() + $quote->getQuoteNetPrice() * 0.22, 2) }}€
                </td>
            </tr>
        </tbody>
        {{-- end riepilogo table --}}

        </main>
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
        <p>A disposizione per ogni eventuale chiarimento, inviamo cordiali saluti.</p>
        <br>
        <p>Pisa, {{ date('d-m-Y') }}</p>
        <p>Alessio Piccioli</p>
        <p>Amministratore di Webmapp</p>
    </div>
    <footer class="webmapp-footer">
        <p>Webmapp S.r.l. - Via Antonio Cei - 56123 Pisa</p>
        <p>CF/P.iva 02266770508 - Tel +39 3285360803</p>
        <p>www.webmapp.it | info@webmapp.it</p>

    </footer>




</body>

</html>
