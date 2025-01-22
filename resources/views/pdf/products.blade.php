<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ __('Products List') }}</title>
</head>

<body style="font-family: Arial, sans-serif; margin: 0; padding: 0; color: #333;">
    <header
        style="display: flex; justify-content: flex-end; align-items: flex-start; padding: 10px; border-bottom: 2px solid #005485;">
        <div style="text-align: right;">
            <div class="logo" style="margin-bottom: 5px;">
                <img src="{{ public_path('images/logo.svg') }}" alt="Logo" style="max-height: 60px;">
            </div>
            <div>
                <p style="margin: 0; font-size: 10px; line-height: 1.2; color: #005485;">
                    Webmapp S.r.l. - Via Antonio Cei - 56123 Pisa <br>
                    CF/P.iva 02266770508 - Tel +39 3285360803 <br>
                    www.webmapp.it | info@webmapp.it
                </p>
            </div>
        </div>
    </header>

    <main style="padding: 20px;">
        @if ($products->count() > 0)
            <h2
                style="font-size: 18px; color: #005485; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 15px;">
                {{ __('Products/Activation Prices') }}
            </h2>
            <table
                style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-family: Arial, sans-serif; page-break-inside: auto; page-break-after: always;">
                <thead>
                    <tr style="background-color: #f4f4f4; border-bottom: 2px solid #ccc;">
                        <th style="width: 20%; padding: 8px; text-align: left; font-size: 13px; color: #005485;">
                            {{ __('Name') }}
                        </th>
                        <th style="width: 20%; padding: 8px; text-align: left; font-size: 13px; color: #005485;">
                            {{ __('SKU') }}
                        </th>
                        <th style="width: 40%; padding: 8px; text-align: left; font-size: 13px; color: #005485;">
                            {{ __('Description') }}</th>
                        <th style="width: 20%; padding: 8px; text-align: left; font-size: 13px; color: #005485;">
                            {{ __('One-off price') }}
                            (€)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="font-size: 12px; text-align: left; padding: 8px; background-color: #fafafa;">
                                {{ $product->name }}</td>
                            <td style="font-size: 12px; text-align: left; padding: 8px; background-color: #fff;">
                                {{ $product->sku }}</td>
                            <td style="font-size: 12px; text-align: left; padding: 8px; background-color: #fff;">
                                {{ $product->description }}</td>
                            <td style="font-size: 12px; text-align: right; padding: 8px; background-color: #fafafa;">
                                {{ number_format($product->price, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($recurringProducts->count() > 0)
            <h2
                style="font-size: 18px; color: #005485; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 15px; margin-top: 30px;">
                {{ __('Annual subscription prices') }}
            </h2>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-family: Arial, sans-serif;">
                <thead>
                    <tr style="background-color: #f4f4f4; border-bottom: 2px solid #ccc;">
                        <th style="width: 20%; padding: 8px; text-align: left; font-size: 13px; color: #005485;">
                            {{ __('Name') }}
                        </th>
                        <th style="width: 20%; padding: 8px; text-align: left; font-size: 13px; color: #005485;">
                            {{ __('SKU') }}
                        </th>
                        <th style="width: 40%; padding: 8px; text-align: left; font-size: 13px; color: #005485;">
                            {{ __('Description') }}</th>
                        <th style="width: 20%; padding: 8px; text-align: left; font-size: 13px; color: #005485;">
                            {{ __('Annual price') }}
                            (€)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recurringProducts as $recurringProduct)
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="font-size: 12px; text-align: left; padding: 8px; background-color: #fafafa;">
                                {{ $recurringProduct->name }}</td>
                            <td style="font-size: 12px; text-align: left; padding: 8px; background-color: #fff;">
                                {{ $recurringProduct->sku }}</td>
                            <td style="font-size: 12px; text-align: left; padding: 8px; background-color: #fff;">
                                {{ $recurringProduct->description }}</td>
                            <td style="font-size: 12px; text-align: right; padding: 8px; background-color: #fafafa;">
                                {{ number_format($recurringProduct->price, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    </main>

    <footer
        style="text-align: center; padding: 10px 20px; border-top: 2px solid #005485; font-size: 10px; color: #005485;">
        <p style="margin: 0;">
            Webmapp S.r.l. - Via Antonio Cei - 56123 Pisa <br>
            CF/P.iva 02266770508 - Tel +39 3285360803 <br>
            www.webmapp.it | info@webmapp.it
        </p>
    </footer>
</body>

</html>
