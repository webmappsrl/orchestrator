<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Non Registrato</title>
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

        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <p>Mittente: <strong>{{ $userEmail }}</strong></p>
        <p>Oggetto: <strong>{{ $subject }}</strong></p>

        <p>Corpo:</p>
        <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
            {!! $originalBody !!}
        </div>
    </div>
</body>

</html>
