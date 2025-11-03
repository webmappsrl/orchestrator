<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Documentazione Stati Ticket') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
        }

        h1 {
            color: #343a40;
            font-size: 28px;
            margin-bottom: 10px;
            border-bottom: 3px solid #2FBDA5;
            padding-bottom: 10px;
        }

        .intro {
            color: #6c757d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
            background-color: white;
        }

        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .status-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .status-color {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            margin-right: 15px;
            border: 2px solid rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .status-name {
            font-size: 18px;
            font-weight: bold;
            color: #343a40;
            margin: 0;
        }

        .status-description {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .status-value {
            color: #868e96;
            font-size: 12px;
            font-style: italic;
        }

        .legend {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }

        .legend-title {
            font-size: 18px;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 15px;
        }

        .legend-item {
            display: inline-block;
            margin: 5px 15px 5px 0;
            padding: 5px 15px;
            border-radius: 5px;
            font-size: 14px;
        }

        .legend-orange {
            background-color: #fff3e0;
            border-left: 4px solid #ea580c;
        }

        .legend-green {
            background-color: #e8f5e9;
            border-left: 4px solid #16a34a;
        }

        .legend-blue {
            background-color: #e3f2fd;
            border-left: 4px solid #3b82f6;
        }

        .legend-red {
            background-color: #ffebee;
            border-left: 4px solid #dc2626;
        }

        .legend-yellow {
            background-color: #fffde7;
            border-left: 4px solid #eab308;
        }

        .legend-gray {
            background-color: #f5f5f5;
            border-left: 4px solid #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('Documentazione Stati Ticket') }}</h1>
        <div class="intro">
            {{ __('Questa pagina descrive tutti gli stati possibili di un ticket nel sistema, il loro significato e i colori associati per facilitare la lettura e la comprensione della situazione dei ticket.') }}
        </div>

        <div class="status-grid">
            @foreach($statuses as $status)
                <div class="status-card">
                    <div class="status-header">
                        <div class="status-color" style="background-color: {{ $status['color'] }}80;">
                            {{ $status['icon'] ?? '' }}
                        </div>
                        <h3 class="status-name">{{ $status['label'] }}</h3>
                    </div>
                    <div class="status-description">
                        {{ $status['description'] }}
                    </div>
                    <div class="status-value">{{ __('Valore') }}: {{ $status['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="legend">
            <div class="legend-title">{{ __('Legenda Color') }}</div>
            <div class="legend-item legend-orange">{{ __('Arancione') }}: {{ __('In lavorazione (assigned, todo, progress, testing)') }}</div>
            <div class="legend-item legend-green">{{ __('Verde') }}: {{ __('Completato (tested, released, done)') }}</div>
            <div class="legend-item legend-blue">{{ __('Blu') }}: {{ __('Nuovo') }}</div>
            <div class="legend-item legend-red">{{ __('Rosso') }}: {{ __('Problema/Respinto (problem, rejected)') }}</div>
            <div class="legend-item legend-yellow">{{ __('Giallo') }}: {{ __('In attesa (waiting)') }}</div>
            <div class="legend-item legend-gray">{{ __('Grigio') }}: {{ __('Backlog') }}</div>
        </div>
    </div>
</body>
</html>

