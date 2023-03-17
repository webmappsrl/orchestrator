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
    <h1 class="quote-header"> Quote: {{ $quote->title }} </h1>
    <div class="quote-details">
        <p> <strong>Id:</strong> {{ $quote->id }}</p>
        <p> <strong>Creata il:</strong> {{ $quote->created_at }}</p>
        <p> <strong>Destinatario:</strong> {{ $quote->customer->full_name ?? $quote->customer->name }}</p>
    </div>

    <div class="tables-container">
        <div class="products-table">
            <h2>Prodotti</h2>
            @if (count($quote->products) < 1)
                <h2 class="no-elements">Nessun elemento disponibile</h2>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Descrizione</th>
                            <th>SKU</th>
                            <th>Prezzo unitario</th>
                            <th>Quantità</th>
                            <th>Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quote->products as $product)
                            <tr>
                                <td>{{ $product->description }}</td>
                                <td>{{ $product->sku }}</td>
                                <td>{{ $product->price }}€</td>
                                <td>{{ $product->pivot->quantity }}</td>
                                <td>{{ $product->price * $product->pivot->quantity }}€</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            @endif

        </div>

        <div class="recurring-products-table">
            <h2>Prodotti Ricorrenti</h2>
            @if (count($quote->recurringProducts) < 1)
                <h2 class="no-elements">Nessun elemento disponibile</h2>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Descrizione</th>
                            <th>SKU</th>
                            <th>Prezzo unitario</th>
                            <th>Quantità</th>
                            <th>Costo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quote->recurringProducts as $product)
                            <tr>
                                <td>{{ $product->description }}</td>
                                <td>{{ $product->sku }}</td>
                                <td>{{ $product->price }}€</td>
                                <td>{{ $product->pivot->quantity }}</td>
                                <td>{{ $product->price * $product->pivot->quantity }}€</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        </div>


        <div class="additional-services-table">
            <h2>Servizi Aggiuntivi</h2>
            @if (count($quote->additional_services) < 1)
                <h2 class="no-elements">Nessun elemento disponibile</h2>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Descrizione</th>
                            <th>Prezzo unitario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quote->additional_services as $description => $price)
                            <tr>
                                <td>{{ $description }}</td>
                                <td>{{ $price }}€</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

        </div>

        <div class="summary-table">
            <h2>Riassunto</h2>
            <table>
                <thead>
                    <tr>
                        <th>Ammontare dei prodotti</th>
                        <th>Ammontare dei prodotti ricorrenti</th>
                        <th>Ammontare dei servizi aggiuntivi</th>
                        <th>Sconto</th>
                        <th>Prezzo finale (senza IVA)</th>
                        <th>IVA (22%)</th>
                        <th>Prezzo finale (con IVA)</th>
                    </tr>
                </thead>
                <tbody>
                    <td>{{ $quote->getTotalPrice() }}€</td>
                    <td>{{ $quote->getTotalRecurringPrice() }}€</td>
                    <td>{{ $quote->getTotalAdditionalServicesPrice() }}€</td>
                    <td>{{ $quote->discount ?? 0 }}€</td>
                    <td>{{ $quote->getQuoteNetPrice() }}€</td>
                    <td>{{ round($quote->getQuoteNetPrice() * 0.22, 2) }}€</td>
                    <td>{{ round($quote->getQuoteNetPrice() + $quote->getQuoteNetPrice() * 0.22, 2) }}€</td>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
