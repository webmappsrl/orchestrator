<!DOCTYPE html>
<html>

<head>
    <title>Digest di Customer Stories</title>
</head>

<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #333; font-size: 24px;">Digest Customer Stories {{ \Carbon\Carbon::now()->format('d-m-Y') }}</h1>
    <p style="color: #666; font-size: 16px; line-height: 1.5;">Gentile {{ $customer->name }},</p>
    <p style="color: #666; font-size: 16px; line-height: 1.5;">Siamo lieti di inviarti il nostro ultimo riepilogo sulle
        tue Customer stories per tenerti aggiornato sullo
        stato dei tuoi progetti sulla nostra piattaforma. Siamo impegnati a garantire che le tue esigenze siano
        soddisfatte al meglio.</p>

    <table style="border-collapse: collapse; width: 100%; margin-top: 20px;">
        <tr>
            <th style="background-color: #333; color: #fff; padding: 10px; text-align: left;">Storie Concluse</th>
            <th style="background-color: #333; color: #fff; padding: 10px; text-align: left;">Storie in Test</th>
            <th style="background-color: #333; color: #fff; padding: 10px; text-align: left;">Storie in corso</th>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; padding: 10px; vertical-align: top;">
                @if ($doneStories->isNotEmpty())
                    <ul style="list-style-type: none; padding-left: 0;">
                        @foreach ($doneStories as $story)
                            <li><a style="color: #007bff; text-decoration: none;"
                                    href="{{ url('/resources/stories/' . $story->id) }}"
                                    target="blank">{{ $story->id }}</a>
                            </li>
                            <hr>
                        @endforeach
                    </ul>
                @else
                    Nessuna storia conclusa
                @endif
            </td>
            <td style="border: 1px solid #ccc; padding: 10px; vertical-align: top;">
                @if ($testStories->isNotEmpty())
                    <ul style="list-style-type: none; padding-left: 0;">
                        @foreach ($testStories as $story)
                            <li><a style="color: #007bff; text-decoration: none;"
                                    href="{{ url('/resources/stories/' . $story->id) }}"
                                    target="blank">{{ $story->id }}</a>
                            </li>
                            <hr>
                        @endforeach
                    </ul>
                @else
                    Nessuna storia in test
                @endif
            </td>
            <td style="border: 1px solid #ccc; padding: 10px; vertical-align: top;">
                @if ($progressStories->isNotEmpty())
                    <ul style="list-style-type: none; padding-left: 0;">
                        @foreach ($progressStories as $story)
                            <li><a style="color: #007bff; text-decoration: none;"
                                    href="{{ url('/resources/stories/' . $story->id) }}"
                                    target="blank">{{ $story->id }}</a>
                            </li>
                            <hr>
                        @endforeach
                    </ul>
                @else
                    Nessuna storia in corso
                @endif
            </td>
        </tr>
    </table>

    <p style="color: #666; font-size: 16px; line-height: 1.5; margin-top: 20px;">Il nostro team di sviluppatori sta
        lavorando diligentemente per garantire che tutte le tue richieste siano
        gestite in modo efficiente e professionale. Restiamo a tua disposizione per rispondere a qualsiasi domanda o
        richiesta specifica.</p>
    <p style="color: #666; font-size: 16px; line-height: 1.5;">Grazie per aver scelto la nostra piattaforma per le tue
        esigenze di sviluppo. Siamo entusiasti di continuare a
        lavorare al tuo fianco per raggiungere gli obiettivi del progetto.</p>
    <p style="color: #666; font-size: 16px; line-height: 1.5;">Ti preghiamo di non esitare a contattarci per ulteriori
        dettagli o richieste personalizzate. La tua soddisfazione
        è la nostra priorità.</p>

    <p style="color: #666; font-size: 16px; line-height: 1.5; margin-top: 20px;">Cordiali saluti,<br>Il team di sviluppo
        Webmapp.</p>
</body>

</html>
