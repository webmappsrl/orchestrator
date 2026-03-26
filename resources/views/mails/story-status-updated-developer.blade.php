<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 680px;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .title {
            font-size: 22px;
            margin: 0 0 14px 0;
        }

        .meta {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
        }

        .meta-row {
            margin: 0 0 6px 0;
        }

        .status-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #fff;
            background-color: #2563eb;
        }

        .section-title {
            margin: 18px 0 8px 0;
            font-size: 16px;
            font-weight: bold;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .description {
            background: #fcfcfc;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .link-box {
            margin-top: 18px;
            text-align: center;
        }

        .button {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 6px;
            background: #2563eb;
            color: #fff !important;
            text-decoration: none;
            font-weight: bold;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 0.9em;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    @php
        $statusValue = (string) $story->status;
        $statusEnum = \App\Enums\StoryStatus::tryFrom($statusValue);
        $statusColor = $statusEnum?->color() ?? '#2563eb';
        $statusLabel = $statusEnum?->label() ?? ucfirst($statusValue);

        $descriptionHtml = trim((string) ($story->description ?? ''));
        $descriptionText = trim(strip_tags($descriptionHtml));

        $customerRequestHtml = trim((string) ($story->customer_request ?? ''));
        $customerRequestText = trim(strip_tags($customerRequestHtml));
    @endphp

    <div class="container">
        <h1 class="title">Aggiornamento ticket</h1>

        <div class="meta">
            <p class="meta-row">
                <strong>ID:</strong>
                <a href="{{ url('resources/stories/' . $story->id) }}">{{ $story->id }}</a>
            </p>
            <p class="meta-row"><strong>Titolo:</strong> {{ $story->name }}</p>
            <p class="meta-row">
                <strong>Stato:</strong>
                @include('mails.partials.story-status-badge', [
                    'statusColor' => $statusColor,
                    'statusLabel' => $statusLabel,
                ])
            </p>
        </div>

        @include('mails.partials.rich-html-section', [
            'title' => '',
            'showTitle' => false,
            'html' => $customerRequestHtml,
            'text' => $customerRequestText,
            'fallback' => 'Nessuna richiesta cliente disponibile.',
        ])

        @include('mails.partials.rich-html-section', [
            'title' => '',
            'showTitle' => false,
            'html' => $descriptionHtml,
            'text' => $descriptionText,
            'fallback' => 'Nessuna descrizione disponibile.',
        ])

        <div class="link-box">
            <a class="button" href="{{ url('resources/stories/' . $story->id) }}">Apri ticket</a>
        </div>

        @include('mails.partials.email-footer')
    </div>
</body>
</html>
