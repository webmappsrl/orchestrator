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
                max-width: 600px;
                margin: 20px auto;
                background-color: #fff;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            .content {
                margin: 20px 0;
            }

            .footer {
                text-align: center;
                font-size: 0.9em;
                color: #777;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }

            a {
                color: #3498db;
                text-decoration: none;
            }
        </style>
    </head>

    <body>
        @if ($recipient->role === 'customer')
            <div class="container">
                <div class="content">
                    <p>
                        Il ticket <strong>#{{ $story->id }}</strong> - {{ $story->name }}

                        ha cambiato stato in: {{ $story->status }}

                    </p>
                    <p>Puoi visualizzare i dettagli della storia a questo <a
                            href="{{ url('resources/story-showed-by-customers/' . $story->id) }}">link</a>.</p>
                </div>
            </div>
        @else
            <div class="container">
                <div class="content">
                    <p>
                        <strong>Ticket #{{ $story->id }} - {{ $story->name }}</strong><br>
                    <div style="text-align: center">
                        <a href="{{ url('resources/stories/' . $story->id) }}">Link</a>
                    </div>
                    </p>
                </div>
            </div>
        @endif
    </body>

    </html>
