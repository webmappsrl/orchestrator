<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Changelog') }} - MS-{{ $minorVersion }}.x</title>
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

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2FBDA5;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .minor-releases-menu {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .minor-release-menu-link {
            display: inline-block;
            padding: 8px 16px;
            background-color: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            text-decoration: none;
            color: #343a40;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .minor-release-menu-link:hover {
            background-color: #e9ecef;
            border-color: #2FBDA5;
        }

        .minor-release-menu-link.active {
            background-color: #2FBDA5;
            color: white;
            border-color: #2FBDA5;
        }

        .release-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .release-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
            background-color: white;
            position: relative;
            overflow: hidden;
        }

        .release-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #2FBDA5;
        }

        .release-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .release-version {
            font-size: 24px;
            font-weight: bold;
            color: #2FBDA5;
            margin: 0;
        }

        .release-date {
            font-size: 14px;
            color: #6c757d;
            font-style: italic;
        }

        .release-content {
            padding: 0;
        }

        .release-html-content {
            text-align: left;
        }

        .release-html-content h1 {
            border: none;
            padding: 0;
            margin: 20px 0 15px 0;
            font-size: 24px;
        }

        .release-html-content h2 {
            margin: 20px 0 10px 0;
            font-size: 20px;
            color: #2FBDA5;
        }

        .release-html-content h3 {
            margin: 15px 0 8px 0;
            font-size: 16px;
            color: #495057;
        }

        .release-html-content ul {
            list-style-type: disc;
            padding-left: 20px;
            margin: 10px 0;
        }

        .release-html-content li {
            margin: 5px 0;
        }

        .release-html-content p {
            margin: 10px 0;
            line-height: 1.6;
        }

        .release-html-content hr {
            border: none;
            border-top: 1px solid #e0e0e0;
            margin: 20px 0;
        }

        .release-html-content strong {
            font-weight: bold;
        }

        .release-html-content em {
            font-style: italic;
        }

        .release-html-content a {
            color: #2FBDA5;
            text-decoration: none;
        }

        .release-html-content a:hover {
            text-decoration: underline;
        }

        .release-html-content code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .release-html-content pre {
            background-color: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }

        .release-html-content pre code {
            background-color: transparent;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ url('/dashboards/changelog') }}" class="back-link">← {{ __('Torna al menu principale') }}</a>
        
        <h1>{{ __('Changelog') }} - MS-{{ $minorVersion }}.x</h1>

        <div class="minor-releases-menu">
            @foreach($minorReleases as $mv => $release)
                <a href="{{ url('/dashboards/changelog-' . str_replace('.', '-', $mv)) }}" 
                   class="minor-release-menu-link {{ $mv === $minorVersion ? 'active' : '' }}">
                    MS-{{ $mv }}.x
                </a>
            @endforeach
        </div>

        <div class="release-list">
            @foreach($patches as $patch)
                <div class="release-card">
                    <div class="release-header">
                        <h2 class="release-version">MS-{{ $patch['version'] }}</h2>
                        @if($patch['date'])
                            <span class="release-date">{{ $patch['date'] }}</span>
                        @endif
                    </div>
                    <div class="release-content">
                        <div class="release-html-content">
                            {!! $patch['content'] !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>

