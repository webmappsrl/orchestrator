<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segnalazione Registrata</title>
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
        <h1>Segnalazione Registrata</h1>
        <p>Ciao,</p>
        <p>La tua segnalazione con oggetto <strong>{{ $story->name }}</strong> è stata registrata con successo su
            Orchestrator.</p>
        <p>Puoi visualizzare e seguire l'andamento della tua segnalazione utilizzando il seguente link alla tua card:
        </p>
        <p><a href="{{ url('/resources/story-showed-by-customers/' . $story->id) }}">Visualizza la tua segnalazione</a>
        </p>
        <p>Per ulteriori dettagli o per seguire il corso su Orchestrator, visita la nostra piattaforma.</p>
        <p>Grazie per averci contattato.</p>
        <div class="footer">
            <p>Cordiali saluti,</p>
            <p>Il Team Webmapp</p>
        </div>
    </div>
</body>

</html>
