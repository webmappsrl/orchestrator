<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f6fb;
            color: #1f2937;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 700px;
            margin: 24px auto;
            background-color: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.08);
            border: 1px solid #e5e7eb;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin: 22px 0 10px;
        }

        a {
            color: #3498db;
            text-decoration: none;
        }

        img {
            max-width: 100% !important;
            height: auto !important;
        }

        .status-line {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 14px 16px;
            border-radius: 10px;
        }

        .status-row {
            display: block;
        }

        .conversation {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
        }

        .conversation-item {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 8px;
            margin-bottom: 10px;
        }

        .conversation-item p {
            margin: 0 0 10px 0;
        }

        .conversation-item p:last-child {
            margin-bottom: 0;
        }

        .conversation-item a {
            word-break: break-all;
        }

        .conversation-item.latest {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.22);
        }

        .latest-label {
            display: inline-block;
            margin-bottom: 8px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            color: #92400e;
            background: #fef3c7;
        }

        .muted {
            color: #6b7280;
            margin: 0;
        }

        .footer {
            text-align: center;
            font-size: 0.9em;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 12px;
            margin-top: 22px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 14px 0;
            color: #0f172a;
        }

        .link-box {
            margin: 14px 0 18px 0;
            text-align: left;
        }
    </style>
</head>

<body>
    <div class="container">
        <p style="margin-top: 0;">
            Puoi visualizzare i dettagli della storia a questo
            <a href="{{ url('resources/story-showed-by-customers/' . $story->id) }}">link</a>.
        </p>

        @include('mails.partials.conversation', [
            'story' => $story,
            'highlightLatest' => true,
        ])

        @include('mails.partials.email-footer')
    </div>
</body>

</html>