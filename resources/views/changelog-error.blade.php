<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Errore') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }

        h1 {
            color: #dc3545;
            font-size: 24px;
            margin-bottom: 20px;
        }

        p {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2FBDA5;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #28a085;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('Errore') }}</h1>
        <p>{{ $message ?? 'Si è verificato un errore.' }}</p>
        @if(isset($redirectUrl))
            <a href="{{ $redirectUrl }}" class="btn">{{ __('Torna al Changelog') }}</a>
        @endif
    </div>
</body>
</html>

