<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="/app.css">
    <title>Quotes</title>
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
                <p>Oggetto: {{ $quote->title }}</p>
                {{-- <p>Emesso il: {{ date('d-m-Y', strtotime($quote->created_at)) }}</p>
                <p>Scadenza: {{ date('d-m-Y', strtotime($quote->created_at->addDays(30))) }} </p> --}}
                <h4>Con la presente inviamo il preventivo per i servizi qui sotto descritti:</h4>
            </div>

            <main class="service-description">
                @if (count($quote->products) < 1)
                    <h2 style="color:red;">Nessun elemento disponibile</h2>
                @else
                    <h2 class="description-h2">Caratteristiche del servizio</h2>
                    <p>Il servizio prevede:</p>
                    <div class="service-details">
                        <ul>
                            @foreach ($quote->products as $product)
                                <li>{{ $product->description }} </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($quote->products->count() > 0)
                    <h2 class="costs-h2">Costi</h2>
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
                    <td>{{ $product->price }}€</td>
                    <td>{{ $product->pivot->quantity }}</td>
                    <td class="aligned-right">{{ $product->price * $product->pivot->quantity }}€</td>
                </tr>
            @endforeach
            <tr style="color: #005485;">
                <td>Subtotale:</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ $quote->getTotalPrice() }}€</td>
            </tr>
            <tr style="color: #005485;">
                <td>IVA:</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ $quote->getTotalPrice() * 0.22 }}€</td>
            </tr>
            <tr class="table-header-style">
                <td>Totale costi attivazione</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ $quote->getTotalPrice() * 1.22 }}€</td>
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
                        <td>{{ $recurringProduct->price }}€</td>
                        <td>{{ $recurringProduct->pivot->quantity }}</td>
                        <td class="aligned-right">{{ $recurringProduct->price * $recurringProduct->pivot->quantity }}€
                        </td>
                    </tr>
                @endforeach
                <tr style="color: #005485;">
                    <td>Subtotale:</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right"> {{ $quote->getTotalRecurringPrice() }}€</td>
                </tr>
                <tr style="color: #005485;">
                    <td>IVA:</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right">{{ $quote->getTotalRecurringPrice() * 0.22 }}€</td>
                </tr>
                <tr class="table-header-style">
                    <td>Totale costi abbonamento annuale</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="aligned-right">{{ $quote->getTotalRecurringPrice() * 1.22 }}€</td>
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
                        <td class="aligned-right">{{ $price }}€</td>
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
                <td class="aligned-right">{{ $quote->getTotalPrice() }}€</td>
            </tr>
            <tr>
                <td>Ammontare dei costi di abbonamento annuale</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ $quote->getTotalRecurringPrice() }}€</td>
            </tr>
            <tr>
                <td>Ammontare dei servizi aggiuntivi</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ $quote->getTotalAdditionalServicesPrice() }}€</td>
            </tr>
            <tr>
                <td>Sconto</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="aligned-right">{{ $quote->discount ?? 0 }}€</td>
            </tr>
            <tr>
                <td style="color: #005485;">Prezzo finale (senza IVA)</td>
                <td></td>
                <td></td>
                <td></td>
                <td style="color: #005485;" class="aligned-right ">{{ $quote->getQuoteNetPrice() }}€</td>
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
