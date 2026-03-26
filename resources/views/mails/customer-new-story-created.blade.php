<!DOCTYPE html>
<html>

<head>
    <title>New Story Created</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .header,
        .footer {
            background-color: #f5f5f5;
            padding: 16px;
        }

        .content {
            padding: 16px;
        }

        img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <strong>{{ $story->name }}</strong>
        </div>
        <div class="content">
            {!! $story->customer_request !!}
            <p><a href="{{ url('/resources/customer-stories/' . $story->id) }}">Ticket {{ $story->id }}</a></p>
        </div>
        <div class="footer">
            <p>Orchestrator©</p>
        </div>
</body>

</html>
