<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Non Trovato</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        .container {
            width: 80%;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }

        p {
            line-height: 1.6;
            margin-bottom: 16px;
        }

        a {
            color: #007bff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Account Non Trovato su <a href="{{ config('app.url') }}">Orchestrator</a></h1>
        <p>Ciao,</p>
        <p>Abbiamo ricevuto la tua segnalazione con oggetto <strong>{{ $sub }}</strong>, ma non siamo riusciti
            a trovare un account associato al tuo indirizzo email sulla piattaforma Orchestrator.</p>
        <p>Per poter gestire correttamente il tuo ticket, ti invitiamo a verificare se hai effettuato l'accesso con
            l'indirizzo email corretto. Se non hai ancora un account su Orchestrator, ti chiediamo di contattare il
            nostro
            supporto all'indirizzo <a href="mailto:info@webmapp.it">info@webmapp.it</a> che ti aiuterá a crearne uno per
            poter aprire un ticket e ricevere assistenza.</p>
        <p>Questa è una comunicazione automatica, se hai bisogno di ulteriore assistenza contattaci all'indirizzo <a
                href="mailto:info@webmapp.it">info@webmapp.it</a></p>
        <p>Grazie per la tua comprensione.</p>
        <div class="footer">
            <p>Cordiali saluti,</p>
            <p>Il Team Webmapp</p>
        </div>
    </div>
</body>

</html>
