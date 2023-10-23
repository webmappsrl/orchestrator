<!DOCTYPE html>
<html>

<head>
    <title>Digest di Customer Stories</title>
</head>

<body>
    <h1>Digest Customer Stories {{ \Carbon\Carbon::now()->format('d-m-Y') }}</h1>
    <p>Gentile cliente,</p>
    <p>Siamo lieti di inviarti il nostro ultimo riepilogo sulle tue Customer stories per tenerti aggiornato sullo
        stato dei tuoi progetti sulla nostra piattaforma. Siamo impegnati a garantire che le tue esigenze siano
        soddisfatte al meglio.

        Ecco uno sguardo preciso allo stato delle tue customer stories:</p>

    <h2>Storie Concluse</h2>
    <ul>
        @foreach ($doneStories as $story)
            <li>{{ $story->name }}</li>
        @endforeach
    </ul>

    <h2>Storie in Test</h2>
    <ul>
        @foreach ($testStories as $story)
            <li>{{ $story->name }}</li>
        @endforeach
    </ul>

    <h2>Storie in corso</h2>
    <ul>
        @foreach ($progressStories as $story)
            <li>{{ $story->name }}</li>
        @endforeach
    </ul>
    <p>Il nostro team di sviluppatori sta lavorando diligentemente per garantire che tutte le tue richieste siano
        gestite in modo efficiente e professionale. Restiamo a tua disposizione per rispondere a qualsiasi domanda o
        richiesta specifica.</p>
    <p>Grazie per aver scelto la nostra piattaforma per le tue esigenze di sviluppo. Siamo entusiasti di continuare a
        lavorare al tuo fianco per raggiungere gli obiettivi del progetto.</p>
    <p>Ti preghiamo di non esitare a contattarci per ulteriori dettagli o richieste personalizzate. La tua soddisfazione
        è la nostra priorità.</p>
    <p>Cordiali saluti,</p>

    <p>Il team di sviluppo Webmapp.</p>
</body>

</html>
