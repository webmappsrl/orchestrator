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

        .minor-releases-grid {
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
            cursor: pointer;
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

        .release-section {
            display: none;
        }

        .release-section.active {
            display: block;
        }

        .release-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
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
        <h1>{{ __('Changelog') }}</h1>
        
        <div id="menu-view" class="release-section active">
            <div class="intro">
                {{ __('Seleziona una minor release per visualizzare tutte le patch relative.') }}
            </div>

            <div class="minor-releases-grid">
                @foreach($minorReleases as $minorVersion => $release)
                    <a href="#" class="minor-release-link" onclick="showVersion('{{ $minorVersion }}'); return false;">
                        <div class="minor-release-version">MS-{{ $minorVersion }}.x</div>
                        <div class="minor-release-patches-count">
                            {{ count($release['patches']) }} {{ count($release['patches']) === 1 ? 'patch' : 'patches' }}
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        @foreach($minorReleases as $minorVersion => $release)
            <div id="version-{{ $minorVersion }}" class="release-section">
                <a href="#" class="back-link" onclick="showMenu(); return false;" style="display: inline-block; margin-bottom: 20px; color: #2FBDA5; text-decoration: none; font-size: 14px;">← {{ __('Torna al menu principale') }}</a>
                
                <h1>{{ __('Changelog') }} - MS-{{ $minorVersion }}.x</h1>

                <div class="minor-releases-menu">
                    @foreach($minorReleases as $mv => $r)
                        <a href="#" 
                           class="minor-release-menu-link {{ $mv === $minorVersion ? 'active' : '' }}"
                           onclick="showVersion('{{ $mv }}'); return false;">
                            MS-{{ $mv }}.x
                        </a>
                    @endforeach
                </div>

                <div class="release-list">
                    @foreach($allPatchesData[$minorVersion] as $patch)
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
        @endforeach
    </div>

    <script>
        function showVersion(version) {
            // Hide all sections
            document.querySelectorAll('.release-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show the selected version section
            const versionSection = document.getElementById('version-' + version);
            if (versionSection) {
                versionSection.classList.add('active');
                // Scroll to top
                window.scrollTo(0, 0);
            }
        }

        function showMenu() {
            // Hide all sections
            document.querySelectorAll('.release-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show menu
            document.getElementById('menu-view').classList.add('active');
            // Scroll to top
            window.scrollTo(0, 0);
        }

        // Check if there's a version parameter in the URL
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const version = urlParams.get('version');
            if (version) {
                showVersion(version);
            }
        });
    </script>
</body>
</html>

