<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Changelog') }}</title>
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

        .minor-releases-menu {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .minor-release-link {
            display: block;
            padding: 20px;
            background-color: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            color: #343a40;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .minor-release-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #2FBDA5;
        }

        .minor-release-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            border-color: #2FBDA5;
        }

        .minor-release-version {
            font-size: 20px;
            font-weight: bold;
            color: #2FBDA5;
            margin-bottom: 5px;
        }

        .minor-release-patches-count {
            font-size: 14px;
            color: #6c757d;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2FBDA5;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('Changelog') }}</h1>
        <div class="intro">
            {{ __('Seleziona una minor release per visualizzare tutte le patch relative.') }}
        </div>

        <div class="minor-releases-menu">
            @foreach($minorReleases as $minorVersion => $release)
                <a href="{{ url('/changelog/' . $minorVersion) }}" class="minor-release-link">
                    <div class="minor-release-version">MS-{{ $minorVersion }}.x</div>
                    <div class="minor-release-patches-count">
                        {{ count($release['patches']) }} {{ count($release['patches']) === 1 ? 'patch' : 'patches' }}
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</body>
</html>

